<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\BracketEliminacion;
use PingPong\Domain\EstadoTorneo;
use PingPong\Domain\TablaPosiciones;
use PingPong\Http\Controller;
use PingPong\Repository\AuditoriaRepository;
use PingPong\Repository\InscripcionRepository;
use PingPong\Repository\PartidoRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\Auth;
use PingPong\Support\Session;
use PingPong\Support\View;

final class EliminatoriaController extends Controller
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

    /** Promueve los N mejores de la tabla y genera el cuadro de eliminación. */
    public function promover(array $params): string
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

        if ($torneo['estado'] !== EstadoTorneo::FASE_LIGA->value) {
            Session::flash('error', 'El bracket se genera durante la fase de liga.');
            return $this->redirect($back);
        }
        if ($this->partidos->existeBracket($id)) {
            Session::flash('error', 'El bracket de este torneo ya fue generado.');
            return $this->redirect($back);
        }

        $progreso = $this->partidos->progresoLiga($id);
        if ($progreso['total'] === 0 || $progreso['finalizados'] !== $progreso['total']) {
            Session::flash('error', 'Faltan cargar partidos de la liga: la tabla todavía no es definitiva.');
            return $this->redirect($back);
        }

        $jugadores = array_map(
            static fn (array $i): array => ['id' => (int) $i['id'], 'nombre' => $i['label']],
            $this->inscripciones->porTorneo($id)
        );
        $tabla = TablaPosiciones::calcular($jugadores, $this->partidos->ligaAgregada($id), (int) $torneo['puntos_por_victoria']);

        $cupos = min((int) $torneo['num_clasificados'], count($tabla));
        if ($cupos < 2) {
            Session::flash('error', 'Se necesitan al menos 2 clasificados para el bracket.');
            return $this->redirect($back);
        }

        $clasificados = array_map(
            static fn (array $fila): int => (int) $fila['insc_id'],
            array_slice($tabla, 0, $cupos)
        );

        $bracket = BracketEliminacion::generar($clasificados);
        $this->partidos->crearBracket($id, $bracket);
        $this->torneos->cambiarEstado($id, EstadoTorneo::FASE_ELIMINATORIA->value);

        $this->auditoria->registrar('torneo', $id, 'GENERAR_BRACKET', Auth::user()['id'] ?? null, null, [
            'clasificados' => $cupos,
            'tamano'       => $bracket['tamano'],
        ]);

        Session::flash('success', "Bracket generado con {$cupos} clasificados. ¡Arrancó la eliminatoria!");
        return $this->redirect($back . '/bracket');
    }

    /** Muestra el cuadro de eliminación. */
    public function bracket(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }

        $partidos = $this->partidos->bracket((int) $torneo['id']);
        $totalRondas = 0;
        foreach ($partidos as $p) {
            $totalRondas = max($totalRondas, (int) $p['ronda']);
        }

        $porRonda = [];
        foreach ($partidos as $p) {
            $porRonda[(int) $p['ronda']][] = $p;
        }

        // Campeón y subcampeón: del partido de la ronda final.
        $campeon = null;
        $subcampeon = null;
        if ($totalRondas > 0) {
            foreach ($porRonda[$totalRondas] as $final) {
                if ($final['estado'] === 'FINALIZADO' && $final['ganador_inscripcion_id'] !== null) {
                    $ganoA = (int) $final['ganador_inscripcion_id'] === (int) $final['inscripcion_a_id'];
                    $campeon = $ganoA ? $final['a_label'] : $final['b_label'];
                    $subcampeon = $ganoA ? $final['b_label'] : $final['a_label'];
                }
            }
        }

        return $this->render('torneos/bracket', [
            'title'       => 'Bracket · ' . $torneo['nombre'],
            'torneo'      => $torneo,
            'porRonda'    => $porRonda,
            'totalRondas' => $totalRondas,
            'campeon'     => $campeon,
            'subcampeon'  => $subcampeon,
        ]);
    }

    /** Cierra el torneo cuando la final está definida. */
    public function finalizar(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $id = (int) $params['id'];
        $torneo = $this->torneos->find($id);
        if ($torneo === null) {
            return $this->notFound();
        }
        $back = '/torneos/' . $id . '/bracket';
        if (!$this->csrfOk()) {
            return $this->csrfFail($back);
        }
        if ($torneo['estado'] !== EstadoTorneo::FASE_ELIMINATORIA->value) {
            Session::flash('error', 'El torneo no está en fase eliminatoria.');
            return $this->redirect($back);
        }

        $partidos = $this->partidos->bracket($id);
        $totalRondas = 0;
        foreach ($partidos as $p) {
            $totalRondas = max($totalRondas, (int) $p['ronda']);
        }
        $finalDefinida = false;
        foreach ($partidos as $p) {
            if ((int) $p['ronda'] === $totalRondas && $p['estado'] === 'FINALIZADO' && $p['ganador_inscripcion_id'] !== null) {
                $finalDefinida = true;
            }
        }
        if (!$finalDefinida) {
            Session::flash('error', 'Todavía no se jugó la final.');
            return $this->redirect($back);
        }

        $this->torneos->cambiarEstado($id, EstadoTorneo::FINALIZADO->value);
        $this->auditoria->registrar(
            'torneo',
            $id,
            'CAMBIO_ESTADO',
            Auth::user()['id'] ?? null,
            ['estado' => EstadoTorneo::FASE_ELIMINATORIA->value],
            ['estado' => EstadoTorneo::FINALIZADO->value],
        );
        Session::flash('success', '🏆 ¡Torneo finalizado! Ya tenés tu campeón.');
        return $this->redirect($back);
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'El torneo no existe.']);
    }
}
