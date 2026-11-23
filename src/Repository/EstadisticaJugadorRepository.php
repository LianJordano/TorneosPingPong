<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

final class EstadisticaJugadorRepository implements EstadisticaJugadorRepositoryInterface
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
        $regulares = $this->db->query(
            "SELECT 'TORNEO' AS origen, id, nombre, modalidad, puntos_por_victoria,
                    fecha_inicio, fecha_fin, created_at
             FROM torneo
             WHERE estado = 'FINALIZADO'
             ORDER BY COALESCE(fecha_fin, fecha_inicio, DATE(created_at)) DESC, id DESC"
        )->fetchAll();

        $fiestas = $this->db->query(
            "SELECT 'FIESTA' AS origen, f.id, f.nombre, f.modalidad, 2 AS puntos_por_victoria,
                    DATE(f.sorteado_at) AS fecha_inicio,
                    COALESCE(DATE(f.updated_at), DATE(f.sorteado_at), DATE(f.created_at)) AS fecha_fin,
                    f.created_at
             FROM fiesta f
             WHERE f.estado = 'FINALIZADO'
             ORDER BY COALESCE(DATE(f.updated_at), DATE(f.sorteado_at), DATE(f.created_at)) DESC, f.id DESC"
        )->fetchAll();

        return array_merge($regulares, $fiestas);
    }

    /** @return list<array<string,mixed>> */
    public function inscripcionesFinalizadas(): array
    {
        $regulares = $this->db->query(
            "SELECT 'TORNEO' AS origen, i.id, i.torneo_id, i.persona_id, i.persona_id_2,
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
        )->fetchAll();

        $fiestas = $this->db->query(
            "SELECT 'FIESTA' AS origen, e.id, e.fiesta_id AS torneo_id,
                    fp1.persona_id AS persona_id, fp2.persona_id AS persona_id_2,
                    f.nombre AS torneo_nombre, f.modalidad,
                    DATE(f.sorteado_at) AS fecha_inicio,
                    COALESCE(DATE(f.updated_at), DATE(f.sorteado_at), DATE(f.created_at)) AS fecha_fin,
                    f.created_at,
                    CONCAT(p1.apellido, ', ', p1.nombre) AS persona_label,
                    CONCAT(p2.apellido, ', ', p2.nombre) AS persona_2_label,
                    CASE WHEN e.participante_2_id IS NULL
                         THEN CONCAT(p1.apellido, ', ', p1.nombre)
                         ELSE CONCAT(p1.apellido, ' / ', p2.apellido) END AS label
             FROM fiesta_equipo e
             JOIN fiesta f ON f.id = e.fiesta_id AND f.estado = 'FINALIZADO'
             JOIN fiesta_participante fp1 ON fp1.id = e.participante_1_id
             JOIN persona p1 ON p1.id = fp1.persona_id
             LEFT JOIN fiesta_participante fp2 ON fp2.id = e.participante_2_id
             LEFT JOIN persona p2 ON p2.id = fp2.persona_id
             ORDER BY COALESCE(DATE(f.updated_at), DATE(f.sorteado_at), DATE(f.created_at)) DESC, f.id DESC, e.id"
        )->fetchAll();

        return array_merge($regulares, $fiestas);
    }

    /** @return list<array<string,mixed>> */
    public function partidosFinalizadosReales(): array
    {
        $regulares = $this->db->query(
            "SELECT 'TORNEO' AS origen, p.id, p.torneo_id, p.fase, p.ronda, p.orden_en_ronda, p.estado, p.tipo_resultado,
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
        )->fetchAll();

        $fiestas = $this->db->query(
            "SELECT 'FIESTA' AS origen, fp.id, fp.fiesta_id AS torneo_id, fp.fase, fp.ronda, fp.orden AS orden_en_ronda,
                    fp.estado, 'NORMAL' AS tipo_resultado,
                    fp.equipo_a_id AS inscripcion_a_id, fp.equipo_b_id AS inscripcion_b_id,
                    fp.ganador_id AS ganador_inscripcion_id,
                    f.nombre AS torneo_nombre, f.modalidad,
                    DATE(f.sorteado_at) AS fecha_inicio,
                    COALESCE(DATE(f.updated_at), DATE(f.sorteado_at), DATE(f.created_at)) AS fecha_fin,
                    f.created_at,
                    fpa1.persona_id AS a_persona_id, fpa2.persona_id AS a_persona_id_2,
                    fpb1.persona_id AS b_persona_id, fpb2.persona_id AS b_persona_id_2,
                    CASE WHEN ea.participante_2_id IS NULL
                         THEN CONCAT(pa1.apellido, ', ', pa1.nombre)
                         ELSE CONCAT(pa1.apellido, ' / ', pa2.apellido) END AS a_label,
                    CASE WHEN eb.participante_2_id IS NULL
                         THEN CONCAT(pb1.apellido, ', ', pb1.nombre)
                         ELSE CONCAT(pb1.apellido, ' / ', pb2.apellido) END AS b_label,
                    0 AS sets_a,
                    0 AS sets_b,
                    COALESCE(fp.marcador_a, 0) AS puntos_a,
                    COALESCE(fp.marcador_b, 0) AS puntos_b
             FROM fiesta_partido fp
             JOIN fiesta f ON f.id = fp.fiesta_id AND f.estado = 'FINALIZADO'
             JOIN fiesta_equipo ea ON ea.id = fp.equipo_a_id
             JOIN fiesta_equipo eb ON eb.id = fp.equipo_b_id
             JOIN fiesta_participante fpa1 ON fpa1.id = ea.participante_1_id
             JOIN persona pa1 ON pa1.id = fpa1.persona_id
             LEFT JOIN fiesta_participante fpa2 ON fpa2.id = ea.participante_2_id
             LEFT JOIN persona pa2 ON pa2.id = fpa2.persona_id
             JOIN fiesta_participante fpb1 ON fpb1.id = eb.participante_1_id
             JOIN persona pb1 ON pb1.id = fpb1.persona_id
             LEFT JOIN fiesta_participante fpb2 ON fpb2.id = eb.participante_2_id
             LEFT JOIN persona pb2 ON pb2.id = fpb2.persona_id
             WHERE fp.estado = 'FINALIZADO'
               AND fp.es_bye = 0
               AND fp.equipo_a_id IS NOT NULL
               AND fp.equipo_b_id IS NOT NULL
             ORDER BY COALESCE(DATE(f.updated_at), DATE(f.sorteado_at), DATE(f.created_at)) ASC,
                      fp.fiesta_id ASC,
                      CASE WHEN fp.fase = 'GRUPOS' THEN 0 ELSE 1 END ASC,
                      fp.ronda ASC, fp.orden ASC, fp.id ASC"
        )->fetchAll();

        return array_merge($regulares, $fiestas);
    }

    /** @return list<array{a:int,b:int,ganador:?int,setsA:int,setsB:int,puntosA:int,puntosB:int}> */
    public function ligaAgregada(int $torneoId, string $origen = 'TORNEO'): array
    {
        if ($origen === 'FIESTA') {
            $stmt = $this->db->prepare(
                "SELECT fp.equipo_a_id AS a, fp.equipo_b_id AS b, fp.ganador_id AS ganador,
                        0 AS setsA,
                        0 AS setsB,
                        COALESCE(fp.marcador_a, 0) AS puntosA,
                        COALESCE(fp.marcador_b, 0) AS puntosB
                 FROM fiesta_partido fp
                 WHERE fp.fiesta_id = :t
                   AND fp.fase = 'GRUPOS'
                   AND fp.estado = 'FINALIZADO'
                   AND fp.es_bye = 0
                   AND fp.equipo_a_id IS NOT NULL
                   AND fp.equipo_b_id IS NOT NULL
                 ORDER BY fp.ronda, fp.orden, fp.id"
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
    public function jugadoresTorneo(int $torneoId, string $origen = 'TORNEO'): array
    {
        if ($origen === 'FIESTA') {
            $stmt = $this->db->prepare(
                "SELECT e.id,
                        CASE WHEN e.participante_2_id IS NULL
                             THEN CONCAT(p1.apellido, ', ', p1.nombre)
                             ELSE CONCAT(p1.apellido, ' / ', p2.apellido) END AS nombre
                 FROM fiesta_equipo e
                 JOIN fiesta_participante fp1 ON fp1.id = e.participante_1_id
                 JOIN persona p1 ON p1.id = fp1.persona_id
                 LEFT JOIN fiesta_participante fp2 ON fp2.id = e.participante_2_id
                 LEFT JOIN persona p2 ON p2.id = fp2.persona_id
                 WHERE e.fiesta_id = :t
                 ORDER BY e.id"
            );
            $stmt->execute([':t' => $torneoId]);

            return array_map(static fn (array $r): array => [
                'id'     => (int) $r['id'],
                'nombre' => (string) $r['nombre'],
            ], $stmt->fetchAll());
        }

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
    public function bracketPodio(int $torneoId, string $origen = 'TORNEO'): array
    {
        if ($origen === 'FIESTA') {
            $stmt = $this->db->prepare(
                "SELECT equipo_a_id AS inscripcion_a_id,
                        equipo_b_id AS inscripcion_b_id,
                        ganador_id AS ganador_inscripcion_id,
                        ronda,
                        estado
                 FROM fiesta_partido
                 WHERE fiesta_id = :t AND fase = 'ELIMINATORIA'
                 ORDER BY ronda, orden, id"
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
