<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Repository\AreaRepository;
use PingPong\Repository\NivelRepository;
use PingPong\Support\View;

final class ConfiguracionController extends Controller
{
    public function __construct(
        View $view,
        private AreaRepository $areas,
        private NivelRepository $niveles,
    ) {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }

        return $this->render('configuracion/index', [
            'title' => 'Configuración',
            'totalAreas' => count($this->areas->all()),
            'totalNiveles' => count($this->niveles->all()),
        ]);
    }
}
