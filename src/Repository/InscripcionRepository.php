<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

final class InscripcionRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Inscripciones de un torneo, con los datos de la persona.
     *
     * @return list<array<string,mixed>>
     */
    public function porTorneo(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id, i.siembra, i.pago_realizado, i.persona_id,
                    p.nombre, p.apellido, ar.nombre AS area, ni.nombre AS nivel
             FROM inscripcion i
             JOIN persona p ON p.id = i.persona_id
             LEFT JOIN area ar  ON ar.id = p.area_id
             LEFT JOIN nivel ni ON ni.id = p.nivel_id
             WHERE i.torneo_id = :torneo
             ORDER BY p.apellido, p.nombre'
        );
        $stmt->execute([':torneo' => $torneoId]);
        return $stmt->fetchAll();
    }

    /**
     * Personas activas que todavía NO están inscritas en el torneo.
     *
     * @return list<array<string,mixed>>
     */
    public function personasDisponibles(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.nombre, p.apellido, ar.nombre AS area
             FROM persona p
             LEFT JOIN area ar ON ar.id = p.area_id
             WHERE p.activo = 1
               AND p.id NOT IN (SELECT persona_id FROM inscripcion WHERE torneo_id = :torneo)
             ORDER BY p.apellido, p.nombre'
        );
        $stmt->execute([':torneo' => $torneoId]);
        return $stmt->fetchAll();
    }

    public function contar(int $torneoId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM inscripcion WHERE torneo_id = :torneo');
        $stmt->execute([':torneo' => $torneoId]);
        return (int) $stmt->fetchColumn();
    }

    public function inscribir(int $torneoId, int $personaId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inscripcion (torneo_id, persona_id) VALUES (:torneo, :persona)'
        );
        $stmt->execute([':torneo' => $torneoId, ':persona' => $personaId]);
    }

    /** Desinscribe por id de inscripción, validando que pertenezca al torneo. */
    public function desinscribir(int $torneoId, int $inscripcionId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM inscripcion WHERE id = :id AND torneo_id = :torneo'
        );
        $stmt->execute([':id' => $inscripcionId, ':torneo' => $torneoId]);
    }

    public function togglePago(int $torneoId, int $inscripcionId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE inscripcion SET pago_realizado = 1 - pago_realizado
             WHERE id = :id AND torneo_id = :torneo'
        );
        $stmt->execute([':id' => $inscripcionId, ':torneo' => $torneoId]);
    }
}
