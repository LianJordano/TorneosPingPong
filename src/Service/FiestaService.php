<?php

declare(strict_types=1);

namespace PingPong\Service;

use PingPong\Domain\BracketEliminacion;
use PingPong\Domain\Fiesta\SorteoFiesta;
use PingPong\Domain\Fiesta\TablaGrupoFiesta;
use PingPong\Repository\AuditoriaRepository;
use PingPong\Repository\FiestaRepository;

/**
 * Application orchestration for the "torneo fiesta" module: validation,
 * atomic creation, draw/roulette script construction, standings/bracket
 * view-models, score recording, and consistent audit logging.
 */
final class FiestaService
{
    public const MODALIDADES = ['INDIVIDUAL', 'DOBLES'];
    public const FORMATOS = [SorteoFiesta::ELIMINACION, SorteoFiesta::GRUPOS];

    public function __construct(
        private FiestaRepository $fiestas,
        private AuditoriaRepository $auditoria,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        return $this->fiestas->all();
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        return $this->fiestas->find($id);
    }

    /** @return list<array<string,mixed>> */
    public function jugadoresActivos(): array
    {
        return $this->fiestas->jugadoresActivos();
    }

    /**
     * Validates fiesta configuration fields. In ELIMINACION mode, group fields
     * are hidden in the form so they are normalized to safe defaults without
     * triggering validation errors.
     *
     * @param array<string,mixed> $input
     * @return array{0:array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int},1:array<string,string>}
     */
    public static function validarConfig(array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $modalidad = (string) ($input['modalidad'] ?? 'DOBLES');
        $formato = (string) ($input['formato'] ?? SorteoFiesta::ELIMINACION);

        $errors = [];
        if ($nombre === '') {
            $errors['nombre'] = 'Poné un nombre a la fiesta.';
        } elseif (mb_strlen($nombre) > 150) {
            $errors['nombre'] = 'El nombre admite hasta 150 caracteres.';
        }
        if (!in_array($modalidad, self::MODALIDADES, true)) {
            $errors['modalidad'] = 'Elegí individual o dobles.';
        }
        if (!in_array($formato, self::FORMATOS, true)) {
            $errors['formato'] = 'Elegí eliminación directa o grupos.';
        }

        if ($formato === SorteoFiesta::GRUPOS) {
            $numGrupos = self::parseEnteroEstricto($input['num_grupos'] ?? null);
            $clasificados = self::parseEnteroEstricto($input['clasificados_por_grupo'] ?? null);

            if ($numGrupos === null || $numGrupos < 1 || $numGrupos > 16) {
                $errors['num_grupos'] = 'Los grupos van de 1 a 16.';
                $numGrupos = 2;
            }
            if ($clasificados === null || $clasificados < 1 || $clasificados > 8) {
                $errors['clasificados_por_grupo'] = 'Los clasificados por grupo van de 1 a 8.';
                $clasificados = 2;
            }
        } else {
            $numGrupos = 2;
            $clasificados = 2;
        }

        return [[
            'nombre'                 => $nombre,
            'modalidad'              => $modalidad,
            'formato'                => $formato,
            'num_grupos'             => $numGrupos,
            'clasificados_por_grupo' => $clasificados,
        ], $errors];
    }

    /**
     * Strictly parses two non-negative integer scores (0..999) with no ties.
     *
     * @return array{0:int,1:int}
     * @throws \DomainException
     */
    public static function validarMarcador(?string $rawA, ?string $rawB): array
    {
        $ma = self::parseEnteroEstricto($rawA);
        $mb = self::parseEnteroEstricto($rawB);
        if ($ma === null || $mb === null || $ma < 0 || $mb < 0 || $ma > 999 || $mb > 999) {
            throw new \DomainException('Cargá el marcador de los dos lados (números enteros de 0 a 999).');
        }
        if ($ma === $mb) {
            throw new \DomainException('No puede haber empate: alguien tiene que ganar.');
        }
        return [$ma, $mb];
    }

    /**
     * Builds the roulette script JSON payload (exact schema expected by ruleta.js).
     *
     * @param array<string,mixed> $fiesta
     * @param list<array{id:int,nombre:string}> $participantes
     * @param array{equipos:list<array{numero:int,p1:int,p2:?int}>,bracket:array{rondas:int,partidos:list<array<string,mixed>>},guion:list<array<string,int|string>>} $sorteo
     * @return array<string,mixed>
     */
    public static function construirGuion(array $fiesta, array $participantes, array $sorteo): array
    {
        $nombres = [];
        foreach ($participantes as $p) {
            $nombres[$p['id']] = $p['nombre'];
        }
        $equipos = [];
        foreach ($sorteo['equipos'] as $e) {
            $equipos[$e['numero']] = ['p1' => $e['p1'], 'p2' => $e['p2']];
        }
        $cruces = [];
        foreach ($sorteo['bracket']['partidos'] as $p) {
            if ($p['ronda'] === 1) {
                $cruces[] = ['orden' => $p['orden'], 'a' => $p['a'], 'b' => $p['b'], 'bye' => $p['bye']];
            }
        }

        return [
            'modalidad'     => $fiesta['modalidad'],
            'formato'       => (string) $fiesta['formato'],
            'numGrupos'     => (int) $fiesta['num_grupos'],
            'participantes' => $nombres,
            'equipos'       => $equipos,
            'pasos'         => $sorteo['guion'],
            'cruces'        => $cruces,
            'rondas'        => $sorteo['bracket']['rondas'],
        ];
    }

