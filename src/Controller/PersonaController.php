<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Repository\AreaRepository;
use PingPong\Repository\NivelRepository;
use PingPong\Repository\PersonaRepository;
use PingPong\Service\RankingHistoricoService;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\Validator;
use PingPong\Support\View;

final class PersonaController extends Controller
{
    private const POR_PAGINA = 10;

    public function __construct(
        View $view,
        private PersonaRepository $personas,
        private AreaRepository $areas,
        private NivelRepository $niveles,
        private RankingHistoricoService $ranking,
    ) {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $incluirInactivas = Request::query('inactivas') === '1';
        $q = (string) Request::query('q', '');
        $pagina = max(1, (int) Request::query('pagina', '1'));

        // Ordenamiento validado contra la whitelist del repositorio.
        $orden = (string) Request::query('orden', 'nombre');
        if (!array_key_exists($orden, PersonaRepository::ORDEN_COLUMNAS)) {
            $orden = 'nombre';
        }
        $dir = strtolower((string) Request::query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $resultado = $this->personas->paginar($q, $incluirInactivas, $pagina, self::POR_PAGINA, $orden, $dir);
        $total = $resultado['total'];
        $totalPaginas = (int) max(1, ceil($total / self::POR_PAGINA));

        if ($pagina > $totalPaginas) {
            $pagina = $totalPaginas;
            $resultado = $this->personas->paginar($q, $incluirInactivas, $pagina, self::POR_PAGINA, $orden, $dir);
        }

        $desde = $total === 0 ? 0 : (($pagina - 1) * self::POR_PAGINA) + 1;
        $hasta = min($pagina * self::POR_PAGINA, $total);

        return $this->render('personas/index', [
            'title'            => 'Jugadores',
            'personas'         => $resultado['items'],
            'incluirInactivas' => $incluirInactivas,
            'q'                => trim($q),
            'total'            => $total,
            'pagina'           => $pagina,
            'totalPaginas'     => $totalPaginas,
            'desde'            => $desde,
            'hasta'            => $hasta,
            'orden'            => $orden,
            'dir'              => $dir,
        ]);
    }

    public function create(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        return $this->renderForm('Nuevo jugador', null, '/personas');
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }

        $perfil = $this->ranking->perfil((int) $params['id']);
        if ($perfil === null) {
            return $this->notFound();
        }

        return $this->render('personas/show', [
            'title' => 'Perfil · ' . $perfil['persona']['nombre_completo'],
            'perfil' => $perfil,
        ]);
    }

    public function store(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/personas/nueva');
        }

        $data = $this->input();
        $validator = $this->validate($data);
        if ($validator->fails()) {
            return $this->renderForm('Nuevo jugador', null, '/personas', $validator->errors(), $data);
        }

        $this->personas->create($data);
        Session::flash('success', 'Jugador creado correctamente.');
        return $this->redirect('/personas');
    }

    /** @param array<string,string> $params */
    public function edit(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $persona = $this->personas->find((int) $params['id']);
        if ($persona === null) {
            return $this->notFound();
        }
        return $this->renderForm('Editar jugador', $persona, '/personas/' . $persona['id']);
    }

    /** @param array<string,string> $params */
    public function update(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $id = (int) $params['id'];
        $persona = $this->personas->find($id);
        if ($persona === null) {
            return $this->notFound();
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/personas/' . $id . '/editar');
        }

        $data = $this->input();
        $validator = $this->validate($data, $id);
        if ($validator->fails()) {
            return $this->renderForm('Editar jugador', array_merge($persona, $data), '/personas/' . $id, $validator->errors(), $data);
        }

        $this->personas->update($id, $data);
        Session::flash('success', 'Jugador actualizado.');
        return $this->redirect('/personas');
    }

    /** @param array<string,string> $params */
    public function desactivar(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/personas');
        }
        $this->personas->desactivar((int) $params['id']);
        Session::flash('success', 'Jugador dado de baja.');
        return $this->redirect('/personas');
    }

    /** @param array<string,string> $params */
    public function activar(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/personas?inactivas=1');
        }
        $this->personas->activar((int) $params['id']);
        Session::flash('success', 'Jugador reactivado.');
        return $this->redirect('/personas?inactivas=1');
    }

    /**
     * @param array<string,mixed>|null $persona
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderForm(string $title, ?array $persona, string $action, array $errors = [], array $old = []): string
    {
        return $this->render('personas/form', [
            'title'   => $title,
            'persona' => $persona,
            'action'  => $action,
            'errors'  => $errors,
            'old'     => $old,
            'areas'   => $this->areas->all(),
            'niveles' => $this->niveles->all(),
        ]);
    }

    /** @return array<string,string> */
    private function input(): array
    {
        return [
            'nombre'                => (string) Request::post('nombre', ''),
            'apellido'              => (string) Request::post('apellido', ''),
            'identificador_interno' => (string) Request::post('identificador_interno', ''),
            'area_id'               => (string) Request::post('area_id', ''),
            'email'                 => (string) Request::post('email', ''),
            'telefono'              => (string) Request::post('telefono', ''),
            'nivel_id'              => (string) Request::post('nivel_id', ''),
        ];
    }

    /** @param array<string,string> $data */
    private function validate(array $data, ?int $exceptoId = null): Validator
    {
        $validator = (new Validator($data))
            ->required('nombre', 'Nombre')->maxLen('nombre', 80, 'Nombre')
            ->required('apellido', 'Apellido')->maxLen('apellido', 80, 'Apellido')
            ->email('email', 'Correo')
            ->maxLen('identificador_interno', 50, 'Identificador');

        $ident = trim($data['identificador_interno']);
        if ($ident !== '' && $this->personas->identificadorEnUso($ident, $exceptoId)) {
            $validator->addError('identificador_interno', 'Ese identificador ya está en uso.');
        }

        // Área y nivel son opcionales, pero si vienen deben existir en el catálogo.
        if (trim($data['area_id']) !== '' && !in_array((int) $data['area_id'], $this->areas->ids(), true)) {
            $validator->addError('area_id', 'El área seleccionada no es válida.');
        }
        if (trim($data['nivel_id']) !== '' && !in_array((int) $data['nivel_id'], $this->niveles->ids(), true)) {
            $validator->addError('nivel_id', 'El nivel seleccionado no es válido.');
        }

        return $validator;
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'El jugador no existe.']);
    }
}
