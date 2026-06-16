<?php

declare(strict_types=1);

namespace PingPong\Repository;

use PDO;

final class PremioRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Distribución de premios del torneo, indexada por posición.
     *
     * @return array<int,array{posicion:int,monto:?string,porcentaje:?string}>
     */
    public function porTorneo(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT posicion, monto, porcentaje FROM premio_config WHERE torneo_id = :t ORDER BY posicion'
        );
        $stmt->execute([':t' => $torneoId]);

        $premios = [];
        foreach ($stmt->fetchAll() as $row) {
            $premios[(int) $row['posicion']] = [
                'posicion'   => (int) $row['posicion'],
                'monto'      => $row['monto'],
                'porcentaje' => $row['porcentaje'],
            ];
        }
        return $premios;
    }

    /**
     * Reemplaza la distribución completa del torneo.
     *
     * @param array<int,float> $porcentajePorPosicion posición => porcentaje
     */
    public function reemplazarPorcentajes(int $torneoId, array $porcentajePorPosicion): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM premio_config WHERE torneo_id = :t');
            $del->execute([':t' => $torneoId]);

            $ins = $this->db->prepare(
                'INSERT INTO premio_config (torneo_id, posicion, porcentaje) VALUES (:t, :pos, :pct)'
            );
            foreach ($porcentajePorPosicion as $posicion => $porcentaje) {
                $ins->execute([':t' => $torneoId, ':pos' => $posicion, ':pct' => $porcentaje]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
