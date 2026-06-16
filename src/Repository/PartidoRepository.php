<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;
use PingPong\Domain\ReglasMarcador;

final class PartidoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function existenLiga(int $torneoId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM partido WHERE torneo_id = :t AND fase = 'LIGA'");
        $stmt->execute([':t' => $torneoId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea todos los partidos de liga en una transacción.
     *
     * @param list<array{ronda:int,a:int,b:int}> $partidos
     */
    public function crearLiga(int $torneoId, array $partidos): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO partido (torneo_id, fase, ronda, inscripcion_a_id, inscripcion_b_id, estado)
             VALUES (:t, 'LIGA', :ronda, :a, :b, 'PENDIENTE')"
        );

        $this->db->beginTransaction();
        try {
            foreach ($partidos as $p) {
                $stmt->execute([':t' => $torneoId, ':ronda' => $p['ronda'], ':a' => $p['a'], ':b' => $p['b']]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Partidos de liga de un torneo, con etiquetas de jugador y sets cargados.
     *
     * @return list<array<string,mixed>>
     */
    public function liga(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*,
                    CONCAT(pa.apellido, ', ', pa.nombre) AS a_label,
                    CONCAT(pb.apellido, ', ', pb.nombre) AS b_label
             FROM partido p
             LEFT JOIN inscripcion ia ON ia.id = p.inscripcion_a_id
             LEFT JOIN persona pa     ON pa.id = ia.persona_id
             LEFT JOIN inscripcion ib ON ib.id = p.inscripcion_b_id
             LEFT JOIN persona pb     ON pb.id = ib.persona_id
             WHERE p.torneo_id = :t AND p.fase = 'LIGA'
             ORDER BY p.ronda, p.id"
        );
        $stmt->execute([':t' => $torneoId]);
        $partidos = $stmt->fetchAll();

        $sets = $this->setsPorTorneo($torneoId);
        foreach ($partidos as &$p) {
            $p['sets'] = $sets[(int) $p['id']] ?? [];
        }
        unset($p);

        return $partidos;
    }

    /** @return array<string,mixed>|null */
    public function find(int $partidoId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*,
                    CONCAT(pa.apellido, ', ', pa.nombre) AS a_label,
                    CONCAT(pb.apellido, ', ', pb.nombre) AS b_label
             FROM partido p
             LEFT JOIN inscripcion ia ON ia.id = p.inscripcion_a_id
             LEFT JOIN persona pa     ON pa.id = ia.persona_id
             LEFT JOIN inscripcion ib ON ib.id = p.inscripcion_b_id
             LEFT JOIN persona pb     ON pb.id = ib.persona_id
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $partidoId]);
        $partido = $stmt->fetch();
        if ($partido === false) {
            return null;
        }
        $partido['sets'] = $this->setsDe($partidoId);
        return $partido;
    }

    /** @return list<array{numero_set:int,puntos_a:int,puntos_b:int}> */
    public function setsDe(int $partidoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT numero_set, puntos_a, puntos_b FROM partido_set WHERE partido_id = :p ORDER BY numero_set'
        );
        $stmt->execute([':p' => $partidoId]);
        return array_map(
            static fn (array $r): array => [
                'numero_set' => (int) $r['numero_set'],
                'puntos_a'   => (int) $r['puntos_a'],
                'puntos_b'   => (int) $r['puntos_b'],
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * Guarda el marcador de un partido: reemplaza sets, fija ganador y estado.
     *
     * @param list<array{0:int,1:int}> $sets
     */
    public function guardarMarcador(int $partidoId, array $sets, int $ganadorInscripcionId, string $tipoResultado = 'NORMAL'): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM partido_set WHERE partido_id = :p');
            $del->execute([':p' => $partidoId]);

            $ins = $this->db->prepare(
                'INSERT INTO partido_set (partido_id, numero_set, puntos_a, puntos_b)
                 VALUES (:p, :n, :a, :b)'
            );
            foreach ($sets as $i => [$a, $b]) {
                $ins->execute([':p' => $partidoId, ':n' => $i + 1, ':a' => $a, ':b' => $b]);
            }

            $upd = $this->db->prepare(
                "UPDATE partido
                 SET estado = 'FINALIZADO', tipo_resultado = :tipo, ganador_inscripcion_id = :g
                 WHERE id = :p"
            );
            $upd->execute([':tipo' => $tipoResultado, ':g' => $ganadorInscripcionId, ':p' => $partidoId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Partidos de liga FINALIZADOS, agregados para la tabla de posiciones.
     *
     * @return list<array{a:int,b:int,ganador:?int,setsA:int,setsB:int,puntosA:int,puntosB:int}>
     */
    public function ligaAgregada(int $torneoId): array
    {
        $agg = [];
        foreach ($this->liga($torneoId) as $p) {
            if ($p['estado'] !== 'FINALIZADO') {
                continue;
            }
            $resumen = ReglasMarcador::resumen(
                array_map(static fn (array $s): array => [(int) $s['puntos_a'], (int) $s['puntos_b']], $p['sets'])
            );
            $agg[] = [
                'a'       => (int) $p['inscripcion_a_id'],
                'b'       => (int) $p['inscripcion_b_id'],
                'ganador' => $p['ganador_inscripcion_id'] !== null ? (int) $p['ganador_inscripcion_id'] : null,
                'setsA'   => $resumen['setsA'],
                'setsB'   => $resumen['setsB'],
                'puntosA' => $resumen['puntosA'],
                'puntosB' => $resumen['puntosB'],
            ];
        }
        return $agg;
    }

    public function existeBracket(int $torneoId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM partido WHERE torneo_id = :t AND fase = 'ELIMINATORIA'");
        $stmt->execute([':t' => $torneoId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Persiste el cuadro de eliminación. Inserta de la última ronda a la
     * primera para que cada partido pueda referenciar al siguiente (padre).
     *
     * @param array{tamano:int,rondas:int,partidos:list<array{ronda:int,orden:int,a:?int,b:?int,estado:string,ganador:?int,bye:bool}>} $bracket
     */
    public function crearBracket(int $torneoId, array $bracket): void
    {
        $rondas = $bracket['rondas'];

        // Ordenar de la ronda final (mayor) hacia la primera.
        $partidos = $bracket['partidos'];
        usort($partidos, static fn (array $x, array $y): int => $y['ronda'] <=> $x['ronda'] ?: $x['orden'] <=> $y['orden']);

        $insert = $this->db->prepare(
            "INSERT INTO partido
                (torneo_id, fase, ronda, orden_en_ronda, inscripcion_a_id, inscripcion_b_id,
                 estado, ganador_inscripcion_id, partido_siguiente_id)
             VALUES (:t, 'ELIMINATORIA', :ronda, :orden, :a, :b, :estado, :ganador, :siguiente)"
        );

        $this->db->beginTransaction();
        try {
            $idPorClave = []; // "ronda-orden" => id
            foreach ($partidos as $p) {
                $siguiente = null;
                if ($p['ronda'] < $rondas) {
                    $claveSiguiente = ($p['ronda'] + 1) . '-' . intdiv($p['orden'], 2);
                    $siguiente = $idPorClave[$claveSiguiente] ?? null;
                }
                $insert->execute([
                    ':t'         => $torneoId,
                    ':ronda'     => $p['ronda'],
                    ':orden'     => $p['orden'],
                    ':a'         => $p['a'],
                    ':b'         => $p['b'],
                    ':estado'    => $p['estado'],
                    ':ganador'   => $p['ganador'],
                    ':siguiente' => $siguiente,
                ]);
                $idPorClave[$p['ronda'] . '-' . $p['orden']] = (int) $this->db->lastInsertId();
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Partidos del bracket con etiquetas de jugador y sets, ordenados por ronda.
     *
     * @return list<array<string,mixed>>
     */
    public function bracket(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*,
                    CONCAT(pa.apellido, ', ', pa.nombre) AS a_label,
                    CONCAT(pb.apellido, ', ', pb.nombre) AS b_label
             FROM partido p
             LEFT JOIN inscripcion ia ON ia.id = p.inscripcion_a_id
             LEFT JOIN persona pa     ON pa.id = ia.persona_id
             LEFT JOIN inscripcion ib ON ib.id = p.inscripcion_b_id
             LEFT JOIN persona pb     ON pb.id = ib.persona_id
             WHERE p.torneo_id = :t AND p.fase = 'ELIMINATORIA'
             ORDER BY p.ronda, p.orden_en_ronda"
        );
        $stmt->execute([':t' => $torneoId]);
        $partidos = $stmt->fetchAll();

        $sets = $this->setsPorTorneo($torneoId);
        foreach ($partidos as &$p) {
            $p['sets'] = $sets[(int) $p['id']] ?? [];
        }
        unset($p);

        return $partidos;
    }

    /** Coloca al ganador en la ranura indicada del partido siguiente. */
    public function avanzarGanador(int $partidoSiguienteId, string $ranura, int $ganadorInscripcionId): void
    {
        $columna = $ranura === 'a' ? 'inscripcion_a_id' : 'inscripcion_b_id';
        $stmt = $this->db->prepare("UPDATE partido SET {$columna} = :g WHERE id = :id");
        $stmt->execute([':g' => $ganadorInscripcionId, ':id' => $partidoSiguienteId]);
    }

    /** @return array{total:int,finalizados:int} */
    public function progresoLiga(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(estado = 'FINALIZADO') AS finalizados
             FROM partido WHERE torneo_id = :t AND fase = 'LIGA'"
        );
        $stmt->execute([':t' => $torneoId]);
        $row = $stmt->fetch();
        return ['total' => (int) ($row['total'] ?? 0), 'finalizados' => (int) ($row['finalizados'] ?? 0)];
    }

    /**
     * Sets de todos los partidos del torneo (liga y bracket), agrupados por
     * partido_id. Cada consumidor solo busca los ids que le interesan.
     *
     * @return array<int,list<array{numero_set:int,puntos_a:int,puntos_b:int}>>
     */
    private function setsPorTorneo(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ps.partido_id, ps.numero_set, ps.puntos_a, ps.puntos_b
             FROM partido_set ps
             JOIN partido p ON p.id = ps.partido_id
             WHERE p.torneo_id = :t
             ORDER BY ps.partido_id, ps.numero_set"
        );
        $stmt->execute([':t' => $torneoId]);

        $map = [];
        foreach ($stmt->fetchAll() as $r) {
            $map[(int) $r['partido_id']][] = [
                'numero_set' => (int) $r['numero_set'],
                'puntos_a'   => (int) $r['puntos_a'],
                'puntos_b'   => (int) $r['puntos_b'],
            ];
        }
        return $map;
    }
}
