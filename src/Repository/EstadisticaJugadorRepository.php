<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

final class EstadisticaJugadorRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function personas(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*, ar.nombre AS area, ni.nombre AS nivel
             FROM persona p
             LEFT JOIN area ar ON ar.id = p.area_id
             LEFT JOIN nivel ni ON ni.id = p.nivel_id
             ORDER BY p.apellido, p.nombre'
        );
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function torneosFinalizados(): array
    {
        $stmt = $this->db->query(
            "SELECT *
             FROM torneo
             WHERE estado = 'FINALIZADO'
             ORDER BY COALESCE(fecha_fin, fecha_inicio, DATE(created_at)) DESC, id DESC"
        );
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function inscripcionesFinalizadas(): array
    {
        $stmt = $this->db->query(
            "SELECT i.id, i.torneo_id, i.persona_id, i.persona_id_2,
                    t.nombre AS torneo_nombre, t.modalidad, t.fecha_inicio, t.fecha_fin, t.created_at,
                    CONCAT(p.apellido, ', ', p.nombre) AS persona_label,
                    CONCAT(p2.apellido, ', ', p2.nombre) AS persona_2_label,
                    CASE WHEN i.persona_id_2 IS NULL
                         THEN CONCAT(p.apellido, ', ', p.nombre)
                         ELSE CONCAT(p.apellido, ' / ', p2.apellido) END AS label
             FROM inscripcion i
             JOIN torneo t ON t.id = i.torneo_id AND t.estado = 'FINALIZADO'
             JOIN persona p ON p.id = i.persona_id
             LEFT JOIN persona p2 ON p2.id = i.persona_id_2
             ORDER BY COALESCE(t.fecha_fin, t.fecha_inicio, DATE(t.created_at)) DESC, t.id DESC, i.id"
        );
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function partidosFinalizadosReales(): array
    {
        $stmt = $this->db->query(
            "SELECT p.id, p.torneo_id, p.fase, p.ronda, p.orden_en_ronda, p.estado, p.tipo_resultado,
                    p.inscripcion_a_id, p.inscripcion_b_id, p.ganador_inscripcion_id,
                    t.nombre AS torneo_nombre, t.modalidad, t.fecha_inicio, t.fecha_fin, t.created_at,
                    ia.persona_id AS a_persona_id, ia.persona_id_2 AS a_persona_id_2,
                    ib.persona_id AS b_persona_id, ib.persona_id_2 AS b_persona_id_2,
                    CASE WHEN ia.persona_id_2 IS NULL
                         THEN CONCAT(pa.apellido, ', ', pa.nombre)
                         ELSE CONCAT(pa.apellido, ' / ', pa2.apellido) END AS a_label,
                    CASE WHEN ib.persona_id_2 IS NULL
                         THEN CONCAT(pb.apellido, ', ', pb.nombre)
                         ELSE CONCAT(pb.apellido, ' / ', pb2.apellido) END AS b_label,
                    COALESCE(s.sets_a, 0) AS sets_a,
                    COALESCE(s.sets_b, 0) AS sets_b,
                    COALESCE(s.puntos_a, 0) AS puntos_a,
                    COALESCE(s.puntos_b, 0) AS puntos_b
             FROM partido p
             JOIN torneo t ON t.id = p.torneo_id AND t.estado = 'FINALIZADO'
             JOIN inscripcion ia ON ia.id = p.inscripcion_a_id
             JOIN inscripcion ib ON ib.id = p.inscripcion_b_id
             JOIN persona pa ON pa.id = ia.persona_id
             LEFT JOIN persona pa2 ON pa2.id = ia.persona_id_2
             JOIN persona pb ON pb.id = ib.persona_id
             LEFT JOIN persona pb2 ON pb2.id = ib.persona_id_2
             LEFT JOIN (
                SELECT partido_id,
                       SUM(CASE WHEN puntos_a > puntos_b THEN 1 ELSE 0 END) AS sets_a,
                       SUM(CASE WHEN puntos_b > puntos_a THEN 1 ELSE 0 END) AS sets_b,
                       SUM(puntos_a) AS puntos_a,
                       SUM(puntos_b) AS puntos_b
                FROM partido_set
                GROUP BY partido_id
             ) s ON s.partido_id = p.id
             WHERE p.estado = 'FINALIZADO'
               AND p.inscripcion_a_id IS NOT NULL
               AND p.inscripcion_b_id IS NOT NULL
             ORDER BY COALESCE(t.fecha_fin, t.fecha_inicio, DATE(t.created_at)) ASC, p.torneo_id ASC, p.fase ASC, p.ronda ASC, p.orden_en_ronda ASC, p.id ASC"
        );
        return $stmt->fetchAll();
    }

    /** @return list<array{a:int,b:int,ganador:?int,setsA:int,setsB:int,puntosA:int,puntosB:int}> */
    public function ligaAgregada(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.inscripcion_a_id AS a, p.inscripcion_b_id AS b, p.ganador_inscripcion_id AS ganador,
                    COALESCE(s.setsA, 0) AS setsA,
                    COALESCE(s.setsB, 0) AS setsB,
                    COALESCE(s.puntosA, 0) AS puntosA,
                    COALESCE(s.puntosB, 0) AS puntosB
             FROM partido p
             LEFT JOIN (
                SELECT partido_id,
                       SUM(CASE WHEN puntos_a > puntos_b THEN 1 ELSE 0 END) AS setsA,
                       SUM(CASE WHEN puntos_b > puntos_a THEN 1 ELSE 0 END) AS setsB,
                       SUM(puntos_a) AS puntosA,
                       SUM(puntos_b) AS puntosB
                FROM partido_set
                GROUP BY partido_id
             ) s ON s.partido_id = p.id
             WHERE p.torneo_id = :t
               AND p.fase = 'LIGA'
               AND p.estado = 'FINALIZADO'
               AND p.inscripcion_a_id IS NOT NULL
               AND p.inscripcion_b_id IS NOT NULL
             ORDER BY p.ronda, p.id"
        );
        $stmt->execute([':t' => $torneoId]);

        return array_map(static fn (array $r): array => [
            'a'       => (int) $r['a'],
            'b'       => (int) $r['b'],
            'ganador' => $r['ganador'] !== null ? (int) $r['ganador'] : null,
            'setsA'   => (int) $r['setsA'],
            'setsB'   => (int) $r['setsB'],
            'puntosA' => (int) $r['puntosA'],
            'puntosB' => (int) $r['puntosB'],
        ], $stmt->fetchAll());
    }

    /** @return list<array{id:int,nombre:string}> */
    public function jugadoresTorneo(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id,
                    CASE WHEN i.persona_id_2 IS NULL
                         THEN CONCAT(p.apellido, ', ', p.nombre)
                         ELSE CONCAT(p.apellido, ' / ', p2.apellido) END AS nombre
             FROM inscripcion i
             JOIN persona p ON p.id = i.persona_id
             LEFT JOIN persona p2 ON p2.id = i.persona_id_2
             WHERE i.torneo_id = :t
             ORDER BY i.id"
        );
        $stmt->execute([':t' => $torneoId]);

        return array_map(static fn (array $r): array => [
            'id'     => (int) $r['id'],
            'nombre' => (string) $r['nombre'],
        ], $stmt->fetchAll());
    }

    /** @return list<array{a:?int,b:?int,ganador:?int,ronda:int,estado:string}> */
    public function bracketPodio(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT inscripcion_a_id, inscripcion_b_id, ganador_inscripcion_id, ronda, estado
             FROM partido
             WHERE torneo_id = :t AND fase = 'ELIMINATORIA'
             ORDER BY ronda, orden_en_ronda, id"
        );
        $stmt->execute([':t' => $torneoId]);

        return array_map(static fn (array $r): array => [
            'a'       => $r['inscripcion_a_id'] !== null ? (int) $r['inscripcion_a_id'] : null,
            'b'       => $r['inscripcion_b_id'] !== null ? (int) $r['inscripcion_b_id'] : null,
            'ganador' => $r['ganador_inscripcion_id'] !== null ? (int) $r['ganador_inscripcion_id'] : null,
            'ronda'   => (int) $r['ronda'],
            'estado'  => (string) $r['estado'],
        ], $stmt->fetchAll());
    }
}
