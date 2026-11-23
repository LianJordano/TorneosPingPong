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
    public const FORMATOS = [SorteoFiesta::ELIMINACION, SorteoFiesta::GRUPOS, SorteoFiesta::LIGA];
    public const MEJOR_DE_OPCIONES = [1, 3, 5];

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
     * @return array{0:array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int,mejor_de:int,sets_rondas_json:?string},1:array<string,string>}
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
            $errors['formato'] = 'Elegí eliminación directa, grupos o liga.';
        }

        $rawMejorDe = $input['mejor_de'] ?? 1;
        if ($rawMejorDe === '' || $rawMejorDe === null) {
            $mejorDe = 1;
        } else {
            $mejorDe = self::parseEnteroEstricto($rawMejorDe);
            if ($mejorDe === null || !in_array($mejorDe, self::MEJOR_DE_OPCIONES, true)) {
                $errors['mejor_de'] = 'Elegí 1 set, mejor de 3 o mejor de 5.';
                $mejorDe = 1;
            }
        }

        $overrides = [];
        $mapaOverrides = [
            'mejor_de_elim'  => 'eliminatoria',
            'mejor_de_semis' => 'semifinal',
            'mejor_de_final' => 'final',
        ];
        foreach ($mapaOverrides as $field => $jsonKey) {
            $rawOverride = $input[$field] ?? null;
            if ($rawOverride === null || (is_string($rawOverride) && trim($rawOverride) === '')) {
                continue;
            }
            $val = self::parseEnteroEstricto($rawOverride);
            if ($val === null || !in_array($val, self::MEJOR_DE_OPCIONES, true)) {
                $errors[$field] = 'Elegí 1 set, mejor de 3, mejor de 5 o dejalo igual al base.';
                continue;
            }
            $overrides[$jsonKey] = $val;
        }
        $setsRondasJson = $overrides === [] ? null : json_encode($overrides, JSON_THROW_ON_ERROR);

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
        } elseif ($formato === SorteoFiesta::LIGA) {
            $numGrupos = 1;
            $clasificados = self::parseEnteroEstricto($input['clasificados_por_grupo'] ?? null);

            if ($clasificados === null || $clasificados < 2 || $clasificados > 8) {
                $errors['clasificados_por_grupo'] = 'Los clasificados a la eliminatoria van de 2 a 8.';
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
            'mejor_de'               => $mejorDe,
            'sets_rondas_json'       => $setsRondasJson,
        ], $errors];
    }

    /**
     * Parses valid per-round sets overrides from JSON.
     *
     * @return array{eliminatoria?:int,semifinal?:int,final?:int}
     */
    public static function parsearSetsRondas(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $overrides = [];
        foreach (['eliminatoria', 'semifinal', 'final'] as $key) {
            if (!isset($decoded[$key])) {
                continue;
            }
            $val = self::parseEnteroEstricto($decoded[$key]);
            if ($val !== null && in_array($val, self::MEJOR_DE_OPCIONES, true)) {
                $overrides[$key] = $val;
            }
        }
        return $overrides;
    }

    /**
     * Resolves the effective best-of (1, 3, or 5) for a specific match,
     * applying optional per-round overrides (eliminatoria, semifinal, final).
     *
     * @param array<string,mixed> $fiesta
     * @param array<string,mixed> $partido
     */
    public static function mejorDeParaPartido(array $fiesta, array $partido, int $totalRondasElim = 0): int
    {
        $base = (int) ($fiesta['mejor_de'] ?? 1);
        if (!in_array($base, self::MEJOR_DE_OPCIONES, true)) {
            $base = 1;
        }

        $fase = (string) ($partido['fase'] ?? '');
        if ($fase === 'GRUPOS') {
            return $base;
        }

        $overrides = self::parsearSetsRondas(
            is_string($fiesta['sets_rondas_json'] ?? null) ? $fiesta['sets_rondas_json'] : null
        );

        if ($fase === 'ELIMINATORIA') {
            $ronda = (int) ($partido['ronda'] ?? 0);
            $esFinal = ($partido['siguiente_id'] ?? null) === null
                || ($totalRondasElim > 0 && $ronda === $totalRondasElim);
            if ($esFinal) {
                return $overrides['final'] ?? $overrides['eliminatoria'] ?? $base;
            }

            $esSemifinal = $totalRondasElim >= 2 && $ronda === $totalRondasElim - 1;
            if ($esSemifinal) {
                return $overrides['semifinal'] ?? $overrides['eliminatoria'] ?? $base;
            }

            return $overrides['eliminatoria'] ?? $base;
        }

        return $base;
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
     * Validates a match score for 1-set, best-of-3, or best-of-5 formats.
     *
     * @param list<mixed> $setsA
     * @param list<mixed> $setsB
     * @return array{marcador_a:int,marcador_b:int,sets:?list<array{a:int,b:int}>}
     * @throws \DomainException
     */
    public static function validarMarcadorSets(int $mejorDe, array $setsA, array $setsB): array
    {
        if (!in_array($mejorDe, self::MEJOR_DE_OPCIONES, true)) {
            throw new \DomainException('Formato de sets inválido.');
        }

        if ($mejorDe === 1) {
            $rawA = array_key_exists(0, $setsA) && $setsA[0] !== null ? (string) $setsA[0] : null;
            $rawB = array_key_exists(0, $setsB) && $setsB[0] !== null ? (string) $setsB[0] : null;
            [$ma, $mb] = self::validarMarcador($rawA, $rawB);
            return [
                'marcador_a' => $ma,
                'marcador_b' => $mb,
                'sets'       => null,
            ];
        }

        $target = intdiv($mejorDe, 2) + 1;
        $maxIter = max($mejorDe, count($setsA), count($setsB));
        $winsA = 0;
        $winsB = 0;
        $sets = [];
        $huboVacio = false;

        for ($i = 0; $i < $maxIter; $i++) {
            $rawA = array_key_exists($i, $setsA) && $setsA[$i] !== null ? trim((string) $setsA[$i]) : '';
            $rawB = array_key_exists($i, $setsB) && $setsB[$i] !== null ? trim((string) $setsB[$i]) : '';
            $vacioA = $rawA === '';
            $vacioB = $rawB === '';

            if ($vacioA && $vacioB) {
                $huboVacio = true;
                continue;
            }

            if ($i >= $mejorDe) {
                throw new \DomainException("En un partido al mejor de {$mejorDe} no se pueden cargar más de {$mejorDe} sets.");
            }
            if ($huboVacio) {
                throw new \DomainException('Completá los sets en orden, sin dejar sets vacíos en el medio.');
            }
            if ($vacioA !== $vacioB) {
                throw new \DomainException('Set ' . ($i + 1) . ': cargá el puntaje de los dos lados.');
            }
            if ($winsA >= $target || $winsB >= $target) {
                throw new \DomainException('El partido ya se definió: no cargues sets extra.');
            }

            $a = self::parseEnteroEstricto($rawA);
            $b = self::parseEnteroEstricto($rawB);
            if ($a === null || $b === null || $a < 0 || $b < 0 || $a > 999 || $b > 999) {
                throw new \DomainException('Set ' . ($i + 1) . ': cargá números enteros de 0 a 999.');
            }
            if ($a === $b) {
                throw new \DomainException('Set ' . ($i + 1) . ': no puede haber empate en un set.');
            }

            $sets[] = ['a' => $a, 'b' => $b];
            if ($a > $b) {
                $winsA++;
            } else {
                $winsB++;
            }
        }

        if ($winsA < $target && $winsB < $target) {
            throw new \DomainException("El partido todavía no se definió: un lado tiene que ganar {$target} sets.");
        }

        return [
            'marcador_a' => $winsA,
            'marcador_b' => $winsB,
            'sets'       => $sets,
        ];
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
            'numGrupos'     => $fiesta['formato'] === SorteoFiesta::LIGA ? 1 : (int) $fiesta['num_grupos'],
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
        $formato = (string) $fiesta['formato'];
        $numGrupos = $formato === SorteoFiesta::LIGA ? 1 : (int) $fiesta['num_grupos'];
        $errorSorteo = null;
        try {
            SorteoFiesta::validar(
                count($participantes),
                $dobles,
                $formato,
                $numGrupos,
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
     * @param array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int,mejor_de:int,sets_rondas_json:?string} $data
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
            'mejor_de'               => (int) ($fiestaAnterior['mejor_de'] ?? 1),
            'sets_rondas_json'       => $fiestaAnterior['sets_rondas_json'] ?? null,
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
        $numGrupos = $formato === SorteoFiesta::LIGA ? 1 : (int) $fiesta['num_grupos'];
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
     * @param ?list<mixed> $setsA
     * @param ?list<mixed> $setsB
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
        ?array $setsA = null,
        ?array $setsB = null,
    ): array {
        $fiesta = $this->fiestas->find($fiestaId);
        if ($fiesta === null) {
            throw new \DomainException('La fiesta no existe.');
        }

        $partidoActual = $this->fiestas->findPartido($fiestaId, $partidoId);
        if ($partidoActual === null) {
            throw new \DomainException('Ese partido no existe.');
        }

        $totalRondasElim = 0;
        if (($partidoActual['fase'] ?? '') === 'ELIMINATORIA') {
            foreach ($this->fiestas->partidos($fiestaId, 'ELIMINATORIA') as $pe) {
                $totalRondasElim = max($totalRondasElim, (int) $pe['ronda']);
            }
        }

        $mejorDe = self::mejorDeParaPartido($fiesta, $partidoActual, $totalRondasElim);
        if ($mejorDe > 1) {
            $validado = self::validarMarcadorSets($mejorDe, $setsA ?? [], $setsB ?? []);
            $ma = $validado['marcador_a'];
            $mb = $validado['marcador_b'];
            $setsJson = json_encode($validado['sets'], JSON_THROW_ON_ERROR);
        } else {
            [$ma, $mb] = self::validarMarcador($rawA, $rawB);
            $setsJson = null;
        }

        $res = $this->fiestas->guardarResultado($fiestaId, $partidoId, $ma, $mb, $esAdmin, $setsJson);
        $partido = $res['partido'];

        $this->auditoria->registrar(
            'fiesta_partido',
            $partidoId,
            'CARGAR_MARCADOR',
            $usuarioId,
            ['a' => $partido['marcador_a'], 'b' => $partido['marcador_b'], 'sets_json' => $partido['sets_json'] ?? null],
            ['a' => $ma, 'b' => $mb, 'sets_json' => $setsJson],
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
