<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;

/**
 * Renders the designed error page for routes the router cannot resolve.
 */
final class ErrorController extends Controller
{
    public function show(int $status): string
    {
        if ($status === 405) {
            return $this->render('error', [
                'title'   => 'Acción no disponible',
                'mensaje' => 'Esa acción no se puede realizar desde esta dirección.',
            ]);
        }

        return $this->render('error', [
            'title'   => 'No encontrado',
            'mensaje' => 'La página que buscás no existe o cambió de dirección.',
        ]);
    }
}
