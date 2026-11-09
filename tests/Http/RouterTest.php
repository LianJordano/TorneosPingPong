<?php

declare(strict_types=1);

namespace PingPong\Tests\Http;

use PHPUnit\Framework\TestCase;
use PingPong\Http\Router;

final class RouterTest extends TestCase
{
    private function dispatch(Router $router, string $method, string $uri): string
    {
        ob_start();
        $router->dispatch($method, $uri);
        return (string) ob_get_clean();
    }

    public function test_unknown_route_without_fallback_keeps_plain_text_404(): void
    {
        $router = new Router();
        $router->get('/torneos', static fn (): string => 'lista');

        $this->assertSame('404 — Ruta no encontrada.', $this->dispatch($router, 'GET', '/nada'));
        $this->assertSame(404, http_response_code());
    }

    public function test_unknown_route_uses_fallback_with_status(): void
    {
        $router = new Router();
        $router->get('/torneos', static fn (): string => 'lista');
        $router->fallback(static fn (int $status): string => "pagina-error-{$status}");

        $this->assertSame('pagina-error-404', $this->dispatch($router, 'GET', '/nada'));
        $this->assertSame(404, http_response_code());
    }

    public function test_wrong_method_uses_fallback_with_405(): void
    {
        $router = new Router();
        $router->post('/torneos', static fn (): string => 'creado');
        $router->fallback(static fn (int $status): string => "pagina-error-{$status}");

        $this->assertSame('pagina-error-405', $this->dispatch($router, 'GET', '/torneos'));
        $this->assertSame(405, http_response_code());
    }

    public function test_matching_route_ignores_fallback(): void
    {
        $router = new Router();
        $router->get('/torneos/{id}', static fn (array $p): string => 'torneo ' . $p['id']);
        $router->fallback(static fn (int $status): string => 'error');

        $this->assertSame('torneo 5', $this->dispatch($router, 'GET', '/torneos/5'));
    }
}
