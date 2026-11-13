<?php

declare(strict_types=1);

namespace PingPong\Domain\Fiesta;

use PingPong\Domain\BracketEliminacion;

/**
 * Group standings for a party tournament and the crosswise seeding of the
 * group qualifiers into the knockout bracket.
 *
 * Order: wins, then score difference, then score in favour, then the result
 * between the two tied teams (if they played), then team number.
 */
final class TablaGrupoFiesta
{
    /**
     * @param list<int> $equipos team ids in the group
     * @param list<array{a:int,b:int,ma:int,mb:int,ganador:int}> $partidos finished group matches
     * @return list<array{equipo:int,posicion:int,pj:int,pg:int,pp:int,favor:int,contra:int,diferencia:int}>
     */
    public static function calcular(array $equipos, array $partidos): array
    {
        $filas = [];
        foreach ($equipos as $id) {
            $filas[$id] = ['equipo' => $id, 'posicion' => 0, 'pj' => 0, 'pg' => 0, 'pp' => 0, 'favor' => 0, 'contra' => 0, 'diferencia' => 0];
        }

        $directo = [];
        foreach ($partidos as $p) {
            if (!isset($filas[$p['a']], $filas[$p['b']])) {
                continue;
            }
            foreach ([[$p['a'], $p['ma'], $p['mb']], [$p['b'], $p['mb'], $p['ma']]] as [$id, $favor, $contra]) {
                $filas[$id]['pj']++;
                $filas[$id]['favor'] += $favor;
                $filas[$id]['contra'] += $contra;
                $filas[$id]['diferencia'] = $filas[$id]['favor'] - $filas[$id]['contra'];
                if ($p['ganador'] === $id) {
                    $filas[$id]['pg']++;
                } else {
                    $filas[$id]['pp']++;
                }
            }
            $directo[$p['a'] . '-' . $p['b']] = $p['ganador'];
            $directo[$p['b'] . '-' . $p['a']] = $p['ganador'];
        }

        $filas = array_values($filas);
        usort($filas, static function (array $x, array $y) use ($directo): int {
            $cmp = [$y['pg'], $y['diferencia'], $y['favor']] <=> [$x['pg'], $x['diferencia'], $x['favor']];
            if ($cmp !== 0) {
                return $cmp;
            }
            $ganador = $directo[$x['equipo'] . '-' . $y['equipo']] ?? null;
            if ($ganador !== null) {
                return $ganador === $x['equipo'] ? -1 : 1;
            }
            return $x['equipo'] <=> $y['equipo'];
        });

        foreach ($filas as $i => &$fila) {
            $fila['posicion'] = $i + 1;
        }
        unset($fila);

        return $filas;
    }

    /**
     * Seed order for the knockout: all group winners first, then runners-up, etc.
     * Lower tiers are rotated/reversed until no first-round match pits two teams
     * of the same group against each other (when that is possible).
     *
     * @param array<int,list<int>> $clasificadosPorGrupo group number => team ids by position
     * @return list<int> team ids in seed order (best first)
     */
    public static function sembrar(array $clasificadosPorGrupo, int $porGrupo): array
    {
        ksort($clasificadosPorGrupo);
        $grupoDe = [];
        $tiers = [];
        foreach ($clasificadosPorGrupo as $grupo => $ids) {
            foreach (array_slice(array_values($ids), 0, $porGrupo) as $pos => $id) {
                $tiers[$pos][] = $id;
                $grupoDe[$id] = $grupo;
            }
        }
        ksort($tiers);

        $candidatos = [];
        $g = max(1, count($clasificadosPorGrupo));
        foreach ([false, true] as $invertir) {
            for ($rot = 0; $rot < $g; $rot++) {
                $orden = [];
                foreach ($tiers as $t => $ids) {
                    if ($t > 0) {
                        if ($invertir) {
                            $ids = array_reverse($ids);
                        }
                        $r = $rot % max(1, count($ids));
                        $ids = array_merge(array_slice($ids, $r), array_slice($ids, 0, $r));
                    }
                    $orden = array_merge($orden, $ids);
                }
                $candidatos[] = $orden;
            }
        }

        foreach ($candidatos as $orden) {
            if (self::choquesMismoGrupo($orden, $grupoDe) === 0) {
                return $orden;
            }
        }
        return $candidatos[0] ?? [];
    }

    /**
     * @param list<int> $orden
     * @param array<int,int> $grupoDe
     */
    private static function choquesMismoGrupo(array $orden, array $grupoDe): int
    {
        $choques = 0;
        foreach (BracketEliminacion::generar($orden)['partidos'] as $p) {
            if ($p['ronda'] === 1 && $p['a'] !== null && $p['b'] !== null && $grupoDe[$p['a']] === $grupoDe[$p['b']]) {
                $choques++;
            }
        }
        return $choques;
    }
}
