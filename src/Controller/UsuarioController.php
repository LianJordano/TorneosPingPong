<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\Rol;
use PingPong\Http\Controller;
use PingPong\Repository\UsuarioRepository;
use PingPong\Support\Auth;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\View;

final class UsuarioController extends Controller
{
    public function __construct(View $view, private UsuarioRepository $usuarios)
    {
        parent::__construct($view);
    }

    /** Listado de usuarios con asignación de roles + glosario. */
    public function index(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }

        return $this->render('configuracion/usuarios', [
            'title'    => 'Usuarios y roles',
            'usuarios' => $this->usuarios->all(),
            'roles'    => Rol::cases(),
            'usuarioActualId' => Auth::user()['id'] ?? 0,
        ]);
    }

    /** @param array<string,string> $params */
    public function actualizarRol(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/configuracion/usuarios');
        }

        $id = (int) $params['id'];
        $usuario = $this->usuarios->find($id);
        if ($usuario === null) {
            Session::flash('error', 'El usuario no existe.');
            return $this->redirect('/configuracion/usuarios');
        }

        $rolNuevo = Rol::tryFrom((string) Request::post('rol', ''));
        if ($rolNuevo === null) {
            Session::flash('error', 'El rol seleccionado no es válido.');
            return $this->redirect('/configuracion/usuarios');
        }

        // Guarda anti-lockout: no dejar el sistema sin ningún administrador activo.
        $eraAdminActivo = $usuario['rol'] === Rol::ADMIN->value && (int) $usuario['activo'] === 1;
        if ($eraAdminActivo && $rolNuevo !== Rol::ADMIN && $this->usuarios->contarAdminsActivos() <= 1) {
            Session::flash('error', 'No podés quitar el rol de Administrador al único admin activo. Asigná ADMIN a otro usuario primero.');
            return $this->redirect('/configuracion/usuarios');
        }

        $this->usuarios->actualizarRol($id, $rolNuevo->value);

        $aviso = (int) ($usuario['id']) === (int) (Auth::user()['id'] ?? 0)
            ? ' Cambiaste tu propio rol: cerrá sesión y volvé a entrar para que tome efecto.'
            : '';
        Session::flash('success', 'Rol de ' . $usuario['nombre'] . ' actualizado a ' . $rolNuevo->label() . '.' . $aviso);

        return $this->redirect('/configuracion/usuarios');
    }
}
