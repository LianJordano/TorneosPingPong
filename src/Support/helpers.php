<?php

declare(strict_types=1);

use PingPong\Support\Csrf;

if (!function_exists('e')) {
    /**
     * Escapa una cadena para salida HTML segura (anti-XSS).
     * Usar SIEMPRE al imprimir datos del usuario en las vistas.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    /** Campo oculto con el token CSRF, listo para insertar en un <form>. */
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('to_csv')) {
    /**
     * Convierte filas en una cadena CSV (con BOM UTF-8 para Excel).
     *
     * @param list<array<int|string,scalar|null>> $rows
     */
    function to_csv(array $rows): string
    {
        $buffer = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $csv = (string) stream_get_contents($buffer);
        fclose($buffer);
        return "\xEF\xBB\xBF" . $csv; // BOM para que Excel respete UTF-8
    }
}

if (!function_exists('old')) {
    /**
     * Valor previo de un formulario (tras un error de validación).
     *
     * @param array<string,mixed> $old
     */
    function old(array $old, string $key, string $default = ''): string
    {
        return e((string) ($old[$key] ?? $default));
    }
}
