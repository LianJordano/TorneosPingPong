<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Domain\EstadoTorneo;
use PingPong\Http\Controller;
use PingPong\Repository\AuditoriaRepository;
use PingPong\Repository\InscripcionRepository;
use PingPong\Repository\TorneoRepository;
use PingPong\Support\Auth;
use PingPong\Support\Request;
use PingPong\Support\Session;
use PingPong\Support\Validator;
use PingPong\Support\View;

final class TorneoController extends Controller
{
    /**
     * Estados a los que se puede transicionar EN LA FASE 1.
     * El paso a FASE_LIGA (y siguientes) requiere generar el fixture/bracket,
     * que se implementa en las Fases 2 y 3.
     */
    private const ESTADOS_FASE1 = ['INSCRIPCION_ABIERTA', 'INSCRIPCION_CERRADA', 'CANCELADO'];

    private const FORMATOS = [1, 3, 5, 7];

    public function __construct(
        View $view,
        private TorneoRepository $torneos,
        private InscripcionRepository $inscripciones,
        private AuditoriaRepository $auditoria,
    ) {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $incluirArchivados = Request::query('archivados') === '1';

        return $this->render('torneos/index', [
            'title'             => 'Torneos',
            'torneos'           => $this->torneos->all($incluirArchivados),
            'incluirArchivados' => $incluirArchivados,
        ]);
    }

    public function create(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        return $this->render('torneos/form', [
            'title'   => 'Nuevo torneo',
            'torneo'  => null,
            'action'  => '/torneos',
            'formatos' => self::FORMATOS,
        ]);
    }

