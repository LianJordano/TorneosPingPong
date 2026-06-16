<?php

declare(strict_types=1);

namespace PingPong\Http;

use PingPong\Support\Auth;
use PingPong\Support\Csrf;
use PingPong\Support\Env;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\View;

/**
 * Controlador base: render con layout, redirecciones y guards de permisos.
 */
abstract class Controller
{
    public function __construct(protected View $view)
    {
    }

    /** @param array<string,mixed> $data */
    protected function render(string $template, array $data = []): string
    {
        $base = [
            'authUser'   => Auth::user(),
            'rol'        => Auth::rol(),
            'flashes'    => Session::takeFlashes(),
            'appName'    => Env::get('APP_NAME', 'Torneos Ping Pong'),
            'phpVersion' => PHP_VERSION,
            'errors'     => [],
            'old'        => [],
            'title'      => 'Inicio',
        ];
        return $this->view->page($template, array_merge($base, $data));
    }

    protected function redirect(string $to): string
    {
        header('Location: ' . $to);
        return '';
    }

    /** Exige sesión iniciada. Devuelve una redirección si falla, o null si pasa. */
    protected function requireAuth(): ?string
    {
        if (!Auth::check()) {
            Session::flash('error', 'Necesitás iniciar sesión.');
            return $this->redirect('/login');
        }
        return null;
    }

    /** Exige rol con permiso de gestión (ADMIN). */
    protected function requireGestion(): ?string
    {
        $guard = $this->requireAuth();
        if ($guard !== null) {
            return $guard;
        }
        if (!Auth::puedeGestionar()) {
            http_response_code(403);
            Session::flash('error', 'No tenés permisos para realizar esa acción.');
            return $this->redirect('/');
        }
        return null;
    }

    /** Exige rol con permiso para anotar marcadores (ADMIN o ANOTADOR). */
    protected function requireAnotar(): ?string
    {
        $guard = $this->requireAuth();
        if ($guard !== null) {
            return $guard;
        }
        if (!Auth::puedeAnotar()) {
            http_response_code(403);
            Session::flash('error', 'No tenés permisos para cargar marcadores.');
            return $this->redirect('/');
        }
        return null;
    }

    /** Valida el token CSRF del formulario. */
    protected function csrfOk(): bool
    {
        return Csrf::check(Request::post('_csrf'));
    }

    /** Redirección estándar cuando el CSRF falla. */
    protected function csrfFail(string $back): string
    {
        Session::flash('error', 'Sesión de formulario inválida o expirada. Probá de nuevo.');
        return $this->redirect($back);
    }
}
