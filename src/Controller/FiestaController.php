<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\BracketEliminacion;
use PingPong\Domain\Fiesta\SorteoFiesta;
use PingPong\Domain\Fiesta\TablaGrupoFiesta;
use PingPong\Http\Controller;
use PingPong\Repository\AuditoriaRepository;
use PingPong\Repository\FiestaRepository;
use PingPong\Support\Auth;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\View;

/**
 * Party tournament ("torneo fiesta"): register names, spin the roulette,
 * play groups and/or a knockout. Parallel to the regular tournament system.
 */
final class FiestaController extends Controller
{
    private const MODALIDADES = ['INDIVIDUAL', 'DOBLES'];
    private const FORMATOS = [SorteoFiesta::ELIMINACION, SorteoFiesta::GRUPOS];
    private const MAX_NOMBRE = 80;

    public function __construct(
        View $view,
        private FiestaRepository $fiestas,
        private AuditoriaRepository $auditoria,
    ) {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        return $this->render('fiesta/index', [
            'title'   => 'Torneo fiesta',
            'fiestas' => $this->fiestas->all(),
        ]);
    }

    public function create(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        return $this->render('fiesta/form', ['title' => 'Nueva fiesta']);
    }

    public function store(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/fiesta/nueva');
        }

        [$data, $errors] = $this->leerConfig();
        if ($errors !== []) {
            return $this->render('fiesta/form', [
                'title'  => 'Nueva fiesta',
                'errors' => $errors,
                'old'    => $data + ['participantes' => (string) Request::post('participantes', '')],
            ]);
        }

        $id = $this->fiestas->create($data, Auth::user()['id'] ?? null);
        $nombres = $this->leerNombres((string) Request::post('participantes', ''));
        if ($nombres !== []) {
            $this->fiestas->agregarParticipantes($id, $nombres);
        }
        $this->auditoria->registrar('fiesta', $id, 'CREAR', Auth::user()['id'] ?? null, null, $data);

        Session::flash('success', '¡Fiesta creada! Sumá a los participantes y girá la ruleta.');
        return $this->redirect('/fiesta/' . $id);
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $fiesta = $this->fiestas->find((int) $params['id']);
        if ($fiesta === null) {
            return $this->notFound();
        }
        $id = (int) $fiesta['id'];

        $participantes = $this->fiestas->participantes($id);
        $equipos = $this->fiestas->equipos($id);
        $equiposPorId = [];
        foreach ($equipos as $e) {
            $equiposPorId[(int) $e['id']] = $e;
        }

        $grupos = $fiesta['estado'] === 'INSCRIPCION' ? [] : $this->grupos($id, $equipos);
        $eliminatoria = $this->fiestas->partidos($id, 'ELIMINATORIA');
        $rondas = [];
        foreach ($eliminatoria as $p) {
            $rondas[(int) $p['ronda']][] = $p;
        }
        ksort($rondas);

        $dobles = $fiesta['modalidad'] === 'DOBLES';
        $errorSorteo = null;
        try {
            SorteoFiesta::validar(count($participantes), $dobles, (string) $fiesta['formato'], (int) $fiesta['num_grupos']);
            $this->validarClasificados($fiesta, count($participantes));
        } catch (\DomainException $e) {
            $errorSorteo = $e->getMessage();
        }