    /**
     * Returns a set of elimination match IDs whose next match is already FINISHED,
     * so the UI does not render an uneditable correction form.
     *
     * @param list<array<string,mixed>> $eliminatoria
     * @return array<int,true>
     */
    public static function partidosBloqueados(array $eliminatoria): array
    {
        $porId = [];
        foreach ($eliminatoria as $p) {
            $porId[(int) $p['id']] = $p;
        }
        $bloqueados = [];
        foreach ($eliminatoria as $p) {
            if ($p['siguiente_id'] === null) {
                continue;
            }
            $sig = $porId[(int) $p['siguiente_id']] ?? null;
            if ($sig !== null && $sig['estado'] === 'FINALIZADO') {
                $bloqueados[(int) $p['id']] = true;
            }
        }
        return $bloqueados;
    }

    /**
     * Determines whether a match score form can be rendered/edited.
     *
     * @param array<string,mixed> $partido
     * @param array<string,mixed> $fiesta
     * @param array<int,true> $partidosBloqueados
     */
    public static function puedeEditarPartido(
        array $partido,
        array $fiesta,
        bool $puedeAnotar,
        bool $puedeGestionar,
        array $partidosBloqueados,
    ): bool {
        if (!$puedeAnotar) {
            return false;
        }
        if ((int) $partido['es_bye'] === 1 || $partido['equipo_a_id'] === null || $partido['equipo_b_id'] === null) {
            return false;
        }
        if (isset($partidosBloqueados[(int) $partido['id']])) {
            return false;
        }
        if ($partido['fase'] === $fiesta['estado']) {
            return true;
        }
        $esFinal = $partido['fase'] === 'ELIMINATORIA' && $partido['siguiente_id'] === null;
        return $esFinal && $fiesta['estado'] === 'FINALIZADO' && $puedeGestionar;
    }

    /**
     * Prepares the full view-model for fiesta/show without duplicate DB queries.
     *
     * @return array<string,mixed>|null
     */
    public function prepararVistaShow(int $fiestaId): ?array
    {
        $fiesta = $this->fiestas->find($fiestaId);
        if ($fiesta === null) {
            return null;
        }
        $id = (int) $fiesta['id'];
        $participantes = $this->fiestas->participantes($id);
        $equipos = $this->fiestas->equipos($id);
        $equiposPorId = [];
        foreach ($equipos as $e) {
            $equiposPorId[(int) $e['id']] = $e;
        }

        $partidosGrupos = $fiesta['estado'] === 'INSCRIPCION' ? [] : $this->fiestas->partidos($id, 'GRUPOS');
        $eliminatoria = $fiesta['estado'] === 'INSCRIPCION' ? [] : $this->fiestas->partidos($id, 'ELIMINATORIA');

        $grupos = $fiesta['estado'] === 'INSCRIPCION' ? [] : $this->armarGrupos($equipos, $partidosGrupos);

        $rondas = [];
        foreach ($eliminatoria as $p) {
            $rondas[(int) $p['ronda']][] = $p;
        }
        ksort($rondas);

        $hayResultados = false;
        foreach ([$partidosGrupos, $eliminatoria] as $lista) {
            foreach ($lista as $p) {
                if ((int) $p['es_bye'] === 0 && $p['estado'] === 'FINALIZADO') {
                    $hayResultados = true;
                    break 2;
                }
            }
        }

        $dobles = $fiesta['modalidad'] === 'DOBLES';
        $errorSorteo = null;
        try {
            SorteoFiesta::validar(
                count($participantes),
                $dobles,
                (string) $fiesta['formato'],
                (int) $fiesta['num_grupos'],
                (int) $fiesta['clasificados_por_grupo'],
            );
        } catch (\DomainException $e) {
            $errorSorteo = $e->getMessage();
        }

        return [
            'title'              => $fiesta['nombre'],
            'fiesta'             => $fiesta,
            'participantes'      => $participantes,
            'disponibles'        => $fiesta['estado'] === 'INSCRIPCION' ? $this->fiestas->disponibles($id) : [],
            'equipos'            => $equiposPorId,
            'grupos'             => $grupos,
            'rondas'             => $rondas,
            'partidosBloqueados' => self::partidosBloqueados($eliminatoria),
            'campeon'            => $this->campeon($rondas, $equiposPorId),
            'errorSorteo'        => $errorSorteo,
            'gruposListos'       => $grupos !== [] && $this->gruposTerminados($grupos),
            'hayResultados'      => $hayResultados,
        ];
    }

