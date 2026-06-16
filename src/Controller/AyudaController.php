<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;

final class AyudaController extends Controller
{
    /** Glosario de términos del sistema (desempates, formato, fases). */
    public function glosario(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        return $this->render('glosario', ['title' => 'Glosario']);
    }
}
