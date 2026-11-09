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

if (!function_exists('icon')) {
    /**
     * Inline SVG icon from the sprite in views/partials/icons.php.
     * Decorative by default (aria-hidden); pass $label for a meaningful icon.
     */
    function icon(string $name, string $class = '', ?string $label = null): string
    {
        $classes = trim('icon ' . $class);
        $a11y = $label !== null && $label !== ''
            ? 'role="img" aria-label="' . e($label) . '"'
            : 'aria-hidden="true" focusable="false"';

        return '<svg class="' . e($classes) . '" ' . $a11y . '><use href="#i-' . e($name) . '"></use></svg>';
    }
}

if (!function_exists('asset')) {
    /** Public asset URL with a cache-busting version from its modification time. */
    function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '1';
        return e($path . '?v=' . $version);
    }
}

if (!function_exists('current_path')) {
    /** Path of the current request without query string, normalised (no trailing slash). */
    function current_path(): string
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = '/' . trim($path, '/');
        return $path;
    }
}

if (!function_exists('nav_current')) {
    /**
     * Returns ' aria-current="page"' when $href is the active section.
     * '/' matches only the home page; other hrefs match themselves and sub-paths.
     */
    function nav_current(string $href, ?string $path = null): string
    {
        $path ??= current_path();
        $href = '/' . trim($href, '/');

        $active = $href === '/'
            ? $path === '/'
            : ($path === $href || str_starts_with($path, $href . '/'));

        return $active ? ' aria-current="page"' : '';
    }
}

if (!function_exists('initials')) {
    /** Up to two uppercase initials for avatars ("Ana María Pérez" -> "AM"). */
    function initials(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $letters !== '' ? $letters : '?';
    }
}
