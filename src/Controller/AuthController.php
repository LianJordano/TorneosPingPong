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

final class AuthController extends Controller
{
    public function __construct(View $view, private UsuarioRepository $usuarios)
    {
        parent::__construct($view);
    }

    public function showLogin(): string
    {
        if (Auth::check()) {
            return $this->redirect('/');
        }
        return $this->render('auth/login', ['title' => 'Ingresar']);
    }

    public function login(): string
    {
        if (!$this->csrfOk()) {
            return $this->csrfFail('/login');
        }

        $email = (string) Request::post('email', '');
        $password = (string) ($_POST['password'] ?? '');

        $validator = (new Validator(['email' => $email, 'password' => $password]))
            ->required('email', 'Correo')
            ->email('email', 'Correo')
            ->required('password', 'Contraseña');

        if ($validator->fails()) {
            return $this->render('auth/login', [
                'title'  => 'Ingresar',
                'errors' => $validator->errors(),
                'old'    => ['email' => $email],
            ]);
        }

        if (!Auth::attempt($email, $password, $this->usuarios)) {
            return $this->render('auth/login', [
                'title'  => 'Ingresar',
                'errors' => ['email' => 'Credenciales inválidas.'],
                'old'    => ['email' => $email],
            ]);
        }

        Session::flash('success', '¡Bienvenido/a, ' . (Auth::user()['nombre'] ?? '') . '!');
        return $this->redirect('/');
    }

    public function logout(): string
    {
        if ($this->csrfOk()) {
            Auth::logout();
            Session::flash('success', 'Cerraste sesión.');
        }
        return $this->redirect('/login');
    }
}
