<?php

declare(strict_types=1);

namespace PingPong\Support;

/**
 * Protección CSRF para formularios.
 * Requiere que la sesión esté iniciada (session_start()).
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Devuelve el input oculto listo para insertar en un <form>. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    /** Verifica un token recibido en comparación de tiempo constante. */
    public static function check(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION[self::KEY])
            && hash_equals($_SESSION[self::KEY], $token);
    }
}