    public function store(): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/torneos/nuevo');
        }

        $data = $this->input();
        $validator = $this->validate($data);
        if ($validator->fails()) {
            return $this->render('torneos/form', [
                'title'    => 'Nuevo torneo',
                'torneo'   => null,
                'action'   => '/torneos',
                'formatos' => self::FORMATOS,
                'errors'   => $validator->errors(),
                'old'      => $data,
            ]);
        }

        $id = $this->torneos->create($data);
        Session::flash('success', 'Torneo creado.');
        return $this->redirect('/torneos/' . $id);
    }

    /** @param array<string,string> $params */
    public function show(array $params): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }

        $estado = EstadoTorneo::from($torneo['estado']);
        $transiciones = array_values(array_filter(
            $estado->siguientesPosibles(),
            static fn (EstadoTorneo $e): bool => in_array($e->value, self::ESTADOS_FASE1, true)
        ));

        return $this->render('torneos/show', [
            'title'              => $torneo['nombre'],
            'torneo'             => $torneo,
            'estado'             => $estado,
            'transiciones'       => $transiciones,
            'inscripciones'      => $this->inscripciones->porTorneo((int) $torneo['id']),
            'disponibles'        => $estado->permiteInscripciones()
                ? $this->inscripciones->personasDisponibles((int) $torneo['id'])
                : [],
        ]);
    }

    /** @param array<string,string> $params */
    public function edit(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $torneo = $this->torneos->find((int) $params['id']);
        if ($torneo === null) {
            return $this->notFound();
        }
        if (!EstadoTorneo::from($torneo['estado'])->permiteEditarConfig()) {
            Session::flash('error', 'No se puede editar la configuración en el estado actual del torneo.');
            return $this->redirect('/torneos/' . $torneo['id']);
        }

        return $this->render('torneos/form', [
            'title'    => 'Editar torneo',
            'torneo'   => $torneo,
            'action'   => '/torneos/' . $torneo['id'],
            'formatos' => self::FORMATOS,
        ]);
    }

    /** @param array<string,string> $params */
    public function update(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $id = (int) $params['id'];
        $torneo = $this->torneos->find($id);
        if ($torneo === null) {
            return $this->notFound();
        }
        if (!EstadoTorneo::from($torneo['estado'])->permiteEditarConfig()) {
            Session::flash('error', 'No se puede editar la configuración en el estado actual.');
            return $this->redirect('/torneos/' . $id);
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/torneos/' . $id . '/editar');
        }

        $data = $this->input();
        $validator = $this->validate($data);
        if ($validator->fails()) {
            return $this->render('torneos/form', [
                'title'    => 'Editar torneo',
                'torneo'   => array_merge($torneo, $data),
                'action'   => '/torneos/' . $id,
                'formatos' => self::FORMATOS,
                'errors'   => $validator->errors(),
                'old'      => $data,
            ]);
        }

        $this->torneos->update($id, $data);
        Session::flash('success', 'Torneo actualizado.');
        return $this->redirect('/torneos/' . $id);
    }

    /** @param array<string,string> $params */
    public function cambiarEstado(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        $id = (int) $params['id'];
        $torneo = $this->torneos->find($id);
        if ($torneo === null) {
            return $this->notFound();
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/torneos/' . $id);
        }

        $actual = EstadoTorneo::from($torneo['estado']);
        $destinoRaw = (string) Request::post('estado', '');
        $destino = EstadoTorneo::tryFrom($destinoRaw);

        $permitidoFase1 = $destino !== null && in_array($destino->value, self::ESTADOS_FASE1, true);
        if ($destino === null || !$permitidoFase1 || !$actual->puedeTransicionarA($destino)) {
            Session::flash('error', 'Transición de estado no permitida.');
            return $this->redirect('/torneos/' . $id);
        }

        $this->torneos->cambiarEstado($id, $destino->value);
        $this->auditoria->registrar(
            'torneo',
            $id,
            'CAMBIO_ESTADO',
            Auth::user()['id'] ?? null,
            ['estado' => $actual->value],
            ['estado' => $destino->value],
        );
        Session::flash('success', 'Estado actualizado a: ' . $destino->label() . '.');
        return $this->redirect('/torneos/' . $id);
    }

    /** @param array<string,string> $params */
    public function duplicar(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/torneos');
        }
        $nuevoId = $this->torneos->duplicar((int) $params['id']);
        if ($nuevoId === null) {
            return $this->notFound();
        }
        Session::flash('success', 'Torneo duplicado como borrador.');
        return $this->redirect('/torneos/' . $nuevoId . '/editar');
    }

    /** @param array<string,string> $params */
    public function archivar(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/torneos');
        }
        $archivar = Request::post('archivar', '1') === '1';
        $this->torneos->archivar((int) $params['id'], $archivar);
        Session::flash('success', $archivar ? 'Torneo archivado.' : 'Torneo desarchivado.');
        return $this->redirect('/torneos' . ($archivar ? '' : '?archivados=1'));
    }

    /** @param array<string,string> $params */
    public function delete(array $params): string
    {
        if (($guard = $this->requireGestion()) !== null) {
            return $guard;
        }
        if (!$this->csrfOk()) {
            return $this->csrfFail('/torneos');
        }
        $id = (int) $params['id'];
        $torneo = $this->torneos->find($id);
        $this->torneos->delete($id);
        $this->auditoria->registrar(
            'torneo',
            $id,
            'ELIMINAR',
            Auth::user()['id'] ?? null,
            $torneo !== null ? ['nombre' => $torneo['nombre'], 'estado' => $torneo['estado']] : null,
            null,
        );
        Session::flash('success', 'Torneo eliminado.');
        return $this->redirect('/torneos');
    }

    /** @return array<string,string> */
    private function input(): array
    {
        return [
            'nombre'               => (string) Request::post('nombre', ''),
            'descripcion'          => (string) Request::post('descripcion', ''),
            'lugar'                => (string) Request::post('lugar', ''),
            'fecha_inicio'         => (string) Request::post('fecha_inicio', ''),
            'fecha_fin'            => (string) Request::post('fecha_fin', ''),
            'formato_sets_liga'    => (string) Request::post('formato_sets_liga', '3'),
            'formato_sets_bracket' => (string) Request::post('formato_sets_bracket', '5'),
            'puntos_por_set'       => (string) Request::post('puntos_por_set', '11'),
            'diferencia_minima'    => (string) Request::post('diferencia_minima', '2'),
            'puntos_por_victoria'  => (string) Request::post('puntos_por_victoria', '1'),
            'num_clasificados'     => (string) Request::post('num_clasificados', '8'),
            'posiciones_premiadas' => (string) Request::post('posiciones_premiadas', '3'),
            'cuota_inscripcion'    => (string) Request::post('cuota_inscripcion', ''),
            'pozo_total'           => (string) Request::post('pozo_total', ''),
        ];
    }

    /** @param array<string,string> $data */
    private function validate(array $data): Validator
    {
        $validator = (new Validator($data))
            ->required('nombre', 'Nombre')->maxLen('nombre', 150, 'Nombre')
            ->in('formato_sets_liga', self::FORMATOS, 'Formato de liga')
            ->in('formato_sets_bracket', self::FORMATOS, 'Formato de bracket')
            ->intMin('puntos_por_set', 1, 'Puntos por set')
            ->intMin('diferencia_minima', 1, 'Diferencia mínima')
            ->intMin('puntos_por_victoria', 1, 'Puntos por victoria')
            ->intMin('num_clasificados', 2, 'Número de clasificados')
            ->intMin('posiciones_premiadas', 1, 'Posiciones premiadas');

        foreach (['fecha_inicio' => 'Fecha de inicio', 'fecha_fin' => 'Fecha de fin'] as $campo => $label) {
            $fecha = trim($data[$campo]);
            if ($fecha !== '' && \DateTime::createFromFormat('Y-m-d', $fecha) === false) {
                $validator->addError($campo, "El campo {$label} no es una fecha válida.");
            }
        }

        $inicio = trim($data['fecha_inicio']);
        $fin = trim($data['fecha_fin']);
        if ($inicio !== '' && $fin !== '' && $fin < $inicio) {
            $validator->addError('fecha_fin', 'La fecha de fin no puede ser anterior a la de inicio.');
        }

        foreach (['cuota_inscripcion' => 'Cuota', 'pozo_total' => 'Pozo'] as $campo => $label) {
            $val = trim($data[$campo]);
            if ($val !== '' && !is_numeric($val)) {
                $validator->addError($campo, "El campo {$label} debe ser numérico.");
            }
        }

        return $validator;
    }

    private function notFound(): string
    {
        http_response_code(404);
        return $this->render('error', ['title' => 'No encontrado', 'mensaje' => 'El torneo no existe.']);
    }
}
