<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

final class TorneoRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(bool $incluirArchivados = false): array
    {
        $sql = 'SELECT t.*, (SELECT COUNT(*) FROM inscripcion i WHERE i.torneo_id = t.id) AS inscritos
                FROM torneo t';
        if (!$incluirArchivados) {
            $sql .= ' WHERE t.archivado = 0';
        }
        $sql .= ' ORDER BY t.created_at DESC';
        return $this->db->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM torneo WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO torneo
                (nombre, descripcion, lugar, fecha_inicio, fecha_fin,
                 modalidad, emparejamiento,
                 formato_sets_liga, formato_sets_bracket, puntos_por_set, diferencia_minima,
                 puntos_por_victoria, num_clasificados, posiciones_premiadas,
                 cuota_inscripcion, pozo_total)
             VALUES
                (:nombre, :descripcion, :lugar, :fecha_inicio, :fecha_fin,
                 :modalidad, :emparejamiento,
                 :formato_liga, :formato_bracket, :puntos_set, :diferencia,
                 :puntos_victoria, :num_clasificados, :posiciones_premiadas,
                 :cuota, :pozo)'
        );
        $stmt->execute($this->bind($data));
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE torneo SET
                nombre = :nombre, descripcion = :descripcion, lugar = :lugar,
                fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin,
                modalidad = :modalidad, emparejamiento = :emparejamiento,
                formato_sets_liga = :formato_liga, formato_sets_bracket = :formato_bracket,
                puntos_por_set = :puntos_set, diferencia_minima = :diferencia,
                puntos_por_victoria = :puntos_victoria, num_clasificados = :num_clasificados,
                posiciones_premiadas = :posiciones_premiadas,
                cuota_inscripcion = :cuota, pozo_total = :pozo
             WHERE id = :id'
        );
        $stmt->execute($this->bind($data) + [':id' => $id]);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE torneo SET estado = :estado WHERE id = :id');
        $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    public function archivar(int $id, bool $archivado = true): void
    {
        $stmt = $this->db->prepare('UPDATE torneo SET archivado = :a WHERE id = :id');
        $stmt->execute([':a' => $archivado ? 1 : 0, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM torneo WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Duplica configuración y premios en un nuevo torneo en BORRADOR. */
    public function duplicar(int $id): ?int
    {
        $origen = $this->find($id);
        if ($origen === null) {
            return null;
        }

        $this->db->beginTransaction();
        try {
            $nuevoId = $this->create([
                'nombre'               => $origen['nombre'] . ' (copia)',
                'descripcion'          => $origen['descripcion'],
                'lugar'                => $origen['lugar'],
                'fecha_inicio'         => null,
                'fecha_fin'            => null,
                'modalidad'            => $origen['modalidad'],
                'emparejamiento'       => $origen['emparejamiento'],
                'formato_sets_liga'    => $origen['formato_sets_liga'],
                'formato_sets_bracket' => $origen['formato_sets_bracket'],
                'puntos_por_set'       => $origen['puntos_por_set'],
                'diferencia_minima'    => $origen['diferencia_minima'],
                'puntos_por_victoria'  => $origen['puntos_por_victoria'],
                'num_clasificados'     => $origen['num_clasificados'],
                'posiciones_premiadas' => $origen['posiciones_premiadas'],
                'cuota_inscripcion'    => $origen['cuota_inscripcion'],
                'pozo_total'           => $origen['pozo_total'],
            ]);

            $premios = $this->db->prepare('SELECT posicion, monto, porcentaje FROM premio_config WHERE torneo_id = :id');
            $premios->execute([':id' => $id]);
            $insert = $this->db->prepare(
                'INSERT INTO premio_config (torneo_id, posicion, monto, porcentaje)
                 VALUES (:torneo, :posicion, :monto, :porcentaje)'
            );
            foreach ($premios->fetchAll() as $p) {
                $insert->execute([
                    ':torneo'     => $nuevoId,
                    ':posicion'   => $p['posicion'],
                    ':monto'      => $p['monto'],
                    ':porcentaje' => $p['porcentaje'],
                ]);
            }

            $this->db->commit();
            return $nuevoId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
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

        $modalidad = ($data['modalidad'] ?? 'INDIVIDUAL') === 'DOBLES' ? 'DOBLES' : 'INDIVIDUAL';
        $emparejamiento = ($data['emparejamiento'] ?? 'MANUAL') === 'ALEATORIO' ? 'ALEATORIO' : 'MANUAL';

        return [
            ':nombre'               => trim((string) ($data['nombre'] ?? '')),
            ':descripcion'          => $nullIfEmpty($data['descripcion'] ?? null),
            ':lugar'                => $nullIfEmpty($data['lugar'] ?? null),
            ':fecha_inicio'         => $nullIfEmpty($data['fecha_inicio'] ?? null),
            ':fecha_fin'            => $nullIfEmpty($data['fecha_fin'] ?? null),
            ':modalidad'            => $modalidad,
            ':emparejamiento'       => $emparejamiento,
            ':formato_liga'         => (int) ($data['formato_sets_liga'] ?? 3),
            ':formato_bracket'      => (int) ($data['formato_sets_bracket'] ?? 5),
            ':puntos_set'           => (int) ($data['puntos_por_set'] ?? 11),
            ':diferencia'           => (int) ($data['diferencia_minima'] ?? 2),
            ':puntos_victoria'      => (int) ($data['puntos_por_victoria'] ?? 1),
            ':num_clasificados'     => (int) ($data['num_clasificados'] ?? 8),
            ':posiciones_premiadas' => (int) ($data['posiciones_premiadas'] ?? 3),
            ':cuota'                => $nullIfEmpty($data['cuota_inscripcion'] ?? null),
            ':pozo'                 => $nullIfEmpty($data['pozo_total'] ?? null),
        ];
    }
}
