<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\EstadoTorneo;
use PingPong\Http\Controller;
use PingPong\Repository\PersonaRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\View;

final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        private TorneoRepository $torneos,
        private PersonaRepository $personas,
    ) {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }

        $torneos = $this->torneos->all();
        $activos = array_filter(
            $torneos,
            static fn (array $t): bool => !in_array($t['estado'], ['FINALIZADO', 'CANCELADO'], true)
        );

        return $this->render('dashboard', [
            'title'          => 'Panel',
            'totalTorneos'   => count($torneos),
            'torneosActivos' => count($activos),
            'totalPersonas'  => count($this->personas->all()),
            'ultimos'        => array_slice($torneos, 0, 5),
            'EstadoTorneo'   => EstadoTorneo::class,
        ]);
    }
}
