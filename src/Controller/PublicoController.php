<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\EstadoTorneo;
use PingPong\Domain\TablaPosiciones;
use PingPong\Http\Controller;
use PingPong\Repository\InscripcionRepository;
use PingPong\Repository\PartidoRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\View;

/**
 * Vista pública de solo lectura (sin autenticación), pensada para proyectar:
 * tabla de posiciones, bracket en vivo y próximos partidos.
 */
final class PublicoController extends Controller
{
    public function __construct(
        View $view,
        private TorneoRepository $torneos,
        private InscripcionRepository $inscripciones,
        private PartidoRepository $partidos,
    ) {
        parent::__construct($view);
    }

    public function ver(array $params): string
    {
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            http_response_code(404);
            return $this->view->page('publico/no-disponible', [
                'title' => 'No disponible', 'flashes' => [], 'authUser' => null, 'rol' => null,
            ], 'layout-public');
        }

        $id = (int) $torneo['id'];
        $estado = EstadoTorneo::from($torneo['estado']);

        // Tabla (si la liga ya arrancó)
        $tabla = [];
        if (in_array($estado, [EstadoTorneo::FASE_LIGA, EstadoTorneo::FASE_ELIMINATORIA, EstadoTorneo::FINALIZADO], true)) {
            $jugadores = array_map(
                static fn (array $i): array => ['id' => (int) $i['id'], 'nombre' => $i['label']],
                $this->inscripciones->porTorneo($id)
            );
            $tabla = TablaPosiciones::calcular($jugadores, $this->partidos->ligaAgregada($id), (int) $torneo['puntos_por_victoria']);
        }

        // Bracket
        $bracketRaw = $this->partidos->bracket($id);
        $totalRondas = 0;
        $porRonda = [];
        $campeon = null;
        foreach ($bracketRaw as $p) {
            $totalRondas = max($totalRondas, (int) $p['ronda']);
            $porRonda[(int) $p['ronda']][] = $p;
        }
        if ($totalRondas > 0) {
            foreach ($porRonda[$totalRondas] as $final) {
                if ($final['estado'] === 'FINALIZADO' && $final['ganador_inscripcion_id'] !== null) {
                    $campeon = (int) $final['ganador_inscripcion_id'] === (int) $final['inscripcion_a_id']
                        ? $final['a_label'] : $final['b_label'];
                }
            }
        }

        // Próximos partidos (pendientes con ambos jugadores definidos).
        // La vista muestra una cola corta para proyección, pero contamos todos
        // los pendientes porque los marcadores pueden cargarse fuera de orden.
        $proximos = [];
        $totalPendientes = 0;
        foreach (array_merge($this->partidos->liga($id), $bracketRaw) as $p) {
            if ($p['estado'] === 'PENDIENTE' && $p['inscripcion_a_id'] !== null && $p['inscripcion_b_id'] !== null) {
                $totalPendientes++;
                if (count($proximos) < 4) {
                    $proximos[] = $p;
                }
            }
        }

        return $this->view->page('publico/ver', [
            'title'       => $torneo['nombre'],
            'appName'     => $torneo['nombre'],
            'phpVersion'  => PHP_VERSION,
            'authUser'    => null,
            'rol'         => null,
            'flashes'     => [],
            'torneo'      => $torneo,
            'estado'      => $estado,
            'tabla'       => $tabla,
            'porRonda'    => $porRonda,
            'totalRondas' => $totalRondas,
            'campeon'     => $campeon,
            'proximos'    => $proximos,
            'totalPendientes' => $totalPendientes,
            'clasificados' => (int) $torneo['num_clasificados'],
        ], 'layout-public');
    }
}
