<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Calcula el orden final (podio) a partir del cuadro de eliminación.
 *
 * En eliminación directa el "ranking" sale de la ronda hasta la que llegó cada
 * jugador: campeón > finalista > semifinalistas > cuartofinalistas, etc.
 *
 * Como NO hay partido por el 3er puesto, los jugadores eliminados en la misma
 * ronda se ordenan por su posición final en la liga (mejor sembrado primero).
 * Es un criterio explícito y reproducible, no un sorteo.
 *
 * Clase pura: recibe datos agregados, no toca la base de datos.
 */
final class PodioCalculator
{
    /**
     * @param list<array{a:?int,b:?int,ganador:?int,ronda:int,estado:string}> $bracket
     * @param array<int,int> $posicionLiga insc_id => posición en la tabla (menor = mejor)
     * @return list<array{insc_id:int,posicion:int,hasta_ronda:int}>
     */
    public static function calcular(array $bracket, int $totalRondas, array $posicionLiga): array
    {
        $participantes = [];
        $perdioEn = [];
        $campeon = null;

        foreach ($bracket as $p) {
            foreach (['a', 'b'] as $lado) {
                if ($p[$lado] !== null) {
                    $participantes[$p[$lado]] = true;
                }
            }
            $jugado = $p['estado'] === 'FINALIZADO' && $p['ganador'] !== null
                && $p['a'] !== null && $p['b'] !== null;
            if (!$jugado) {
                continue;
            }
            $perdedor = $p['ganador'] === $p['a'] ? $p['b'] : $p['a'];
            $perdioEn[$perdedor] = $p['ronda'];
            if ($p['ronda'] === $totalRondas) {
                $campeon = $p['ganador'];
            }
        }

        $ids = array_keys($participantes);
        $hasta = static fn (int $insc): int => $insc === $campeon
            ? $totalRondas + 1
            : ($perdioEn[$insc] ?? 0);

        usort($ids, static function (int $x, int $y) use ($hasta, $posicionLiga): int {
            $porRonda = $hasta($y) <=> $hasta($x);
            if ($porRonda !== 0) {
                return $porRonda;
            }
            return ($posicionLiga[$x] ?? PHP_INT_MAX) <=> ($posicionLiga[$y] ?? PHP_INT_MAX);
        });

        $podio = [];
        foreach ($ids as $i => $insc) {
            $podio[] = ['insc_id' => $insc, 'posicion' => $i + 1, 'hasta_ronda' => $hasta($insc)];
        }
        return $podio;
    }
}
