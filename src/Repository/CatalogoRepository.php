<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

/**
 * Base para catálogos simples (área, nivel): tabla con id + nombre único.
 *
 * El nombre de la tabla y la columna FK salen de métodos del propio código
 * (no de input del usuario), por eso es seguro interpolarlos en el SQL.
 */
abstract class CatalogoRepository
{
    public function __construct(protected PDO $db)
    {
    }

    /** Nombre de la tabla del catálogo (literal, definido en la subclase). */
    abstract protected function tabla(): string;

    /** Columna FK en la tabla persona (area_id / nivel_id). */
    abstract protected function columnaPersona(): string;

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM ' . $this->tabla() . ' ORDER BY nombre')->fetchAll();
    }

    /**
     * Catálogo con la cantidad de jugadores que lo usan.
     *
     * @return list<array<string,mixed>>
     */
    public function allConUso(): array
    {
        $sql = 'SELECT c.id, c.nombre, COUNT(p.id) AS uso
                FROM ' . $this->tabla() . ' c
                LEFT JOIN persona p ON p.' . $this->columnaPersona() . ' = c.id
                GROUP BY c.id, c.nombre
                ORDER BY c.nombre';
        return $this->db->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ' . $this->tabla() . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $nombre): int
    {
        $stmt = $this->db->prepare('INSERT INTO ' . $this->tabla() . ' (nombre) VALUES (:nombre)');
        $stmt->execute([':nombre' => $nombre]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $nombre): void
    {
        $stmt = $this->db->prepare('UPDATE ' . $this->tabla() . ' SET nombre = :nombre WHERE id = :id');
        $stmt->execute([':nombre' => $nombre, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM ' . $this->tabla() . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function nombreEnUso(string $nombre, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->tabla() . ' WHERE nombre = :nombre';
        $params = [':nombre' => $nombre];
        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptoId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** ids válidos (para validar selects). @return list<int> */
    public function ids(): array
    {
        return array_map('intval', $this->db->query('SELECT id FROM ' . $this->tabla())->fetchAll(PDO::FETCH_COLUMN));
    }
}
