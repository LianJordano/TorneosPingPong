<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

final class PersonaRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(bool $incluirInactivas = false): array
    {
        $sql = 'SELECT * FROM persona';
        if (!$incluirInactivas) {
            $sql .= ' WHERE activo = 1';
        }
        $sql .= ' ORDER BY apellido, nombre';
        return $this->db->query($sql)->fetchAll();
    }

    /** Columnas por las que se puede ordenar (whitelist anti-inyección). */
    public const ORDEN_COLUMNAS = [
        'nombre'        => 'p.apellido, p.nombre',
        'area'          => 'ar.nombre',
        'nivel'         => 'ni.nombre',
        'identificador' => 'p.identificador_interno',
        'estado'        => 'p.activo',
    ];

    /**
     * Listado paginado con búsqueda, ordenamiento y nombres de área/nivel.
     *
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function paginar(
        string $q,
        bool $incluirInactivas,
        int $pagina,
        int $porPagina,
        string $orden = 'nombre',
        string $dir = 'asc',
    ): array {
        $where = [];
        $params = [];

        if (!$incluirInactivas) {
            $where[] = 'p.activo = 1';
        }
        $q = trim($q);
        if ($q !== '') {
            // CONCAT_WS ignora NULL y usa UN solo placeholder (con prepared
            // statements reales NO se puede reutilizar el mismo :nombre).
            $where[] = "CONCAT_WS(' ', p.nombre, p.apellido, p.identificador_interno, ar.nombre, ni.nombre, p.email) LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }
        $clausula = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $joins = ' FROM persona p
                   LEFT JOIN area ar  ON ar.id = p.area_id
                   LEFT JOIN nivel ni ON ni.id = p.nivel_id';

        $stmtCount = $this->db->prepare('SELECT COUNT(*)' . $joins . $clausula);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Ordenamiento: columna validada contra whitelist; dirección normalizada.
        $columna = self::ORDEN_COLUMNAS[$orden] ?? self::ORDEN_COLUMNAS['nombre'];
        $direccion = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        // LIMIT/OFFSET casteados a int (MySQL nativo no acepta bind de LIMIT).
        $porPagina = max(1, $porPagina);
        $offset = max(0, ($pagina - 1) * $porPagina);

        $sql = 'SELECT p.*, ar.nombre AS area, ni.nombre AS nivel' . $joins . $clausula
             . ' ORDER BY ' . $columna . ' ' . $direccion . ', p.apellido, p.nombre'
             . ' LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM persona WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO persona (nombre, apellido, identificador_interno, area_id, email, telefono, nivel_id)
             VALUES (:nombre, :apellido, :identificador, :area_id, :email, :telefono, :nivel_id)'
        );
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE persona SET nombre = :nombre, apellido = :apellido,
                identificador_interno = :identificador, area_id = :area_id,
                email = :email, telefono = :telefono, nivel_id = :nivel_id
             WHERE id = :id'
        );
        $stmt->execute($this->bind($data) + [':id' => $id]);
    }

    /** Baja lógica: no se borra, se desactiva. */
    public function desactivar(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE persona SET activo = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function activar(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE persona SET activo = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** ¿El identificador interno ya está usado por OTRA persona? */
    public function identificadorEnUso(string $identificador, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM persona WHERE identificador_interno = :ident';
        $params = [':ident' => $identificador];
        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptoId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function bind(array $data): array
    {
        $nullIfEmpty = static fn (mixed $v): ?string => ($v === null || trim((string) $v) === '')
            ? null
            : trim((string) $v);
        $idOrNull = static fn (mixed $v): ?int => ($v === null || trim((string) $v) === '' || !ctype_digit(trim((string) $v)))
            ? null
            : (int) $v;

        return [
            ':nombre'        => trim((string) ($data['nombre'] ?? '')),
            ':apellido'      => trim((string) ($data['apellido'] ?? '')),
            ':identificador' => $nullIfEmpty($data['identificador_interno'] ?? null),
            ':area_id'       => $idOrNull($data['area_id'] ?? null),
            ':email'         => $nullIfEmpty($data['email'] ?? null),
            ':telefono'      => $nullIfEmpty($data['telefono'] ?? null),
            ':nivel_id'      => $idOrNull($data['nivel_id'] ?? null),
        ];
    }
}