        return $this->render('fiesta/show', [
            'title'         => $fiesta['nombre'],
            'fiesta'        => $fiesta,
            'participantes' => $participantes,
            'equipos'       => $equiposPorId,
            'grupos'        => $grupos,
            'rondas'        => $rondas,
            'campeon'       => $this->campeon($rondas, $equiposPorId),
            'errorSorteo'   => $errorSorteo,
            'gruposListos'  => $grupos !== [] && $this->gruposTerminados($grupos),
            'hayResultados' => $this->hayResultados($id),
        ]);
    }

    /** @param array<string,string> $params */
    public function actualizarConfig(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'INSCRIPCION');
        if ($error !== null) {
            return $error;
        }
        $id = (int) $fiesta['id'];
        [$data, $errors] = $this->leerConfig();
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            return $this->redirect('/fiesta/' . $id);
        }
        $this->fiestas->actualizarConfig($id, $data);
        Session::flash('success', 'Configuración actualizada.');
        return $this->redirect('/fiesta/' . $id);
    }

    /** @param array<string,string> $params */
    public function agregarParticipantes(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'INSCRIPCION');
        if ($error !== null) {
            return $error;
        }
        $id = (int) $fiesta['id'];
        $nombres = $this->leerNombres((string) Request::post('nombres', ''));
        if ($nombres === []) {
            Session::flash('error', 'Escribí al menos un nombre.');
            return $this->redirect('/fiesta/' . $id . '#participantes');
        }

        $r = $this->fiestas->agregarParticipantes($id, $nombres);
        $msg = $r['agregados'] === 1 ? '1 participante sumado.' : "{$r['agregados']} participantes sumados.";
        if ($r['repetidos'] !== []) {
            $msg .= ' Ya estaban: ' . implode(', ', $r['repetidos']) . '.';
        }
        Session::flash($r['agregados'] > 0 ? 'success' : 'error', $msg);
        return $this->redirect('/fiesta/' . $id . '#participantes');
    }

    /** @param array<string,string> $params */
    public function quitarParticipante(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'INSCRIPCION');
        if ($error !== null) {
            return $error;
        }
        $this->fiestas->quitarParticipante((int) $fiesta['id'], (int) $params['participante']);
        return $this->redirect('/fiesta/' . $fiesta['id'] . '#participantes');
    }

    /** Runs the random draw and opens the roulette stage. */
    public function sortear(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'INSCRIPCION');
        if ($error !== null) {
            return $error;
        }
        $id = (int) $fiesta['id'];
        $participantes = $this->fiestas->participantes($id);
        $dobles = $fiesta['modalidad'] === 'DOBLES';
        $formato = (string) $fiesta['formato'];
        $numGrupos = (int) $fiesta['num_grupos'];

        try {
            $this->validarClasificados($fiesta, count($participantes));
            $sorteo = SorteoFiesta::sortear(array_column($participantes, 'id'), $dobles, $formato, $numGrupos);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/fiesta/' . $id);
        }

        $nombres = [];
        foreach ($participantes as $p) {
            $nombres[$p['id']] = $p['nombre'];
        }
        $equipos = [];
        foreach ($sorteo['equipos'] as $e) {
            $equipos[$e['numero']] = ['p1' => $e['p1'], 'p2' => $e['p2']];
        }
        $cruces = [];
        foreach ($sorteo['bracket']['partidos'] as $p) {
            if ($p['ronda'] === 1) {
                $cruces[] = ['orden' => $p['orden'], 'a' => $p['a'], 'b' => $p['b'], 'bye' => $p['bye']];
            }
        }

        $guion = [
            'modalidad'     => $fiesta['modalidad'],
            'formato'       => $formato,
            'numGrupos'     => $numGrupos,
            'participantes' => $nombres,
            'equipos'       => $equipos,
            'pasos'         => $sorteo['guion'],
            'cruces'        => $cruces,
            'rondas'        => $sorteo['bracket']['rondas'],
        ];

        $this->fiestas->guardarSorteo($id, $formato, $sorteo, $guion);
        $this->auditoria->registrar('fiesta', $id, 'SORTEO', Auth::user()['id'] ?? null, null, [
            'participantes' => count($participantes),
            'equipos'       => count($sorteo['equipos']),
            'formato'       => $formato,
        ]);

        return $this->redirect('/fiesta/' . $id . '/ruleta');
    }

    /** @param array<string,string> $params */
    public function ruleta(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $fiesta = $this->fiestas->find((int) $params['id']);
        if ($fiesta === null) {
            return $this->notFound();
        }
        if ($fiesta['sorteo_json'] === null) {
            Session::flash('error', 'Todavía no se hizo el sorteo de esta fiesta.');
            return $this->redirect('/fiesta/' . $fiesta['id']);
        }
        return $this->render('fiesta/ruleta', [
            'title'  => 'Ruleta · ' . $fiesta['nombre'],
            'fiesta' => $fiesta,
            'guion'  => (string) $fiesta['sorteo_json'],
        ]);
    }

    /** @param array<string,string> $params */
    public function deshacerSorteo(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, null);
        if ($error !== null) {
            return $error;
        }
        $id = (int) $fiesta['id'];
        if ($fiesta['estado'] === 'INSCRIPCION') {
            return $this->redirect('/fiesta/' . $id);
        }
        if ($this->hayResultados($id)) {
            Session::flash('error', 'Ya hay resultados cargados: no se puede repetir el sorteo.');
            return $this->redirect('/fiesta/' . $id);
        }
        $this->fiestas->deshacerSorteo($id);
        $this->auditoria->registrar('fiesta', $id, 'DESHACER_SORTEO', Auth::user()['id'] ?? null, null, null);
        Session::flash('success', 'Sorteo anulado. Podés ajustar y volver a girar la ruleta.');
        return $this->redirect('/fiesta/' . $id);
    }

    /** @param array<string,string> $params */
    public function guardarResultado(array $params): string
    {
        if (($guard = $this->requireAnotar()) !== null) {
            return $guard;
        }
        $fiesta = $this->fiestas->find((int) $params['id']);
        if ($fiesta === null) {
            return $this->notFound();
        }
        $id = (int) $fiesta['id'];
        $partido = $this->fiestas->findPartido($id, (int) $params['partido']);
        $back = '/fiesta/' . $id . ($partido !== null ? '#partido-' . $partido['id'] : '');
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }
        if ($partido === null || (int) $partido['es_bye'] === 1 || $partido['equipo_a_id'] === null || $partido['equipo_b_id'] === null) {
            Session::flash('error', 'Ese partido todavía no tiene rivales definidos.');
            return $this->redirect($back);
        }
        if ($partido['fase'] !== $fiesta['estado']) {
            Session::flash('error', 'Esa fase ya está cerrada.');
            return $this->redirect($back);
        }

        $ma = Request::postInt('marcador_a');
        $mb = Request::postInt('marcador_b');
        if ($ma === null || $mb === null || $ma < 0 || $mb < 0 || $ma > 999 || $mb > 999) {
            Session::flash('error', 'Cargá el marcador de los dos lados (números de 0 a 999).');
            return $this->redirect($back);
        }
        if ($ma === $mb) {
            Session::flash('error', 'No puede haber empate: alguien tiene que ganar.');
            return $this->redirect($back);
        }
        $ganador = $ma > $mb ? (int) $partido['equipo_a_id'] : (int) $partido['equipo_b_id'];

        if ($partido['fase'] === 'ELIMINATORIA' && $partido['siguiente_id'] !== null
            && $partido['ganador_id'] !== null && (int) $partido['ganador_id'] !== $ganador) {
            $siguiente = $this->fiestas->findPartido($id, (int) $partido['siguiente_id']);
            if ($siguiente !== null && $siguiente['estado'] === 'FINALIZADO') {
                Session::flash('error', 'No se puede cambiar el ganador: el partido siguiente ya se jugó.');
                return $this->redirect($back);
            }
        }

        $this->fiestas->guardarResultado($partido, $ma, $mb, $ganador);
        $this->auditoria->registrar('fiesta_partido', (int) $partido['id'], 'CARGAR_MARCADOR', Auth::user()['id'] ?? null,
            ['a' => $partido['marcador_a'], 'b' => $partido['marcador_b']], ['a' => $ma, 'b' => $mb]);

        if ($partido['fase'] === 'ELIMINATORIA' && $partido['siguiente_id'] === null) {
            $this->fiestas->cambiarEstado($id, 'FINALIZADO');
            Session::flash('success', '🏆 ¡Tenemos campeón!');
            return $this->redirect('/fiesta/' . $id . '#campeon');
        }

        Session::flash('success', 'Resultado guardado.');
        return $this->redirect($back);
    }

    /** Group stage finished → crosswise seeded knockout. */
    public function pasarAEliminatoria(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'GRUPOS');
        if ($error !== null) {
            return $error;
        }
        $id = (int) $fiesta['id'];
        $grupos = $this->grupos($id, $this->fiestas->equipos($id));
        if (!$this->gruposTerminados($grupos)) {
            Session::flash('error', 'Faltan partidos de grupo por jugar.');
            return $this->redirect('/fiesta/' . $id);
        }

        $clasificados = [];
        foreach ($grupos as $numero => $g) {
            $clasificados[$numero] = array_column($g['tabla'], 'equipo');
        }
        $orden = TablaGrupoFiesta::sembrar($clasificados, (int) $fiesta['clasificados_por_grupo']);
        if (count($orden) < 2) {
            Session::flash('error', 'Se necesitan al menos 2 clasificados para la eliminatoria.');
            return $this->redirect('/fiesta/' . $id);
        }

        $this->fiestas->crearEliminatoria($id, BracketEliminacion::generar($orden));
        $this->auditoria->registrar('fiesta', $id, 'GENERAR_ELIMINATORIA', Auth::user()['id'] ?? null, null, ['clasificados' => count($orden)]);
        Session::flash('success', '¡Arrancó la eliminatoria! ' . count($orden) . ' equipos clasificados.');
        return $this->redirect('/fiesta/' . $id . '#eliminatoria');
    }

    /** @param array<string,string> $params */
    public function delete(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, null);
        if ($error !== null) {
            return $error;
        }
        $this->fiestas->delete((int) $fiesta['id']);
        $this->auditoria->registrar('fiesta', (int) $fiesta['id'], 'ELIMINAR', Auth::user()['id'] ?? null, ['nombre' => $fiesta['nombre']], null);
        Session::flash('success', 'Fiesta eliminada.');
        return $this->redirect('/fiesta');
    }

    // ------------------------------------------------------------------

    /**
     * ADMIN + CSRF + existence (+ optional required state).
     *
     * @param array<string,string> $params
     * @return array{0:?array<string,mixed>,1:?string}
     */
    private function fiestaGestionable(array $params, ?string $estado): array
    {
        if (($guard = $this->requireGestion()) !== null) {
            return [null, $guard];
        }
        $fiesta = $this->fiestas->find((int) $params['id']);
        if ($fiesta === null) {
            return [null, $this->notFound()];
        }
        $back = '/fiesta/' . $fiesta['id'];
        if (!$this->csrfOk()) {
            return [null, $this->csrfFail($back)];
        }
        if ($estado !== null && $fiesta['estado'] !== $estado) {
            Session::flash('error', 'Esa acción no está disponible en esta etapa de la fiesta.');
            return [null, $this->redirect($back)];
        }
        return [$fiesta, null];
    }

    /** @return array{0:array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int},1:list<string>} */
    private function leerConfig(): array
    {
        $data = [
            'nombre'                 => trim((string) Request::post('nombre', '')),
            'modalidad'              => (string) Request::post('modalidad', 'DOBLES'),
            'formato'                => (string) Request::post('formato', SorteoFiesta::ELIMINACION),
            'num_grupos'             => (int) Request::post('num_grupos', '2'),
            'clasificados_por_grupo' => (int) Request::post('clasificados_por_grupo', '2'),
        ];
        $errors = [];
        if ($data['nombre'] === '') {
            $errors['nombre'] = 'Poné un nombre a la fiesta.';
        } elseif (mb_strlen($data['nombre']) > 150) {
            $errors['nombre'] = 'El nombre admite hasta 150 caracteres.';
        }
        if (!in_array($data['modalidad'], self::MODALIDADES, true)) {
            $errors['modalidad'] = 'Elegí individual o dobles.';
        }
        if (!in_array($data['formato'], self::FORMATOS, true)) {
            $errors['formato'] = 'Elegí eliminación directa o grupos.';
        }
        if ($data['num_grupos'] < 1 || $data['num_grupos'] > 16) {
            $errors['num_grupos'] = 'Los grupos van de 1 a 16.';
        }
        if ($data['clasificados_por_grupo'] < 1 || $data['clasificados_por_grupo'] > 8) {
            $errors['clasificados_por_grupo'] = 'Los clasificados por grupo van de 1 a 8.';
        }
        return [$data, $errors];
    }

    /** @return list<string> unique trimmed names from a textarea (one per line or comma separated) */
    private function leerNombres(string $raw): array
    {
        $nombres = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $nombre) {
            $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? '');
            if ($nombre === '') {
                continue;
            }
            $nombre = mb_substr($nombre, 0, self::MAX_NOMBRE);
            $nombres[mb_strtolower($nombre)] ??= $nombre; // keep the first spelling
        }
        return array_values($nombres);
    }

    /** @throws \DomainException */
    private function validarClasificados(array $fiesta, int $participantes): void
    {
        if ($fiesta['formato'] !== SorteoFiesta::GRUPOS) {
            return;
        }
        $equipos = SorteoFiesta::cantidadEquipos($participantes, $fiesta['modalidad'] === 'DOBLES');
        $menorGrupo = intdiv($equipos, max(1, (int) $fiesta['num_grupos']));
        $k = (int) $fiesta['clasificados_por_grupo'];
        if ($k > $menorGrupo) {
            throw new \DomainException("Clasifican {$k} por grupo, pero el grupo más chico tendría {$menorGrupo} equipos. Bajá los clasificados o los grupos.");
        }
        if ($k * (int) $fiesta['num_grupos'] < 2) {
            throw new \DomainException('Con 1 grupo tienen que clasificar al menos 2 equipos.');
        }
    }

    /**
     * @param list<array<string,mixed>> $equipos
     * @return array<int,array{letra:string,equipos:list<int>,tabla:list<array<string,int>>,partidos:list<array<string,mixed>>}>
     */
    private function grupos(int $fiestaId, array $equipos): array
    {
        $grupos = [];
        foreach ($equipos as $e) {
            if ($e['grupo'] !== null) {
                $g = (int) $e['grupo'];
                $grupos[$g] ??= ['letra' => chr(64 + $g), 'equipos' => [], 'tabla' => [], 'partidos' => []];
                $grupos[$g]['equipos'][] = (int) $e['id'];
            }
        }
        foreach ($this->fiestas->partidos($fiestaId, 'GRUPOS') as $p) {
            $grupos[(int) $p['grupo']]['partidos'][] = $p;
        }
        foreach ($grupos as &$g) {
            $jugados = [];
            foreach ($g['partidos'] as $p) {
                if ($p['estado'] === 'FINALIZADO') {
                    $jugados[] = [
                        'a' => (int) $p['equipo_a_id'], 'b' => (int) $p['equipo_b_id'],
                        'ma' => (int) $p['marcador_a'], 'mb' => (int) $p['marcador_b'],
                        'ganador' => (int) $p['ganador_id'],
                    ];
                }
            }
            $g['tabla'] = TablaGrupoFiesta::calcular($g['equipos'], $jugados);
        }
        unset($g);
        ksort($grupos);
        return $grupos;
    }

    private function gruposTerminados(array $grupos): bool
    {
        foreach ($grupos as $g) {
            foreach ($g['partidos'] as $p) {
                if ($p['estado'] !== 'FINALIZADO') {
                    return false;
                }
            }
        }
        return true;
    }

    private function hayResultados(int $fiestaId): bool
    {
        foreach (['GRUPOS', 'ELIMINATORIA'] as $fase) {
            foreach ($this->fiestas->partidos($fiestaId, $fase) as $p) {
                if ((int) $p['es_bye'] === 0 && $p['estado'] === 'FINALIZADO') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array<int,list<array<string,mixed>>> $rondas
     * @param array<int,array<string,mixed>> $equipos
     * @return array<string,mixed>|null
     */
    private function campeon(array $rondas, array $equipos): ?array
    {
        if ($rondas === []) {
            return null;
        }
        $final = $rondas[max(array_keys($rondas))][0] ?? null;
        if ($final === null || $final['estado'] !== 'FINALIZADO' || $final['ganador_id'] === null) {
            return null;
        }
        return $equipos[(int) $final['ganador_id']] ?? null;
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'La fiesta no existe.']);
    }
}
