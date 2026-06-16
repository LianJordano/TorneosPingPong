<?php

declare(strict_types=1);

namespace PingPong\Support;

use RuntimeException;

/**
 * Renderizador de plantillas PHP simples.
 * Renderiza una vista de contenido y la envuelve en un layout.
 */
final class View
{
    public function __construct(private string $viewsPath)
    {
    }

    /**
     * Renderiza una plantilla y devuelve el HTML como cadena.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->viewsPath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vista no encontrada: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /**
     * Renderiza una vista de contenido y la inserta dentro de un layout.
     *
     * @param array<string,mixed> $data
     */
    public function page(string $template, array $data = [], string $layout = 'layout'): string
    {
        $content = $this->render($template, $data);
        return $this->render($layout, array_merge($data, ['content' => $content]));
    }
}
