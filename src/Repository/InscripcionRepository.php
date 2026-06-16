<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;
use PingPong\Domain\SorteoParejas;

final class InscripcionRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Inscripciones de un torneo, con los datos de la persona y, en dobles, del
     * compañero. `label` ya viene compuesto: "Apellido, Nombre" en individual y
     * "Apellido1 / Apellido2" en pareja.
     *
     * @return list<array<string,mixed>>
     */
    public function porTorneo(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.siembra, i.pago_realizado, i.persona_id, i.persona_id_2,
                    p.nombre, p.apellido, ar.nombre AS area, ni.nombre AS nivel,
                    p2.nombre AS nombre_2, p2.apellido AS apellido_2,
                    CASE WHEN i.persona_id_2 IS NULL
                         THEN CONCAT(p.apellido, ', ', p.nombre)
                         ELSE CONCAT(p.apellido, ' / ', p2.apellido) END AS label
             FROM inscripcion i
             JOIN persona p ON p.id = i.persona_id
             LEFT JOIN persona p2 ON p2.id = i.persona_id_2
             LEFT JOIN area ar  ON ar.id = p.area_id
             LEFT JOIN nivel ni ON ni.id = p.nivel_id
             WHERE i.torneo_id = :torneo
             ORDER BY p.apellido, p.nombre"
        );
        $stmt->execute([':torneo' => $torneoId]);
        return $stmt->fetchAll();
    }

    /**
     * Personas activas que todavía NO están inscritas en el torneo, ni como
     * titular ni como compañero/a de una pareja.
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
               AND p.id NOT IN (SELECT persona_id_2 FROM inscripcion
                                WHERE torneo_id = :torneo2 AND persona_id_2 IS NOT NULL)
             ORDER BY p.apellido, p.nombre'
        );
        $stmt->execute([':torneo' => $torneoId, ':torneo2' => $torneoId]);
        return $stmt->fetchAll();
    }

    public function contar(int $torneoId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM inscripcion WHERE torneo_id = :torneo');
        $stmt->execute([':torneo' => $torneoId]);
        return (int) $stmt->fetchColumn();
    }

    /** ¿La persona ya participa en el torneo (en cualquiera de los dos lugares)? */
    public function personaYaInscrita(int $torneoId, int $personaId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM inscripcion
             WHERE torneo_id = :torneo AND (persona_id = :p OR persona_id_2 = :p2)'
        );
        $stmt->execute([':torneo' => $torneoId, ':p' => $personaId, ':p2' => $personaId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Inscribe una persona (individual) o una pareja (dobles, con personaId2).
     */
    public function inscribir(int $torneoId, int $personaId, ?int $personaId2 = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inscripcion (torneo_id, persona_id, persona_id_2)
             VALUES (:torneo, :persona, :persona2)'
        );
        $stmt->execute([
            ':torneo'   => $torneoId,
            ':persona'  => $personaId,
            ':persona2' => $personaId2,
        ]);
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

    /** Inscripciones individuales (sin compañero) de un torneo. */
    public function individuales(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, persona_id FROM inscripcion
             WHERE torneo_id = :torneo AND persona_id_2 IS NULL
             ORDER BY id'
        );
        $stmt->execute([':torneo' => $torneoId]);
        return $stmt->fetchAll();
    }

    /**
     * Sortea las inscripciones individuales del torneo en parejas. Por cada par
     * conserva la primera inscripción y le asigna como compañero a la persona de
     * la segunda, que luego se elimina. Devuelve la cantidad de parejas armadas y
     * el id de inscripción que quedó sin pareja (impar), o null si todas casaron.
     *
     * Es seguro fusionar inscripciones acá porque el sorteo ocurre antes de
     * generar el fixture: todavía no hay partidos que las referencien.
     *
     * @param (callable(list<int>):list<int>)|null $barajador
     * @return array{parejas:int,sobrante:?int}
     */
    public function sortearParejas(int $torneoId, ?callable $barajador = null): array
    {
        $individuales = $this->individuales($torneoId);
        $personaPorInscripcion = [];
        foreach ($individuales as $row) {
            $personaPorInscripcion[(int) $row['id']] = (int) $row['persona_id'];
        }
        $ids = array_keys($personaPorInscripcion);

        $sorteo = SorteoParejas::emparejar($ids, $barajador);

        $asignar = $this->db->prepare(
            'UPDATE inscripcion SET persona_id_2 = :persona2 WHERE id = :id AND torneo_id = :torneo'
        );
        $borrar = $this->db->prepare(
            'DELETE FROM inscripcion WHERE id = :id AND torneo_id = :torneo'
        );

        $this->db->beginTransaction();
        try {
            foreach ($sorteo['parejas'] as [$inscA, $inscB]) {
                $asignar->execute([
                    ':persona2' => $personaPorInscripcion[$inscB],
                    ':id'       => $inscA,
                    ':torneo'   => $torneoId,
                ]);
                $borrar->execute([':id' => $inscB, ':torneo' => $torneoId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['parejas' => count($sorteo['parejas']), 'sobrante' => $sorteo['sobrante']];
    }
}
