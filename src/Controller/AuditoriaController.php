<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Repository\AuditoriaRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\View;

final class AuditoriaController extends Controller
{
    public function __construct(
        View $view,
        private TorneoRepository $torneos,
        private AuditoriaRepository $auditoria,
    ) {
        parent::__construct($view);
    }

    /** Bitácora de cambios de un torneo (solo gestión). */
    public function ver(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            http_response_code(404);
            return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'El torneo no existe.']);
        }

        return $this->render('torneos/auditoria', [
            'title'    => 'Auditoría · ' . $torneo['nombre'],
            'torneo'   => $torneo,
            'registros' => $this->auditoria->porTorneo((int) $torneo['id']),
        ]);
    }
}