    /**
     * Creates a fiesta and registers initial participants atomically.
     *
     * @param array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int} $data
     * @param list<int> $personaIds
     */
    public function crearFiesta(array $data, array $personaIds, ?int $usuarioId): int
    {
        return $this->fiestas->transaccion(function () use ($data, $personaIds, $usuarioId): int {
            $id = $this->fiestas->create($data, $usuarioId);
            if ($personaIds !== []) {
                $this->fiestas->agregarParticipantes($id, $personaIds);
            }
            $this->auditoria->registrar('fiesta', $id, 'CREAR', $usuarioId, null, $data + [
                'participantes_iniciales' => count(array_unique($personaIds)),
            ]);
            return $id;
        });
    }

    /**
     * @param array<string,mixed> $fiestaAnterior
     * @param array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int} $data
     */
    public function actualizarConfig(array $fiestaAnterior, array $data, ?int $usuarioId): void
    {
        $id = (int) $fiestaAnterior['id'];
        $this->fiestas->actualizarConfig($id, $data);
        $this->auditoria->registrar('fiesta', $id, 'ACTUALIZAR_CONFIG', $usuarioId, [
            'nombre'                 => $fiestaAnterior['nombre'],
            'modalidad'              => $fiestaAnterior['modalidad'],
            'formato'                => $fiestaAnterior['formato'],
            'num_grupos'             => (int) $fiestaAnterior['num_grupos'],
            'clasificados_por_grupo' => (int) $fiestaAnterior['clasificados_por_grupo'],
        ], $data);
    }

    /**
     * @param list<int> $personaIds
     */
    public function agregarParticipantes(int $fiestaId, array $personaIds, ?int $usuarioId): int
    {
        $agregados = $this->fiestas->agregarParticipantes($fiestaId, $personaIds);
        if ($agregados > 0) {
            $this->auditoria->registrar('fiesta', $fiestaId, 'AGREGAR_PARTICIPANTES', $usuarioId, null, [
                'agregados'   => $agregados,
                'persona_ids' => array_values(array_unique($personaIds)),
            ]);
        }
        return $agregados;
    }

    public function quitarParticipante(int $fiestaId, int $participanteId, ?int $usuarioId): void
    {
        $this->fiestas->quitarParticipante($fiestaId, $participanteId);
        $this->auditoria->registrar('fiesta', $fiestaId, 'QUITAR_PARTICIPANTE', $usuarioId, [
            'participante_id' => $participanteId,
        ], null);
    }

    /**
     * Executes the draw, persists it under lock, and records audit trail.
     *
     * @param array<string,mixed> $fiesta
     * @throws \DomainException
     */
    public function sortear(array $fiesta, ?int $usuarioId): void
    {
        $id = (int) $fiesta['id'];
        $participantes = $this->fiestas->participantes($id);
        $dobles = $fiesta['modalidad'] === 'DOBLES';
        $formato = (string) $fiesta['formato'];
        $numGrupos = (int) $fiesta['num_grupos'];
        $clasificados = (int) $fiesta['clasificados_por_grupo'];

        $sorteo = SorteoFiesta::sortear(
            array_column($participantes, 'id'),
            $dobles,
            $formato,
            $numGrupos,
            null,
            $clasificados,
        );

        $guion = self::construirGuion($fiesta, $participantes, $sorteo);
        $this->fiestas->guardarSorteo($id, $formato, $sorteo, $guion);
        $this->auditoria->registrar('fiesta', $id, 'SORTEO', $usuarioId, null, [
            'participantes' => count($participantes),
            'equipos'       => count($sorteo['equipos']),
            'formato'       => $formato,
        ]);
    }

    /** @throws \DomainException */
    public function deshacerSorteo(int $fiestaId, ?int $usuarioId): void
    {
        $this->fiestas->deshacerSorteo($fiestaId);
        $this->auditoria->registrar('fiesta', $fiestaId, 'DESHACER_SORTEO', $usuarioId, null, null);
    }

    /**
     * Records a match result atomically (closing or correcting the final as needed).
     *
     * @return array{es_final:bool,ganador_id:int}
     * @throws \DomainException
     */
    public function guardarResultado(
        int $fiestaId,
        int $partidoId,
        ?string $rawA,
        ?string $rawB,
        bool $esAdmin,
        ?int $usuarioId,
    ): array {
        [$ma, $mb] = self::validarMarcador($rawA, $rawB);
        $res = $this->fiestas->guardarResultado($fiestaId, $partidoId, $ma, $mb, $esAdmin);
        $partido = $res['partido'];

        $this->auditoria->registrar(
            'fiesta_partido',
            $partidoId,
            'CARGAR_MARCADOR',
            $usuarioId,
            ['a' => $partido['marcador_a'], 'b' => $partido['marcador_b']],
            ['a' => $ma, 'b' => $mb],
        );

        return [
            'es_final'   => $res['es_final'],
            'ganador_id' => $res['ganador_id'],
        ];
    }

