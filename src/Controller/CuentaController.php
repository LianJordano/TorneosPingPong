<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Repository\UsuarioRepository;
use PingPong\Support\Auth;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\Validator;
use PingPong\Support\View;

/**
 * Autogestión de la cuenta del usuario logueado. Disponible para cualquier rol
 * (incluido ESPECTADOR), por eso usa requireAuth y no requireGestion.
 */
final class CuentaController extends Controller
{
    private const MIN_PASSWORD = 8;

    public function __construct(View $view, private UsuarioRepository $usuarios)
    {
        parent::__construct($view);
    }

    public function editarPassword(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        return $this->render('cuenta/password', ['title' => 'Cambiar contraseña']);
    }

    public function actualizarPassword(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/cuenta/password');
        }

        $actual = (string) ($_POST['password_actual'] ?? '');
        $nueva = (string) ($_POST['password_nueva'] ?? '');
        $confirmacion = (string) ($_POST['password_confirmacion'] ?? '');

        $validator = (new Validator([
            'password_actual'       => $actual,
            'password_nueva'        => $nueva,
            'password_confirmacion' => $confirmacion,
        ]))
            ->required('password_actual', 'Contraseña actual')
            ->required('password_nueva', 'Nueva contraseña')
            ->minLen('password_nueva', self::MIN_PASSWORD, 'Nueva contraseña')
            ->required('password_confirmacion', 'Confirmación');

        if ($nueva !== '' && $confirmacion !== '' && $nueva !== $confirmacion) {
            $validator->addError('password_confirmacion', 'La confirmación no coincide con la nueva contraseña.');
        }

        // El usuario de sesión es ligero; recargamos el registro completo para
        // verificar el hash de la contraseña actual.
        $usuario = $this->usuarios->find((int) (Auth::user()['id'] ?? 0));
        if ($usuario === null) {
            Session::flash('error', 'Tu sesión no es válida. Volvé a ingresar.');
            return $this->redirect('/login');
        }

        if ($actual !== '' && !password_verify($actual, (string) $usuario['password_hash'])) {
            $validator->addError('password_actual', 'La contraseña actual no es correcta.');
        }

        if ($validator->fails()) {
            return $this->render('cuenta/password', [
                'title'  => 'Cambiar contraseña',
                'errors' => $validator->errors(),
            ]);
        }

        $this->usuarios->actualizarPassword(
            (int) $usuario['id'],
            password_hash($nueva, PASSWORD_DEFAULT),
        );

        Session::flash('success', 'Tu contraseña se actualizó correctamente.');
        return $this->redirect('/');
    }
}
