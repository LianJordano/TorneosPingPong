<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;
use PingPong\Domain\Fiesta\SorteoFiesta;
use PingPong\Domain\FixtureRoundRobin;

/**
 * Persistence for the party tournament ("torneo fiesta"): a parallel system
 * with its own tables (fiesta, fiesta_participante, fiesta_equipo, fiesta_partido).
 */
final class FiestaRepository
{
    private const EQUIPO_LABEL = "CASE WHEN %1\$s.participante_2_id IS NULL
                                       THEN CONCAT(TRIM(%2\$s.nombre), ' ', TRIM(%2\$s.apellido))
                                       ELSE CONCAT(TRIM(%2\$s.nombre), ' ', TRIM(%2\$s.apellido), ' & ',
                                                   TRIM(%3\$s.nombre), ' ', TRIM(%3\$s.apellido)) END";

    public function __construct(private PDO $db)
    {
    }

    /**
     * Runs a callback inside a DB transaction (reusing an active one if present).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaccion(callable $fn): mixed
    {
        if ($this->db->inTransaction()) {
            return $fn();
        }
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query(
            'SELECT f.*,
                    (SELECT COUNT(*) FROM fiesta_participante fp WHERE fp.fiesta_id = f.id) AS participantes,
                    (SELECT COUNT(*) FROM fiesta_equipo fe WHERE fe.fiesta_id = f.id) AS equipos
             FROM fiesta f
             ORDER BY f.created_at DESC, f.id DESC'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fiesta WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int,mejor_de?:int,sets_rondas_json?:?string} $data */
    public function create(array $data, ?int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO fiesta (nombre, modalidad, formato, num_grupos, clasificados_por_grupo, mejor_de, sets_rondas_json, created_by)
             VALUES (:nombre, :modalidad, :formato, :grupos, :clasificados, :mejor_de, :sets_rondas_json, :usuario)'
        );
        $stmt->execute([
            ':nombre'           => $data['nombre'],
            ':modalidad'        => $data['modalidad'],
            ':formato'          => $data['formato'],
            ':grupos'           => $data['num_grupos'],
            ':clasificados'     => $data['clasificados_por_grupo'],
            ':mejor_de'         => $data['mejor_de'] ?? 1,
            ':sets_rondas_json' => $data['sets_rondas_json'] ?? null,
            ':usuario'          => $usuarioId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array{nombre:string,modalidad:string,formato:string,num_grupos:int,clasificados_por_grupo:int,mejor_de?:int,sets_rondas_json?:?string} $data */
    public function actualizarConfig(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE fiesta SET nombre = :nombre, modalidad = :modalidad, formato = :formato,
                    num_grupos = :grupos, clasificados_por_grupo = :clasificados,
                    mejor_de = :mejor_de, sets_rondas_json = :sets_rondas_json
             WHERE id = :id'
        );
        $stmt->execute([
            ':nombre'           => $data['nombre'],
            ':modalidad'        => $data['modalidad'],
            ':formato'          => $data['formato'],
            ':grupos'           => $data['num_grupos'],
            ':clasificados'     => $data['clasificados_por_grupo'],
            ':mejor_de'         => $data['mejor_de'] ?? 1,
            ':sets_rondas_json' => $data['sets_rondas_json'] ?? null,
            ':id'               => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->transaccion(function () use ($id): void {
            // Self-referencing and team FKs: clear matches and teams before the fiesta.
            $this->db->prepare('DELETE FROM fiesta_partido WHERE fiesta_id = :id')->execute([':id' => $id]);
            $this->db->prepare('DELETE FROM fiesta_equipo WHERE fiesta_id = :id')->execute([':id' => $id]);
            $this->db->prepare('DELETE FROM fiesta WHERE id = :id')->execute([':id' => $id]);
        });
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->db->prepare('UPDATE fiesta SET estado = :estado WHERE id = :id')
            ->execute([':estado' => $estado, ':id' => $id]);
    }

    // ------------------------------------------------------------------
    //  Participants
    // ------------------------------------------------------------------

    /**
     * Registered players (platform `persona`), with a display name for the
     * roulette ("Nombre Apellido").
     *
     * @return list<array{id:int,persona_id:int,nombre:string,area:?string}>
     */
    public function participantes(int $fiestaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT fp.id, fp.persona_id, CONCAT(TRIM(p.nombre), ' ', TRIM(p.apellido)) AS nombre, ar.nombre AS area
             FROM fiesta_participante fp
             JOIN persona p    ON p.id = fp.persona_id
             LEFT JOIN area ar ON ar.id = p.area_id
             WHERE fp.fiesta_id = :f
             ORDER BY p.nombre, p.apellido"
        );
        $stmt->execute([':f' => $fiestaId]);
        return array_map(
            static fn (array $r): array => [
                'id'         => (int) $r['id'],
                'persona_id' => (int) $r['persona_id'],
                'nombre'     => (string) $r['nombre'],
                'area'       => $r['area'] !== null ? (string) $r['area'] : null,
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * Active players not yet registered in this fiesta.
     *
     * @return list<array<string,mixed>>
     */
    public function disponibles(int $fiestaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.nombre, p.apellido, ar.nombre AS area
             FROM persona p
             LEFT JOIN area ar ON ar.id = p.area_id
             WHERE p.activo = 1
               AND p.id NOT IN (SELECT persona_id FROM fiesta_participante WHERE fiesta_id = :f)
             ORDER BY p.apellido, p.nombre'
        );
        $stmt->execute([':f' => $fiestaId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> every active player (create form) */
    public function jugadoresActivos(): array
    {
        return $this->db->query(
            'SELECT p.id, p.nombre, p.apellido, ar.nombre AS area
             FROM persona p
             LEFT JOIN area ar ON ar.id = p.area_id
             WHERE p.activo = 1
             ORDER BY p.apellido, p.nombre'
        )->fetchAll();
    }

    /**
     * Registers platform players transactionally and idempotently.
     * Only active players count; ids already in the fiesta, inactive or unknown are skipped.
     *
     * @param list<int> $personaIds
     * @return int players added
     */
    public function agregarParticipantes(int $fiestaId, array $personaIds): int
    {
        return $this->transaccion(function () use ($fiestaId, $personaIds): int {
            $estado = $this->bloquearEstado($fiestaId);
            if ($estado !== 'INSCRIPCION') {
                throw new \DomainException('Solo se pueden sumar jugadores durante la inscripción.');
            }

            $validos = [];
            foreach ($this->disponibles($fiestaId) as $d) {
                $validos[(int) $d['id']] = true;
            }

            $insert = $this->db->prepare('INSERT IGNORE INTO fiesta_participante (fiesta_id, persona_id) VALUES (:f, :p)');
            $agregados = 0;
            foreach (array_unique($personaIds) as $personaId) {
                if (!isset($validos[$personaId])) {
                    continue;
                }
                $insert->execute([':f' => $fiestaId, ':p' => $personaId]);
                $agregados += $insert->rowCount();
            }
            return $agregados;
        });
    }

    public function quitarParticipante(int $fiestaId, int $participanteId): void
    {
        $this->db->prepare('DELETE FROM fiesta_participante WHERE id = :id AND fiesta_id = :f')
            ->execute([':id' => $participanteId, ':f' => $fiestaId]);
    }

    // ------------------------------------------------------------------
    //  Draw
    // ------------------------------------------------------------------

    /**
     * Persists the draw atomically: teams, groups or bracket, matches, the
     * roulette script and the new state. Locks the fiesta row to prevent
     * double-submit race conditions.
     *
     * @param array{equipos:list<array{numero:int,p1:int,p2:?int}>,orden:list<int>,grupos:array<int,int>,bracket:array{tamano:int,rondas:int,partidos:list<array<string,mixed>>}} $sorteo
     * @param array<string,mixed> $guion roulette payload (stored as JSON)
     */
    public function guardarSorteo(int $fiestaId, string $formato, array $sorteo, array $guion): void
    {
        $this->transaccion(function () use ($fiestaId, $formato, $sorteo, $guion): void {
            $estadoActual = $this->bloquearEstado($fiestaId);
            if ($estadoActual !== 'INSCRIPCION') {
                throw new \DomainException('La fiesta ya fue sorteada o no está en etapa de inscripción.');
            }

            $insEquipo = $this->db->prepare(
                'INSERT INTO fiesta_equipo (fiesta_id, numero, participante_1_id, participante_2_id, grupo)
                 VALUES (:f, :numero, :p1, :p2, :grupo)'
            );
            $idPorNumero = [];
            foreach ($sorteo['equipos'] as $e) {
                $insEquipo->execute([
                    ':f'      => $fiestaId,
                    ':numero' => $e['numero'],
                    ':p1'     => $e['p1'],
                    ':p2'     => $e['p2'],
                    ':grupo'  => $sorteo['grupos'][$e['numero']] ?? null,
                ]);
                $idPorNumero[$e['numero']] = (int) $this->db->lastInsertId();
            }

            if ($formato === SorteoFiesta::GRUPOS || $formato === SorteoFiesta::LIGA) {
                $porGrupo = [];
                foreach ($sorteo['orden'] as $numero) {
                    $porGrupo[$sorteo['grupos'][$numero]][] = $idPorNumero[$numero];
                }
                ksort($porGrupo);
                $insPartido = $this->db->prepare(
                    "INSERT INTO fiesta_partido (fiesta_id, fase, grupo, ronda, orden, equipo_a_id, equipo_b_id)
                     VALUES (:f, 'GRUPOS', :g, :ronda, :orden, :a, :b)"
                );
                // Monotonic orden across groups satisfies uq_fiesta_partido_slot (fiesta_id, fase, ronda, orden).
                $orden = 0;
                foreach ($porGrupo as $grupo => $ids) {
                    foreach (FixtureRoundRobin::generar($ids) as $m) {
                        $insPartido->execute([
                            ':f' => $fiestaId, ':g' => $grupo, ':ronda' => $m['ronda'],
                            ':orden' => $orden++, ':a' => $m['a'], ':b' => $m['b'],
                        ]);
                    }
                }
                $estado = 'GRUPOS';
            } else {
                $bracket = $sorteo['bracket'];
                $mapear = static fn (?int $n): ?int => $n === null ? null : $idPorNumero[$n];
                foreach ($bracket['partidos'] as &$p) {
                    $p['a'] = $mapear($p['a']);
                    $p['b'] = $mapear($p['b']);
                    $p['ganador'] = $mapear($p['ganador']);
                }
                unset($p);
                $this->insertarBracket($fiestaId, $bracket);
                $estado = 'ELIMINATORIA';
            }

            $this->db->prepare(
                'UPDATE fiesta SET estado = :estado, sorteo_json = :json, sorteado_at = NOW() WHERE id = :id'
            )->execute([
                ':estado' => $estado,
                ':json'   => json_encode($guion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ':id'     => $fiestaId,
            ]);
        });
    }

    /** Removes the draw (teams and matches) and goes back to registration. */
    public function deshacerSorteo(int $fiestaId): void
    {
        $this->transaccion(function () use ($fiestaId): void {
            $estado = $this->bloquearEstado($fiestaId);
            if ($estado === null || $estado === 'INSCRIPCION') {
                return;
            }
            $check = $this->db->prepare(
                "SELECT 1 FROM fiesta_partido WHERE fiesta_id = :f AND es_bye = 0 AND estado = 'FINALIZADO' LIMIT 1"
            );
            $check->execute([':f' => $fiestaId]);
            if ($check->fetchColumn() !== false) {
                throw new \DomainException('Ya hay resultados cargados: no se puede repetir el sorteo.');
            }

            $this->db->prepare('DELETE FROM fiesta_partido WHERE fiesta_id = :f')->execute([':f' => $fiestaId]);
            $this->db->prepare('DELETE FROM fiesta_equipo WHERE fiesta_id = :f')->execute([':f' => $fiestaId]);
            $this->db->prepare(
                "UPDATE fiesta SET estado = 'INSCRIPCION', sorteo_json = NULL, sorteado_at = NULL WHERE id = :f"
            )->execute([':f' => $fiestaId]);
        });
    }

    // ------------------------------------------------------------------
    //  Teams and matches
    // ------------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function equipos(int $fiestaId): array
    {
        $label = sprintf(self::EQUIPO_LABEL, 'e', 'p1', 'p2');
        $stmt = $this->db->prepare(
            "SELECT e.id, e.numero, e.grupo, e.participante_1_id, e.participante_2_id,
                    {$label} AS label
             FROM fiesta_equipo e
             JOIN fiesta_participante fp1 ON fp1.id = e.participante_1_id
             JOIN persona p1              ON p1.id = fp1.persona_id
             LEFT JOIN fiesta_participante fp2 ON fp2.id = e.participante_2_id
             LEFT JOIN persona p2              ON p2.id = fp2.persona_id
             WHERE e.fiesta_id = :f
             ORDER BY e.numero"
        );
        $stmt->execute([':f' => $fiestaId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function partidos(int $fiestaId, string $fase): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fiesta_partido WHERE fiesta_id = :f AND fase = :fase ORDER BY grupo, ronda, orden'
        );
        $stmt->execute([':f' => $fiestaId, ':fase' => $fase]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findPartido(int $fiestaId, int $partidoId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fiesta_partido WHERE id = :id AND fiesta_id = :f');
        $stmt->execute([':id' => $partidoId, ':f' => $fiestaId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Saves a score atomically under FOR UPDATE locks: validates fiesta state,
     * match readiness, next-match lock, advances winner in knockout, and closes
     * the fiesta when saving the final (or allows ADMIN to correct the final).
     *
     * @return array{partido:array<string,mixed>,es_final:bool,ganador_id:int}
     * @throws \DomainException
     */
    public function guardarResultado(
        int $fiestaId,
        int $partidoId,
        int $marcadorA,
        int $marcadorB,
        bool $puedeCorregirFinal = false,
        ?string $setsJson = null,
    ): array {
        return $this->transaccion(function () use ($fiestaId, $partidoId, $marcadorA, $marcadorB, $puedeCorregirFinal, $setsJson): array {
            $estadoFiesta = $this->bloquearEstado($fiestaId);
            if ($estadoFiesta === null) {
                throw new \DomainException('La fiesta no existe.');
            }

            $stmt = $this->db->prepare('SELECT * FROM fiesta_partido WHERE id = :id AND fiesta_id = :f FOR UPDATE');
            $stmt->execute([':id' => $partidoId, ':f' => $fiestaId]);
            $partido = $stmt->fetch();
            if ($partido === false || (int) $partido['es_bye'] === 1 || $partido['equipo_a_id'] === null || $partido['equipo_b_id'] === null) {
                throw new \DomainException('Ese partido todavía no tiene rivales definidos.');
            }

            $esFinal = $partido['fase'] === 'ELIMINATORIA' && $partido['siguiente_id'] === null;
            $corrigiendoFinal = $esFinal && $estadoFiesta === 'FINALIZADO' && $puedeCorregirFinal;
            if ($partido['fase'] !== $estadoFiesta && !$corrigiendoFinal) {
                throw new \DomainException('Esa fase ya está cerrada.');
            }

            $ganadorId = $marcadorA > $marcadorB
                ? (int) $partido['equipo_a_id']
                : (int) $partido['equipo_b_id'];

            if ($partido['fase'] === 'ELIMINATORIA' && $partido['siguiente_id'] !== null) {
                $stmtSig = $this->db->prepare('SELECT estado FROM fiesta_partido WHERE id = :id AND fiesta_id = :f FOR UPDATE');
                $stmtSig->execute([':id' => (int) $partido['siguiente_id'], ':f' => $fiestaId]);
                $estadoSig = $stmtSig->fetchColumn();
                if ($estadoSig === 'FINALIZADO' && $partido['ganador_id'] !== null && (int) $partido['ganador_id'] !== $ganadorId) {
                    throw new \DomainException('No se puede cambiar el ganador: el partido siguiente ya se jugó.');
                }
            }

            $this->db->prepare(
                "UPDATE fiesta_partido
                 SET marcador_a = :ma, marcador_b = :mb, sets_json = :sets_json, ganador_id = :g, estado = 'FINALIZADO'
                 WHERE id = :id"
            )->execute([
                ':ma'        => $marcadorA,
                ':mb'        => $marcadorB,
                ':sets_json' => $setsJson,
                ':g'         => $ganadorId,
                ':id'        => $partidoId,
            ]);

            if ($partido['fase'] === 'ELIMINATORIA' && $partido['siguiente_id'] !== null) {
                $columna = (int) $partido['orden'] % 2 === 0 ? 'equipo_a_id' : 'equipo_b_id';
                $this->db->prepare("UPDATE fiesta_partido SET {$columna} = :g WHERE id = :id")
                    ->execute([':g' => $ganadorId, ':id' => (int) $partido['siguiente_id']]);
            }

            if ($esFinal) {
                $this->db->prepare("UPDATE fiesta SET estado = 'FINALIZADO' WHERE id = :id")
                    ->execute([':id' => $fiestaId]);
            }

            return [
                'partido'    => $partido,
                'es_final'   => $esFinal,
                'ganador_id' => $ganadorId,
            ];
        });
    }

    /** @param array{tamano:int,rondas:int,partidos:list<array<string,mixed>>} $bracket team ids already mapped */
    public function crearEliminatoria(int $fiestaId, array $bracket): void
    {
        $this->transaccion(function () use ($fiestaId, $bracket): void {
            $estadoActual = $this->bloquearEstado($fiestaId);
            if ($estadoActual !== 'GRUPOS') {
                throw new \DomainException('La eliminatoria ya fue creada o la fiesta no está en fase de grupos.');
            }
            $this->insertarBracket($fiestaId, $bracket);
            $this->db->prepare("UPDATE fiesta SET estado = 'ELIMINATORIA' WHERE id = :id")->execute([':id' => $fiestaId]);
        });
    }

    /** Locks the fiesta row inside an active transaction and returns its estado. */
    private function bloquearEstado(int $fiestaId): ?string
    {
        $stmt = $this->db->prepare('SELECT estado FROM fiesta WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $fiestaId]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : (string) $estado;
    }

    /**
     * Inserts bracket matches from the final down so each one can point to the
     * match its winner advances to. Caller owns the transaction.
     *
     * @param array{tamano:int,rondas:int,partidos:list<array<string,mixed>>} $bracket
     */
    private function insertarBracket(int $fiestaId, array $bracket): void
    {
        $partidos = $bracket['partidos'];
        usort($partidos, static fn (array $x, array $y): int => $y['ronda'] <=> $x['ronda'] ?: $x['orden'] <=> $y['orden']);

        $insert = $this->db->prepare(
            "INSERT INTO fiesta_partido
                (fiesta_id, fase, ronda, orden, equipo_a_id, equipo_b_id, ganador_id, es_bye, estado, siguiente_id)
             VALUES (:f, 'ELIMINATORIA', :ronda, :orden, :a, :b, :ganador, :bye, :estado, :siguiente)"
        );
        $idPorClave = [];
        foreach ($partidos as $p) {
            $siguiente = $p['ronda'] < $bracket['rondas']
                ? ($idPorClave[($p['ronda'] + 1) . '-' . intdiv($p['orden'], 2)] ?? null)
                : null;
            $insert->execute([
                ':f'         => $fiestaId,
                ':ronda'     => $p['ronda'],
                ':orden'     => $p['orden'],
                ':a'         => $p['a'],
                ':b'         => $p['b'],
                ':ganador'   => $p['ganador'],
                ':bye'       => $p['bye'] ? 1 : 0,
                ':estado'    => $p['estado'],
                ':siguiente' => $siguiente,
            ]);
            $idPorClave[$p['ronda'] . '-' . $p['orden']] = (int) $this->db->lastInsertId();
        }
    }
}
