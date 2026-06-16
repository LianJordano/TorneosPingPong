<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Genera un cuadro de eliminación directa (single elimination) con siembra
 * estándar y byes para los mejores sembrados cuando la cantidad de clasificados
 * no es potencia de 2.
 *
 * - La siembra estándar ubica a los jugadores de modo que el 1 y el 2 solo
 *   puedan cruzarse en la final, el 1 con el 4 en semis, etc.
 * - Si N no es potencia de 2, se completa hasta la siguiente potencia con byes
 *   (descansos), que se asignan a los mejores sembrados: ese jugador avanza
 *   directo a la segunda ronda.
 *
 * Clase pura: recibe ids de inscripción ya ordenados por siembra (mejor primero)
 * y devuelve la estructura del cuadro sin tocar la base de datos.
 */
final class BracketEliminacion
{
    /**
     * @param list<int> $clasificados ids de inscripción ordenados por posición final (1° primero)
     * @return array{
     *     tamano:int,
     *     rondas:int,
     *     partidos:list<array{ronda:int,orden:int,a:?int,b:?int,estado:string,ganador:?int,bye:bool}>
     * }
     */
    public static function generar(array $clasificados): array
    {
        $clasificados = array_values($clasificados);
        $n = count($clasificados);
        if ($n < 2) {
            return ['tamano' => 0, 'rondas' => 0, 'partidos' => []];
        }

        $m = self::siguientePotenciaDe2($n);
        $rondas = (int) log($m, 2);
        $orden = self::ordenSiembra($m);

        // seed (1..M) → id de inscripción (o null si es bye)
        $seedAInsc = static fn (int $seed): ?int => $seed <= $n ? $clasificados[$seed - 1] : null;

        // Slots por ronda. Ronda 1 se llena con la siembra; el resto arranca vacío.
        $slots = [];
        for ($k = 1; $k <= $rondas; $k++) {
            $cantidad = $m >> $k;
            for ($j = 0; $j < $cantidad; $j++) {
                $slots[$k][$j] = ['a' => null, 'b' => null];
            }
        }
        for ($j = 0; $j < $m / 2; $j++) {
            $slots[1][$j]['a'] = $seedAInsc($orden[2 * $j]);
            $slots[1][$j]['b'] = $seedAInsc($orden[2 * $j + 1]);
        }

        // Resolver byes de la primera ronda y propagar al ganador a la 2da ronda.
        $byes = [];
        for ($j = 0; $j < $m / 2; $j++) {
            $a = $slots[1][$j]['a'];
            $b = $slots[1][$j]['b'];
            if (($a === null) === ($b === null)) {
                continue; // ambos reales (partido normal) o ambos nulos (no ocurre)
            }
            $ganador = $a ?? $b;
            $byes[$j] = $ganador;
            if ($rondas >= 2) {
                $padre = intdiv($j, 2);
                $ranura = $j % 2 === 0 ? 'a' : 'b';
                $slots[2][$padre][$ranura] = $ganador;
            }
        }

        // Aplanar a lista de partidos.
        $partidos = [];
        for ($k = 1; $k <= $rondas; $k++) {
            $cantidad = $m >> $k;
            for ($j = 0; $j < $cantidad; $j++) {
                $esBye = $k === 1 && isset($byes[$j]);
                $partidos[] = [
                    'ronda'   => $k,
                    'orden'   => $j,
                    'a'       => $slots[$k][$j]['a'],
                    'b'       => $slots[$k][$j]['b'],
                    'estado'  => $esBye ? 'FINALIZADO' : 'PENDIENTE',
                    'ganador' => $esBye ? $byes[$j] : null,
                    'bye'     => $esBye,
                ];
            }
        }

        return ['tamano' => $m, 'rondas' => $rondas, 'partidos' => $partidos];
    }

    /** Nombre legible de la ronda (Final, Semifinal, Cuartos…). */
    public static function nombreRonda(int $ronda, int $totalRondas): string
    {
        return match ($totalRondas - $ronda) {
            0       => 'Final',
            1       => 'Semifinal',
            2       => 'Cuartos de final',
            3       => 'Octavos de final',
            4       => 'Dieciseisavos',
            default => 'Ronda ' . $ronda,
        };
    }

    public static function siguientePotenciaDe2(int $n): int
    {
        $m = 1;
        while ($m < $n) {
            $m <<= 1;
        }
        return $m;
    }

    /**
     * Orden de siembra estándar para un cuadro de tamaño $m (potencia de 2).
     * Devuelve los seeds (1..M) en el orden de las ranuras del cuadro.
     *
     * @return list<int>
     */
    public static function ordenSiembra(int $m): array
    {
        $seeds = [1];
        while (count($seeds) < $m) {
            $limite = count($seeds) * 2 + 1;
            $siguiente = [];
            foreach ($seeds as $s) {
                $siguiente[] = $s;
                $siguiente[] = $limite - $s;
            }
            $seeds = $siguiente;
        }
        return $seeds;
    }
}