    /**
     * Promotes qualified teams from finished groups into the knockout bracket.
     *
     * @param array<string,mixed> $fiesta
     * @return int number of qualified teams
     * @throws \DomainException
     */
    public function pasarAEliminatoria(array $fiesta, ?int $usuarioId): int
    {
        $id = (int) $fiesta['id'];
        $grupos = $this->armarGrupos(
            $this->fiestas->equipos($id),
            $this->fiestas->partidos($id, 'GRUPOS'),
        );
        if (!$this->gruposTerminados($grupos)) {
            throw new \DomainException('Faltan partidos de grupo por jugar.');
        }

        $clasificados = [];
        foreach ($grupos as $numero => $g) {
            $clasificados[$numero] = array_column($g['tabla'], 'equipo');
        }
        $orden = TablaGrupoFiesta::sembrar($clasificados, (int) $fiesta['clasificados_por_grupo']);
        if (count($orden) < 2) {
            throw new \DomainException('Se necesitan al menos 2 clasificados para la eliminatoria.');
        }

        $this->fiestas->crearEliminatoria($id, BracketEliminacion::generar($orden));
        $this->auditoria->registrar('fiesta', $id, 'GENERAR_ELIMINATORIA', $usuarioId, null, [
            'clasificados' => count($orden),
        ]);

        return count($orden);
    }

    /** @param array<string,mixed> $fiesta */
    public function eliminar(array $fiesta, ?int $usuarioId): void
    {
        $id = (int) $fiesta['id'];
        $this->fiestas->delete($id);
        $this->auditoria->registrar('fiesta', $id, 'ELIMINAR', $usuarioId, ['nombre' => $fiesta['nombre']], null);
    }

    // ------------------------------------------------------------------

    private static function parseEnteroEstricto(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/^-?\d+$/', $trimmed) !== 1) {
            return null;
        }
        $parsed = filter_var($trimmed, FILTER_VALIDATE_INT);
        return $parsed === false ? null : $parsed;
    }

    /**
     * @param list<array<string,mixed>> $equipos
     * @param list<array<string,mixed>> $partidosGrupos
     * @return array<int,array{letra:string,equipos:list<int>,tabla:list<array<string,int>>,partidos:list<array<string,mixed>>}>
     */
    private function armarGrupos(array $equipos, array $partidosGrupos): array
    {
        $grupos = [];
        foreach ($equipos as $e) {
            if ($e['grupo'] !== null) {
                $g = (int) $e['grupo'];
                $grupos[$g] ??= ['letra' => chr(64 + $g), 'equipos' => [], 'tabla' => [], 'partidos' => []];
                $grupos[$g]['equipos'][] = (int) $e['id'];
            }
        }
        foreach ($partidosGrupos as $p) {
            $grupos[(int) $p['grupo']]['partidos'][] = $p;
        }
        foreach ($grupos as &$g) {
            $jugados = [];
            foreach ($g['partidos'] as $p) {
                if ($p['estado'] === 'FINALIZADO') {
                    $jugados[] = [
                        'a'       => (int) $p['equipo_a_id'],
                        'b'       => (int) $p['equipo_b_id'],
                        'ma'      => (int) $p['marcador_a'],
                        'mb'      => (int) $p['marcador_b'],
                        'ganador' => (int) $p['ganador_id'],
                    ];
                }
            }
            $g['tabla'] = TablaGrupoFiesta::calcular($g['equipos'], $jugados);
        }
        unset($g);
        ksort($grupos);
        return $grupos;
    }

    /** @param array<int,array{partidos:list<array<string,mixed>>}> $grupos */
    private function gruposTerminados(array $grupos): bool
    {
        foreach ($grupos as $g) {
            foreach ($g['partidos'] as $p) {
                if ($p['estado'] !== 'FINALIZADO') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param array<int,list<array<string,mixed>>> $rondas
     * @param array<int,array<string,mixed>> $equipos
     * @return array<string,mixed>|null
     */
    private function campeon(array $rondas, array $equipos): ?array
    {
        if ($rondas === []) {
            return null;
        }
        $final = $rondas[max(array_keys($rondas))][0] ?? null;
        if ($final === null || $final['estado'] !== 'FINALIZADO' || $final['ganador_id'] === null) {
            return null;
        }
        return $equipos[(int) $final['ganador_id']] ?? null;
    }
}
