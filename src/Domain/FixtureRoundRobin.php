<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Genera el fixture de liga (todos contra todos, una sola vez) con el
 * "algoritmo del círculo" (round-robin scheduling).
 *
 * Para K jugadores produce exactamente K·(K-1)/2 partidos, repartidos en
 * jornadas (rondas). Si K es impar, se agrega un descanso (bye) que NO genera
 * partido: ese jugador descansa esa jornada.
 */
final class FixtureRoundRobin
{
    /**
     * @param list<int> $jugadores ids de inscripción
     * @return list<array{ronda:int,a:int,b:int}>
     */
    public static function generar(array $jugadores): array
    {
        $jugadores = array_values($jugadores);
        if (count($jugadores) < 2) {
            return [];
        }

        // K impar → agregamos un "fantasma" (bye) para emparejar.
        $bye = null;
        $lista = $jugadores;
        if (count($lista) % 2 !== 0) {
            $lista[] = $bye; // null = descanso
        }

        $n = count($lista);
        $rondas = $n - 1;
        $mitad = intdiv($n, 2);
        $partidos = [];

        for ($r = 0; $r < $rondas; $r++) {
            for ($i = 0; $i < $mitad; $i++) {
                $a = $lista[$i];
                $b = $lista[$n - 1 - $i];
                // El emparejamiento con el bye (null) no genera partido.
                if ($a !== null && $b !== null) {
                    $partidos[] = ['ronda' => $r + 1, 'a' => $a, 'b' => $b];
                }
            }

            // Rotación: el primero queda fijo, el resto gira en sentido horario.
            $fijo = $lista[0];
            $resto = array_slice($lista, 1);
            array_unshift($resto, array_pop($resto));
            $lista = array_merge([$fijo], $resto);
        }

        return $partidos;
    }

    /** Cantidad esperada de partidos para K jugadores: K·(K-1)/2. */
    public static function totalEsperado(int $k): int
    {
        return intdiv($k * ($k - 1), 2);
    }
}
