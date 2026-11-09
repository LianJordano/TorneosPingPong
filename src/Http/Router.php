<?php

declare(strict_types=1);

namespace PingPong\Http;

/**
 * Router con soporte de parámetros dinámicos: '/torneos/{id}/editar'.
 * Los handlers reciben un array asociativo de parámetros y devuelven el HTML
 * (o cadena vacía si ya emitieron una redirección).
 */
final class Router
{
    /** @var list<array{method:string,regex:string,params:list<string>,handler:callable}> */
    private array $routes = [];

    /** @var (callable(int):string)|null Renders 404/405 pages; plain text when not set. */
    private $fallback = null;

    /** @param callable(int):string $handler receives the HTTP status (404 or 405) */
    public function fallback(callable $handler): void
    {
        $this->fallback = $handler;
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $path = '/' . trim($path, '/');
        $params = [];

        $pattern = preg_replace_callback(
            '#\{(\w+)\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $pattern . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim((string) (parse_url($uri, PHP_URL_PATH) ?: '/'), '/');
        $method = strtoupper($method);
        $pathMatchedOtherMethod = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $pathMatchedOtherMethod = true;
                continue;
            }

            array_shift($matches);
            $values = array_map('urldecode', $matches);
            /** @var array<string,string> $params */
            $params = $route['params'] === [] ? [] : array_combine($route['params'], $values);

            echo ($route['handler'])($params);
            return;
        }

        $status = $pathMatchedOtherMethod ? 405 : 404;
        http_response_code($status);

        if ($this->fallback !== null) {
            echo ($this->fallback)($status);
            return;
        }

        echo $pathMatchedOtherMethod
            ? '405 — Método no permitido.'
            : '404 — Ruta no encontrada.';
    }
}
