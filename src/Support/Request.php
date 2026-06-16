<?php

declare(strict_types=1);

namespace PingPong\Support;

/**
 * Acceso a los datos de la petición HTTP.
 */
final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /** Valor de $_POST (string ya recortado) o el default. */
    public static function post(string $key, ?string $default = null): ?string
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) ? trim($value) : $default;
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? null;
        return is_string($value) ? trim($value) : $default;
    }

    /** Valor entero o null si no es un entero válido. */
    public static function postInt(string $key): ?int
    {
        $value = $_POST[$key] ?? null;
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }
}
