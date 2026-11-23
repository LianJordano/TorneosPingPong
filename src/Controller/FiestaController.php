<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Service\FiestaService;
use PingPong\Support\Auth;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\View;

/**
 * HTTP controller for the party tournament ("torneo fiesta").
 * Delegates orchestration, validation, and persistence to {@see FiestaService}.
 */
final class FiestaController extends Controller
{
    public function __construct(
        View $view,
        private FiestaService $service,
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
            'fiestas' => $this->service->listar(),
        ]);
    }

    public function create(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        return $this->render('fiesta/form', [
            'title'       => 'Nueva fiesta',
            'disponibles' => $this->service->jugadoresActivos(),
        ]);
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
        $personaIds = Request::postIntArray('persona_ids');
        if ($errors !== []) {
            return $this->render('fiesta/form', [
                'title'       => 'Nueva fiesta',
                'errors'      => $errors,
                'old'         => $data + [
                    'persona_ids'    => $personaIds,
                    'mejor_de_elim'  => Request::post('mejor_de_elim', ''),
                    'mejor_de_semis' => Request::post('mejor_de_semis', ''),
                    'mejor_de_final' => Request::post('mejor_de_final', ''),
                ],
                'disponibles' => $this->service->jugadoresActivos(),
            ]);
        }

        $id = $this->service->crearFiesta($data, $personaIds, Auth::user()['id'] ?? null);
        Session::flash('success', '¡Fiesta creada! Sumá a los participantes y girá la ruleta.');
        return $this->redirect('/fiesta/' . $id);
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $vm = $this->service->prepararVistaShow((int) $params['id']);
        if ($vm === null) {
            return $this->notFound();
        }
        return $this->render('fiesta/show', $vm);
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
        $this->service->actualizarConfig($fiesta, $data, Auth::user()['id'] ?? null);
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
        $personaIds = Request::postIntArray('persona_ids');
        if ($personaIds === []) {
            Session::flash('error', 'Elegí al menos un jugador.');
            return $this->redirect('/fiesta/' . $id . '#participantes');
        }

        try {
            $agregados = $this->service->agregarParticipantes($id, $personaIds, Auth::user()['id'] ?? null);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/fiesta/' . $id);
        }

        if ($agregados === 0) {
            Session::flash('error', 'Esos jugadores ya estaban anotados o no están activos.');
        } else {
            Session::flash('success', $agregados === 1 ? '1 jugador sumado.' : "{$agregados} jugadores sumados.");
        }
        return $this->redirect('/fiesta/' . $id . '#participantes');
    }

    /** @param array<string,string> $params */
    public function quitarParticipante(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'INSCRIPCION');
        if ($error !== null) {
            return $error;
        }
        $this->service->quitarParticipante((int) $fiesta['id'], (int) $params['participante'], Auth::user()['id'] ?? null);
        return $this->redirect('/fiesta/' . $fiesta['id'] . '#participantes');
    }

    /** @param array<string,string> $params */
    public function sortear(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'INSCRIPCION');
        if ($error !== null) {
            return $error;
        }
        $id = (int) $fiesta['id'];
        try {
            $this->service->sortear($fiesta, Auth::user()['id'] ?? null);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/fiesta/' . $id);
        }

        return $this->redirect('/fiesta/' . $id . '/ruleta');
    }

    /** @param array<string,string> $params */
    public function ruleta(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $fiesta = $this->service->buscar((int) $params['id']);
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
        try {
            $this->service->deshacerSorteo($id, Auth::user()['id'] ?? null);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/fiesta/' . $id);
        }
        Session::flash('success', 'Sorteo anulado. Podés ajustar y volver a girar la ruleta.');
        return $this->redirect('/fiesta/' . $id);
    }

    /** @param array<string,string> $params */
    public function guardarResultado(array $params): string
    {
        if (($guard = $this->requireAnotar()) !== null) {
            return $guard;
        }
        $fiesta = $this->service->buscar((int) $params['id']);
        if ($fiesta === null) {
            return $this->notFound();
        }
        $id = (int) $fiesta['id'];
        $partidoId = (int) $params['partido'];
        $back = '/fiesta/' . $id . '#partido-' . $partidoId;
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }

        $setsA = isset($_POST['sets_a']) && is_array($_POST['sets_a']) ? array_values($_POST['sets_a']) : null;
        $setsB = isset($_POST['sets_b']) && is_array($_POST['sets_b']) ? array_values($_POST['sets_b']) : null;

        try {
            $res = $this->service->guardarResultado(
                $id,
                $partidoId,
                Request::post('marcador_a'),
                Request::post('marcador_b'),
                Auth::puedeGestionar(),
                Auth::user()['id'] ?? null,
                $setsA,
                $setsB,
            );
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect($back);
        }

        if ($res['es_final']) {
            Session::flash('success', '🏆 ¡Tenemos campeón!');
            return $this->redirect('/fiesta/' . $id . '#campeon');
        }

        Session::flash('success', 'Resultado guardado.');
        return $this->redirect($back);
    }

    /** @param array<string,string> $params */
    public function pasarAEliminatoria(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, 'GRUPOS');
        if ($error !== null) {
            return $error;
        }
        $id = (int) $fiesta['id'];
        try {
            $clasificados = $this->service->pasarAEliminatoria($fiesta, Auth::user()['id'] ?? null);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/fiesta/' . $id);
        }
        Session::flash('success', "¡Arrancó la eliminatoria! {$clasificados} equipos clasificados.");
        return $this->redirect('/fiesta/' . $id . '#eliminatoria');
    }

    /** @param array<string,string> $params */
    public function delete(array $params): string
    {
        [$fiesta, $error] = $this->fiestaGestionable($params, null);
        if ($error !== null) {
            return $error;
        }
        $this->service->eliminar($fiesta, Auth::user()['id'] ?? null);
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
        $fiesta = $this->service->buscar((int) $params['id']);
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

    /** @return array{0:array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int,mejor_de:int,sets_rondas_json:?string},1:array<string,string>} */
    private function leerConfig(): array
    {
        return FiestaService::validarConfig([
            'nombre'                 => Request::post('nombre', ''),
            'modalidad'              => Request::post('modalidad', 'DOBLES'),
            'formato'                => Request::post('formato', 'ELIMINACION'),
            'num_grupos'             => Request::post('num_grupos', '2'),
            'clasificados_por_grupo' => Request::post('clasificados_por_grupo', '2'),
            'mejor_de'               => Request::post('mejor_de', '1'),
            'mejor_de_elim'          => Request::post('mejor_de_elim', ''),
            'mejor_de_semis'         => Request::post('mejor_de_semis', ''),
            'mejor_de_final'         => Request::post('mejor_de_final', ''),
        ]);
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'La fiesta no existe.']);
    }
}
