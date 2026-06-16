<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\EstadoTorneo;
use PingPong\Http\Controller;
use PingPong\Repository\InscripcionRepository;
use PingPong\Repository\PersonaRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\View;

final class InscripcionController extends Controller
{
    public function __construct(
        View $view,
        private TorneoRepository $torneos,
        private InscripcionRepository $inscripciones,
        private PersonaRepository $personas,
    ) {
        parent::__construct($view);
    }

    /** @param array<string,string> $params */
    public function inscribir(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $torneoId = (int) $params['id'];
        $torneo = $this->torneos->find($torneoId);
        if ($torneo === null) {
            return $this->notFound();
        }
        $back = '/torneos/' . $torneoId;
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }
        if (!EstadoTorneo::from($torneo['estado'])->permiteInscripciones()) {
            Session::flash('error', 'Las inscripciones no están abiertas para este torneo.');
            return $this->redirect($back);
        }

        $personaId = Request::postInt('persona_id');
        $persona = $personaId !== null ? $this->personas->find($personaId) : null;
        if ($persona === null || (int) $persona['activo'] !== 1) {
            Session::flash('error', 'Seleccioná un jugador válido.');
            return $this->redirect($back);
        }

        // Las personas ya inscritas no aparecen en el desplegable; la unicidad
        // también la garantiza la restricción UNIQUE de la base.
        try {
            $this->inscripciones->inscribir($torneoId, $personaId);
            Session::flash('success', $persona['nombre'] . ' ' . $persona['apellido'] . ' inscrito/a.');
        } catch (\PDOException $e) {
            Session::flash('error', 'Ese jugador ya está inscrito en el torneo.');
        }

        return $this->redirect($back);
    }

    /** @param array<string,string> $params */
    public function desinscribir(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $torneoId = (int) $params['id'];
        $torneo = $this->torneos->find($torneoId);
        if ($torneo === null) {
            return $this->notFound();
        }
        $back = '/torneos/' . $torneoId;
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }
        if (!EstadoTorneo::from($torneo['estado'])->permiteInscripciones()) {
            Session::flash('error', 'No se puede desinscribir con las inscripciones cerradas.');
            return $this->redirect($back);
        }

        $this->inscripciones->desinscribir($torneoId, (int) $params['inscripcion']);
        Session::flash('success', 'Jugador desinscrito.');
        return $this->redirect($back);
    }

    /** @param array<string,string> $params */
    public function togglePago(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $torneoId = (int) $params['id'];
        $back = '/torneos/' . $torneoId;
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }
        $this->inscripciones->togglePago($torneoId, (int) $params['inscripcion']);
        return $this->redirect($back);
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'El torneo no existe.']);
    }
}
