<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

/**
 * Bitácora de cambios sensibles (quién, qué, cuándo, antes/después).
 */
final class AuditoriaRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string,mixed>|null $antes
     * @param array<string,mixed>|null $despues
     */
    public function registrar(
        string $entidad,
        ?int $entidadId,
        string $accion,
        ?int $usuarioId,
        ?array $antes = null,
        ?array $despues = null,
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO registro_auditoria (entidad, entidad_id, accion, usuario_id, valor_anterior, valor_nuevo)
             VALUES (:entidad, :entidad_id, :accion, :usuario, :antes, :despues)'
        );
        $stmt->execute([
            ':entidad'    => $entidad,
            ':entidad_id' => $entidadId,
            ':accion'     => $accion,
            ':usuario'    => $usuarioId,
            ':antes'      => $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE),
            ':despues'    => $despues === null ? null : json_encode($despues, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Bitácora de un torneo: incluye cambios del torneo y de sus partidos.
     *
     * @return list<array<string,mixed>>
     */
    public function porTorneo(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, COALESCE(u.nombre, 'Sistema') AS usuario
             FROM registro_auditoria a
             LEFT JOIN usuario u ON u.id = a.usuario_id
             WHERE (a.entidad = 'torneo' AND a.entidad_id = :tid)
                OR (a.entidad = 'partido' AND a.entidad_id IN (SELECT id FROM partido WHERE torneo_id = :tid2))
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 200"
        );
        $stmt->execute([':tid' => $torneoId, ':tid2' => $torneoId]);
        return $stmt->fetchAll();
    }
}
