<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\EstadoTorneo;
use PingPong\Domain\FixtureRoundRobin;
use PingPong\Domain\ReglasMarcador;
use PingPong\Domain\TablaPosiciones;
use PingPong\Domain\TipoResultado;
use PingPong\Http\Controller;
use PingPong\Repository\AuditoriaRepository;
use PingPong\Repository\InscripcionRepository;
use PingPong\Repository\PartidoRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\Auth;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\View;

final class LigaController extends Controller
{
    public function __construct(
        View $view,
        private TorneoRepository $torneos,
        private InscripcionRepository $inscripciones,
        private PartidoRepository $partidos,
        private AuditoriaRepository $auditoria,
    ) {
        parent::__construct($view);
    }

    /** Genera el fixture round-robin y arranca la fase de liga. */
    public function generarFixture(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $id = (int) $params['id'];
        $torneo = $this->torneos->find($id);
        if ($torneo === null) {
            return $this->notFound();
        }
        $back = '/torneos/' . $id;
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }

        if ($torneo['estado'] !== EstadoTorneo::INSCRIPCION_CERRADA->value) {
            Session::flash('error', 'El fixture se genera con las inscripciones cerradas.');
            return $this->redirect($back);
        }
        if ($this->partidos->existenLiga($id)) {
            Session::flash('error', 'El fixture de este torneo ya fue generado.');
            return $this->redirect($back);
        }

        $insc = $this->inscripciones->porTorneo($id);
        if (count($insc) < 2) {
            Session::flash('error', 'Se necesitan al menos 2 jugadores inscritos.');
            return $this->redirect($back);
        }

        $ids = array_map(static fn (array $i): int => (int) $i['id'], $insc);
        $matches = FixtureRoundRobin::generar($ids);

        $this->partidos->crearLiga($id, $matches);
        $this->torneos->cambiarEstado($id, EstadoTorneo::FASE_LIGA->value);

        $this->auditoria->registrar('torneo', $id, 'GENERAR_FIXTURE', Auth::user()['id'] ?? null, null, [
            'partidos'  => count($matches),
            'jugadores' => count($insc),
        ]);

