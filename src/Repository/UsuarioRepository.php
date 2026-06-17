<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

final class UsuarioRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuario WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query(
            'SELECT id, nombre, email, rol, activo, created_at FROM usuario ORDER BY nombre, email'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuario WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function actualizarRol(int $id, string $rol): void
    {
        $stmt = $this->db->prepare('UPDATE usuario SET rol = :rol WHERE id = :id');
        $stmt->execute([':rol' => $rol, ':id' => $id]);
    }

    /** Cantidad de administradores activos (para evitar quedarse sin ninguno). */
    public function contarAdminsActivos(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM usuario WHERE rol = 'ADMIN' AND activo = 1"
        )->fetchColumn();
    }

    public function crear(string $nombre, string $email, string $passwordHash, string $rol): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuario (nombre, email, password_hash, rol, activo)
             VALUES (:nombre, :email, :hash, :rol, 1)'
        );
        $stmt->execute([':nombre' => $nombre, ':email' => $email, ':hash' => $passwordHash, ':rol' => $rol]);
        return (int) $this->db->lastInsertId();
    }

    /** Actualiza credenciales y rol, reactivando la cuenta (usado por el script de admin). */
    public function actualizarCredenciales(int $id, string $nombre, string $passwordHash, string $rol): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuario SET nombre = :nombre, password_hash = :hash, rol = :rol, activo = 1 WHERE id = :id'
        );
        $stmt->execute([':nombre' => $nombre, ':hash' => $passwordHash, ':rol' => $rol, ':id' => $id]);
    }
}
