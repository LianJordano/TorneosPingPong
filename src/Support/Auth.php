<?php

declare(strict_types=1);

namespace PingPong\Support;

use PingPong\Domain\Rol;
use PingPong\Repository\UsuarioRepository;

/**
 * Autenticación basada en sesión.
 */
final class Auth
{
    /** Intenta autenticar; en éxito guarda el usuario en sesión. */
    public static function attempt(string $email, string $password, UsuarioRepository $usuarios): bool
    {
        $usuario = $usuarios->findByEmail($email);

        if ($usuario === null || (int) $usuario['activo'] !== 1) {
            return false;
        }
        if (!password_verify($password, $usuario['password_hash'])) {
            return false;
        }

        // Anti session-fixation: nuevo ID de sesión al loguear.
        Session::regenerate();
        Session::set('user', [
            'id'     => (int) $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email'  => $usuario['email'],
            'rol'    => $usuario['rol'],
        ]);

        return true;
    }

    public static function check(): bool
    {
        return Session::get('user') !== null;
    }

    /** @return array{id:int,nombre:string,email:string,rol:string}|null */
    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function rol(): ?Rol
    {
        $user = self::user();
        return $user !== null ? Rol::from($user['rol']) : null;
    }

    public static function puedeGestionar(): bool
    {
        return self::rol()?->puedeGestionar() ?? false;
    }

    public static function puedeAnotar(): bool
    {
        return self::rol()?->puedeAnotar() ?? false;
    }

    public static function logout(): void
    {
        Session::forget('user');
        Session::regenerate();
    }
}