        Session::flash('success', 'Fixture generado: ' . count($matches) . ' partidos. ¡Arrancó la liga!');
        return $this->redirect($back . '/partidos');
    }

    /** Listado de partidos de la liga, agrupados por jornada. */
    public function partidos(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }

        $partidos = $this->partidos->liga((int) $torneo['id']);
        $porRonda = [];
        foreach ($partidos as $p) {
            $porRonda[(int) $p['ronda']][] = $p;
        }

        return $this->render('torneos/partidos', [
            'title'    => 'Partidos · ' . $torneo['nombre'],
            'torneo'   => $torneo,
            'porRonda' => $porRonda,
            'progreso' => $this->partidos->progresoLiga((int) $torneo['id']),
        ]);
    }

    /** Formulario de carga de marcador set por set. */
    public function marcadorForm(array $params): string
    {
        if (($guard = $this->requireAnotar()) !== null) {
            return $guard;
        }
        [$torneo, $partido, $error] = $this->cargarPartido($params);
        if ($error !== null) {
            return $error;
        }
        if (($bloqueo = $this->rivalesDefinidos($torneo, $partido)) !== null) {
            return $bloqueo;
        }

        $reglas = $this->reglas($torneo, $partido);
        return $this->vistaMarcador($torneo, $partido, $reglas, $partido['sets'], [], (string) $partido['tipo_resultado']);
    }

    /** Render del formulario de marcador (reutilizado en errores). */
    private function vistaMarcador(array $torneo, array $partido, ReglasMarcador $reglas, array $sets, array $errores, string $tipoActual): string
    {
        return $this->render('torneos/marcador', [
            'title'      => 'Cargar marcador',
            'torneo'     => $torneo,
            'partido'    => $partido,
            'reglas'     => $reglas,
            'sets'       => $sets,
            'errores'    => $errores,
            'tipos'      => TipoResultado::casos(),
            'tipoActual' => $tipoActual,
        ]);
    }

    /** Procesa y valida el marcador cargado. */
    public function guardarMarcador(array $params): string
    {
        if (($guard = $this->requireAnotar()) !== null) {
            return $guard;
        }
        [$torneo, $partido, $error] = $this->cargarPartido($params);
        if ($error !== null) {
            return $error;
        }
        if (($bloqueo = $this->rivalesDefinidos($torneo, $partido)) !== null) {
            return $bloqueo;
        }
        $back = $this->volverA($torneo, $partido);
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }

        $tipo = TipoResultado::tryFrom((string) Request::post('tipo_resultado', 'NORMAL')) ?? TipoResultado::NORMAL;
        $reglas = $this->reglas($torneo, $partido);
        $sets = $this->leerSets();
        $setsVista = array_map(
            static fn (array $s): array => ['numero_set' => 0, 'puntos_a' => $s[0], 'puntos_b' => $s[1]],
            $sets
        );

        $antes = [
            'sets'    => $partido['sets'],
            'ganador' => $partido['ganador_inscripcion_id'],
            'tipo'    => $partido['tipo_resultado'],
            'estado'  => $partido['estado'],
        ];

        if ($tipo === TipoResultado::NORMAL) {
            $resultado = $reglas->validarPartido($sets);
            if (!$resultado['valido']) {
                return $this->vistaMarcador($torneo, $partido, $reglas, $setsVista, $resultado['errores'], $tipo->value);
            }
            $ladoGanador = $resultado['ganador'];
            $setsGuardar = $sets;
        } else {
            // Casos especiales: hay que indicar el ganador a mano.
            $errores = [];
            $lado = strtoupper((string) Request::post('ganador', ''));
            if (!in_array($lado, ['A', 'B'], true)) {
                $errores[] = 'Indicá quién resultó ganador del partido.';
            }
            if ($tipo->permiteSets()) {
                foreach ($reglas->setsInvalidos($sets) as $n) {
                    $errores[] = "Set {$n} inválido: revisá puntos y diferencia.";
                }
            }
            if ($errores !== []) {
                return $this->vistaMarcador($torneo, $partido, $reglas, $setsVista, $errores, $tipo->value);
            }
            $ladoGanador = $lado;
            $setsGuardar = $tipo->permiteSets() ? $sets : [];
        }

        $ganadorInscripcionId = $ladoGanador === 'A'
            ? (int) $partido['inscripcion_a_id']
            : (int) $partido['inscripcion_b_id'];

        $this->partidos->guardarMarcador((int) $partido['id'], $setsGuardar, $ganadorInscripcionId, $tipo->value);

        // En el bracket, el ganador avanza a la siguiente ronda.
        if ($partido['fase'] === 'ELIMINATORIA' && $partido['partido_siguiente_id'] !== null) {
            $ranura = (int) $partido['orden_en_ronda'] % 2 === 0 ? 'a' : 'b';
            $this->partidos->avanzarGanador((int) $partido['partido_siguiente_id'], $ranura, $ganadorInscripcionId);
        }

        $this->auditoria->registrar(
            'partido',
            (int) $partido['id'],
            $antes['estado'] === 'FINALIZADO' ? 'EDITAR_MARCADOR' : 'CARGAR_MARCADOR',
            Auth::user()['id'] ?? null,
            $antes,
            ['sets' => $setsGuardar, 'ganador' => $ganadorInscripcionId, 'tipo' => $tipo->value, 'estado' => 'FINALIZADO'],
        );

        Session::flash('success', 'Marcador guardado.');
        return $this->redirect($back);
    }

    /** Tabla de posiciones única con desempates. */
    public function tabla(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }

        $jugadores = array_map(
            static fn (array $i): array => [
                'id'     => (int) $i['id'],
                'nombre' => $i['apellido'] . ', ' . $i['nombre'],
            ],
            $this->inscripciones->porTorneo((int) $torneo['id'])
        );

        $tabla = TablaPosiciones::calcular(
            $jugadores,
            $this->partidos->ligaAgregada((int) $torneo['id']),
            (int) $torneo['puntos_por_victoria']
        );

        return $this->render('torneos/tabla', [
            'title'    => 'Tabla · ' . $torneo['nombre'],
            'torneo'   => $torneo,
            'tabla'    => $tabla,
            'progreso' => $this->partidos->progresoLiga((int) $torneo['id']),
            'clasificados' => (int) $torneo['num_clasificados'],
        ]);
    }

    /** Exporta la tabla de posiciones a CSV. */
    public function tablaCsv(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }
        $id = (int) $torneo['id'];

        $jugadores = array_map(
            static fn (array $i): array => ['id' => (int) $i['id'], 'nombre' => $i['apellido'] . ', ' . $i['nombre']],
            $this->inscripciones->porTorneo($id)
        );
        $tabla = TablaPosiciones::calcular($jugadores, $this->partidos->ligaAgregada($id), (int) $torneo['puntos_por_victoria']);

        $filas = [['Posicion', 'Jugador', 'PJ', 'PG', 'PP', 'Puntos', 'Sets favor', 'Sets contra', 'Puntos favor', 'Puntos contra', 'Desempate']];
        foreach ($tabla as $f) {
            $filas[] = [$f['posicion'], $f['nombre'], $f['pj'], $f['pg'], $f['pp'], $f['puntos'], $f['sf'], $f['sc'], $f['pf'], $f['pc'], $f['desempate'] ?? ''];
        }

        return $this->descargarCsv('tabla_torneo_' . $id . '.csv', $filas);
    }

    /** Exporta el fixture (partidos de liga) a CSV. */
    public function partidosCsv(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }
        $id = (int) $torneo['id'];

        $filas = [['Jornada', 'Jugador A', 'Jugador B', 'Estado', 'Sets', 'Resultado']];
        foreach ($this->partidos->liga($id) as $p) {
            $sa = $sb = 0;
            $detalle = [];
            foreach ($p['sets'] as $s) {
                if ((int) $s['puntos_a'] > (int) $s['puntos_b']) { $sa++; } elseif ((int) $s['puntos_b'] > (int) $s['puntos_a']) { $sb++; }
                $detalle[] = $s['puntos_a'] . '-' . $s['puntos_b'];
            }
            $filas[] = [
                $p['ronda'],
                $p['a_label'] ?? 'BYE',
                $p['b_label'] ?? 'BYE',
                $p['estado'],
                $p['estado'] === 'FINALIZADO' ? "{$sa}-{$sb}" : '',
                implode(' / ', $detalle),
            ];
        }

        return $this->descargarCsv('fixture_torneo_' . $id . '.csv', $filas);
    }

    /**
     * @param list<array<int|string,scalar|null>> $filas
     */
    private function descargarCsv(string $nombre, array $filas): string
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        return to_csv($filas);
    }

    // ----------------------------------------------------------------

    /**
     * @param array<string,string> $params
     * @return array{0:?array<string,mixed>,1:?array<string,mixed>,2:?string}
     */
    private function cargarPartido(array $params): array
    {
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return [null, null, $this->notFound()];
        }
        $partido = $this->partidos->find((int) $params['partido']);
        if ($partido === null || (int) $partido['torneo_id'] !== (int) $torneo['id']) {
            return [null, null, $this->notFound()];
        }
        return [$torneo, $partido, null];
    }

    /**
     * Reglas según la fase del partido: la liga y el bracket pueden tener
     * formatos de sets distintos (override por fase).
     *
     * @param array<string,mixed> $torneo
     * @param array<string,mixed> $partido
     */
    private function reglas(array $torneo, array $partido): ReglasMarcador
    {
        $formato = $partido['fase'] === 'ELIMINATORIA'
            ? (int) $torneo['formato_sets_bracket']
            : (int) $torneo['formato_sets_liga'];

        return new ReglasMarcador(
            (int) $torneo['puntos_por_set'],
            (int) $torneo['diferencia_minima'],
            $formato,
        );
    }

    /** Lista a la que volver según la fase del partido. */
    private function volverA(array $torneo, array $partido): string
    {
        $seccion = $partido['fase'] === 'ELIMINATORIA' ? 'bracket' : 'partidos';
        return '/torneos/' . $torneo['id'] . '/' . $seccion;
    }

    /**
     * Bloquea la carga si el partido no tiene ambos jugadores definidos
     * (típico en el bracket cuando aún no se jugó la ronda anterior).
     *
     * @param array<string,mixed> $torneo
     * @param array<string,mixed> $partido
     */
    private function rivalesDefinidos(array $torneo, array $partido): ?string
    {
        if ($partido['inscripcion_a_id'] === null || $partido['inscripcion_b_id'] === null) {
            Session::flash('error', 'Este partido todavía no tiene ambos jugadores definidos.');
            return $this->redirect($this->volverA($torneo, $partido));
        }
        return null;
    }

    /** @return list<array{0:int,1:int}> */
    private function leerSets(): array
    {
        $a = $_POST['puntos_a'] ?? [];
        $b = $_POST['puntos_b'] ?? [];
        $a = is_array($a) ? array_values($a) : [];
        $b = is_array($b) ? array_values($b) : [];

        $sets = [];
        $total = max(count($a), count($b));
        for ($i = 0; $i < $total; $i++) {
            $va = trim((string) ($a[$i] ?? ''));
            $vb = trim((string) ($b[$i] ?? ''));
            if ($va === '' && $vb === '') {
                continue; // fila vacía → set no jugado
            }
            $sets[] = [(int) $va, (int) $vb];
        }
        return $sets;
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'Recurso no encontrado.']);
    }
}
