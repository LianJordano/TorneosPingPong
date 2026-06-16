<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Repository\CatalogoRepository;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\View;

/**
 * CRUD genérico de catálogos (áreas, niveles). Se instancia una vez por
 * catálogo, parametrizado con su repositorio, título y ruta base.
 */
final class CatalogoController extends Controller
{
    public function __construct(
        View $view,
        private CatalogoRepository $repo,
        private string $titulo,
        private string $singular,
        private string $ruta,
    ) {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        return $this->vista();
    }

    public function store(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail($this->ruta);
        }

        $nombre = trim((string) Request::post('nombre', ''));
        if ($nombre === '') {
            return $this->vista("El nombre del {$this->singular} es obligatorio.", $nombre);
        }
        if (mb_strlen($nombre) > 100) {
            return $this->vista('El nombre es demasiado largo.', $nombre);
        }
        if ($this->repo->nombreEnUso($nombre)) {
            return $this->vista("Ya existe un {$this->singular} con ese nombre.", $nombre);
        }

        $this->repo->create($nombre);
        Session::flash('success', ucfirst($this->singular) . ' agregado.');
        return $this->redirect($this->ruta);
    }

    /** @param array<string,string> $params */
    public function update(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail($this->ruta);
        }
        $id = (int) $params['id'];
        if ($this->repo->find($id) === null) {
            return $this->redirect($this->ruta);
        }

        $nombre = trim((string) Request::post('nombre', ''));
        if ($nombre === '') {
            return $this->vista("El nombre del {$this->singular} es obligatorio.", $nombre);
        }
        if ($this->repo->nombreEnUso($nombre, $id)) {
            return $this->vista("Ya existe un {$this->singular} con ese nombre.", $nombre);
        }

        $this->repo->update($id, $nombre);
        Session::flash('success', ucfirst($this->singular) . ' actualizado.');
        return $this->redirect($this->ruta);
    }

    /** @param array<string,string> $params */
    public function delete(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail($this->ruta);
        }
        // ON DELETE SET NULL: borrarlo solo deja a los jugadores sin asignar.
        $this->repo->delete((int) $params['id']);
        Session::flash('success', ucfirst($this->singular) . ' eliminado.');
        return $this->redirect($this->ruta);
    }

    private function vista(string $error = '', string $oldNombre = ''): string
    {
        return $this->render('catalogos/index', [
            'title'   => $this->titulo,
            'titulo'  => $this->titulo,
            'singular' => $this->singular,
            'ruta'    => $this->ruta,
            'items'   => $this->repo->allConUso(),
            'error'   => $error,
            'oldNombre' => $oldNombre,
        ]);
    }
}
