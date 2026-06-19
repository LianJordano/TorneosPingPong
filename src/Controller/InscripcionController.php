<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\Emparejamiento;
use PingPong\Domain\EstadoTorneo;
use PingPong\Domain\Modalidad;
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

        $modalidad = Modalidad::from($torneo['modalidad']);
        $emparejamiento = Emparejamiento::from($torneo['emparejamiento']);

        // En dobles con parejas MANUALES se inscriben dos personas a la vez.
        // En individual, o en dobles con sorteo ALEATORIO, se inscribe de a una
        // (y se permite inscribir varias de un saque para agilizar la carga).
        $esParejaManual = $modalidad->esDobles() && !$emparejamiento->esAleatorio();

        return $esParejaManual
            ? $this->inscribirPareja($torneoId, $back)
            : $this->inscribirIndividuales($torneoId, $back);
    }

    /** Inscribe una pareja manual (dos integrantes elegidos a mano). */
    private function inscribirPareja(int $torneoId, string $back): string
    {
        $persona = $this->personaValida(Request::postInt('persona_id'));
        if ($persona === null) {
            Session::flash('error', 'Seleccioná un jugador válido.');
            return $this->redirect($back);
        }

        $compañero = $this->personaValida(Request::postInt('persona_id_2'));
        if ($compañero === null) {
            Session::flash('error', 'En dobles tenés que elegir los DOS integrantes de la pareja.');
            return $this->redirect($back);
        }
        if ((int) $compañero['id'] === (int) $persona['id']) {
            Session::flash('error', 'Una pareja no puede ser la misma persona dos veces.');
            return $this->redirect($back);
        }
        if ($this->inscripciones->personaYaInscrita($torneoId, (int) $compañero['id'])) {
            Session::flash('error', $compañero['nombre'] . ' ' . $compañero['apellido'] . ' ya está inscrito/a.');
            return $this->redirect($back);
        }
        if ($this->inscripciones->personaYaInscrita($torneoId, (int) $persona['id'])) {
            Session::flash('error', $persona['nombre'] . ' ' . $persona['apellido'] . ' ya está inscrito/a.');
            return $this->redirect($back);
        }

        try {
            $this->inscripciones->inscribir($torneoId, (int) $persona['id'], (int) $compañero['id']);
            Session::flash('success', $persona['apellido'] . ' / ' . $compañero['apellido'] . ' inscritos/as.');
        } catch (\PDOException $e) {
            Session::flash('error', 'No se pudo inscribir: ya participa en el torneo.');
        }

        return $this->redirect($back);
    }

    /**
     * Inscribe uno o varios jugadores individuales en lote. Acepta tanto el
     * campo array `persona_ids[]` (selección múltiple) como el legacy
     * `persona_id` (un solo jugador). Omite en silencio los inválidos/inactivos
     * y reporta los que ya estaban inscritos.
     */
    private function inscribirIndividuales(int $torneoId, string $back): string
    {
        $ids = Request::postIntArray('persona_ids');
        if ($ids === [] && ($uno = Request::postInt('persona_id')) !== null) {
            $ids = [$uno];
        }
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            Session::flash('error', 'Seleccioná al menos un jugador.');
            return $this->redirect($back);
        }

        $inscritos = 0;
        $yaInscritos = [];
        foreach ($ids as $id) {
            $persona = $this->personaValida($id);
            if ($persona === null) {
                continue; // inexistente o inactivo: lo ignoramos
            }
            if ($this->inscripciones->personaYaInscrita($torneoId, $id)) {
                $yaInscritos[] = $persona['apellido'] . ', ' . $persona['nombre'];
                continue;
            }
            try {
                $this->inscripciones->inscribir($torneoId, $id);
                $inscritos++;
            } catch (\PDOException $e) {
                $yaInscritos[] = $persona['apellido'] . ', ' . $persona['nombre'];
            }
        }

        if ($inscritos > 0) {
            Session::flash('success', $inscritos === 1
                ? '1 jugador inscrito.'
                : $inscritos . ' jugadores inscritos.');
        }
        if ($yaInscritos !== []) {
            Session::flash('error', 'Ya estaban inscritos: ' . implode('; ', $yaInscritos) . '.');
        }
        if ($inscritos === 0 && $yaInscritos === []) {
            Session::flash('error', 'No se inscribió a nadie: la selección no era válida.');
        }

        return $this->redirect($back);
    }

    /** Sortea las inscripciones individuales en parejas (dobles ALEATORIO). */
    public function sortearParejas(array $params): string
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

        $modalidad = Modalidad::from($torneo['modalidad']);
        $emparejamiento = Emparejamiento::from($torneo['emparejamiento']);
        if (!$modalidad->esDobles() || !$emparejamiento->esAleatorio()) {
            Session::flash('error', 'El sorteo de parejas solo aplica a torneos de dobles con emparejamiento aleatorio.');
            return $this->redirect($back);
        }
        if (EstadoTorneo::from($torneo['estado']) !== EstadoTorneo::INSCRIPCION_CERRADA) {
            Session::flash('error', 'Cerrá la inscripción antes de sortear las parejas.');
            return $this->redirect($back);
        }

        $resultado = $this->inscripciones->sortearParejas($torneoId);
        if ($resultado['parejas'] === 0) {
            Session::flash('error', 'No hay inscriptos individuales para sortear.');
            return $this->redirect($back);
        }

        $msg = $resultado['parejas'] . ' pareja(s) sorteada(s).';
        if ($resultado['sobrante'] !== null) {
            $msg .= ' Quedó un jugador sin pareja (número impar): asignale un compañero o desinscribilo.';
        }
        Session::flash($resultado['sobrante'] !== null ? 'error' : 'success', $msg);
        return $this->redirect($back);
    }

    /** @return array<string,mixed>|null Persona activa o null si no existe / está inactiva. */
    private function personaValida(?int $personaId): ?array
    {
        if ($personaId === null) {
            return null;
        }
        $persona = $this->personas->find($personaId);
        return ($persona !== null && (int) $persona['activo'] === 1) ? $persona : null;
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
