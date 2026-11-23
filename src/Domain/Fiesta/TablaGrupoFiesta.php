<?php

declare(strict_types=1);

namespace PingPong\Domain\Fiesta;

use PingPong\Domain\BracketEliminacion;

/**
 * Group standings for a party tournament and the crosswise seeding of the
 * group qualifiers into the knockout bracket.
 *
 * Order: wins, then score difference, then score in favour. Ties of 3+ teams
 * are resolved via a mini-table restricted to matches among the tied teams
 * (preserving strict weak ordering), 2-way ties via head-to-head, and any
 * remaining tie deterministically by team id ascending.
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
        $filas = self::acumular($equipos, $partidos);
        $directo = self::mapaDirecto($equipos, $partidos);

        $ordenadas = self::ordenarConDesempate(array_values($filas), $partidos, $directo);

        foreach ($ordenadas as $i => &$fila) {
            $fila['posicion'] = $i + 1;
        }
        unset($fila);

        return $ordenadas;
    }

    /**
     * @param list<int> $equipos
     * @param list<array{a:int,b:int,ma:int,mb:int,ganador:int}> $partidos
     * @return array<int,array{equipo:int,posicion:int,pj:int,pg:int,pp:int,favor:int,contra:int,diferencia:int}>
     */
    private static function acumular(array $equipos, array $partidos): array
    {
        $filas = [];
        foreach ($equipos as $id) {
            $filas[$id] = [
                'equipo'     => $id,
                'posicion'   => 0,
                'pj'         => 0,
                'pg'         => 0,
                'pp'         => 0,
                'favor'      => 0,
                'contra'     => 0,
                'diferencia' => 0,
            ];
        }

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
        }

        return $filas;
    }

    /**
     * @param list<int> $equipos
     * @param list<array{a:int,b:int,ma:int,mb:int,ganador:int}> $partidos
     * @return array<string,int>
     */
    private static function mapaDirecto(array $equipos, array $partidos): array
    {
        $enGrupo = array_flip($equipos);
        $directo = [];
        foreach ($partidos as $p) {
            if (!isset($enGrupo[$p['a']], $enGrupo[$p['b']])) {
                continue;
            }
            $directo[$p['a'] . '-' . $p['b']] = $p['ganador'];
            $directo[$p['b'] . '-' . $p['a']] = $p['ganador'];
        }
        return $directo;
    }

    /**
     * Sorts rows by (pg, diferencia, favor) descending and breaks ties cleanly:
     *   - 2-way tie: head-to-head result, then team id ascending.
     *   - 3+-way tie: mini-table restricted to matches among the tied teams;
     *     recurses only when the mini-table strictly splits the group,
     *     otherwise falls back deterministically to team id ascending.
     *
     * @param list<array{equipo:int,posicion:int,pj:int,pg:int,pp:int,favor:int,contra:int,diferencia:int}> $filas
     * @param list<array{a:int,b:int,ma:int,mb:int,ganador:int}> $partidos
     * @param array<string,int> $directo
     * @return list<array{equipo:int,posicion:int,pj:int,pg:int,pp:int,favor:int,contra:int,diferencia:int}>
     */
    private static function ordenarConDesempate(array $filas, array $partidos, array $directo): array
    {
        usort(
            $filas,
            static fn (array $x, array $y): int =>
                [$y['pg'], $y['diferencia'], $y['favor']] <=> [$x['pg'], $x['diferencia'], $x['favor']]
                ?: $x['equipo'] <=> $y['equipo']
        );

        $resultado = [];
        $n = count($filas);
        $i = 0;
        while ($i < $n) {
            $j = $i + 1;
            while (
                $j < $n
                && $filas[$j]['pg'] === $filas[$i]['pg']
                && $filas[$j]['diferencia'] === $filas[$i]['diferencia']
                && $filas[$j]['favor'] === $filas[$i]['favor']
            ) {
                $j++;
            }

            $bloque = array_slice($filas, $i, $j - $i);
            foreach (self::resolverEmpate($bloque, $partidos, $directo) as $fila) {
                $resultado[] = $fila;
            }
            $i = $j;
        }

        return $resultado;
    }

    /**
     * @param list<array{equipo:int,posicion:int,pj:int,pg:int,pp:int,favor:int,contra:int,diferencia:int}> $bloque
     * @param list<array{a:int,b:int,ma:int,mb:int,ganador:int}> $partidos
     * @param array<string,int> $directo
     * @return list<array{equipo:int,posicion:int,pj:int,pg:int,pp:int,favor:int,contra:int,diferencia:int}>
     */
    private static function resolverEmpate(array $bloque, array $partidos, array $directo): array
    {
        $cantidad = count($bloque);
        if ($cantidad <= 1) {
            return $bloque;
        }

        if ($cantidad === 2) {
            [$x, $y] = $bloque;
            $ganador = $directo[$x['equipo'] . '-' . $y['equipo']] ?? null;
            if ($ganador === $y['equipo']) {
                return [$y, $x];
            }
            if ($ganador === $x['equipo']) {
                return [$x, $y];
            }
            return $x['equipo'] <= $y['equipo'] ? [$x, $y] : [$y, $x];
        }

        // 3+ teams tied: compute a mini-table using only matches among the tied teams.
        $ids = array_map(static fn (array $f): int => $f['equipo'], $bloque);
        $mini = self::acumular($ids, $partidos);

        $porId = [];
        foreach ($bloque as $fila) {
            $porId[$fila['equipo']] = $fila;
        }

        $miniOrdenado = array_values($mini);
        usort(
            $miniOrdenado,
            static fn (array $x, array $y): int =>
                [$y['pg'], $y['diferencia'], $y['favor']] <=> [$x['pg'], $x['diferencia'], $x['favor']]
                ?: $x['equipo'] <=> $y['equipo']
        );

        $resultado = [];
        $n = count($miniOrdenado);
        $i = 0;
        while ($i < $n) {
            $j = $i + 1;
            while (
                $j < $n
                && $miniOrdenado[$j]['pg'] === $miniOrdenado[$i]['pg']
                && $miniOrdenado[$j]['diferencia'] === $miniOrdenado[$i]['diferencia']
                && $miniOrdenado[$j]['favor'] === $miniOrdenado[$i]['favor']
            ) {
                $j++;
            }

            $subBloque = [];
            for ($k = $i; $k < $j; $k++) {
                $subBloque[] = $porId[$miniOrdenado[$k]['equipo']];
            }

            if (count($subBloque) === $cantidad) {
                // Mini-table did not separate the group (e.g. symmetric 3-way circle):
                // deterministic fallback by team id ascending preserves strict weak ordering.
                usort($subBloque, static fn (array $x, array $y): int => $x['equipo'] <=> $y['equipo']);
                foreach ($subBloque as $fila) {
                    $resultado[] = $fila;
                }
            } else {
                foreach (self::resolverEmpate($subBloque, $partidos, $directo) as $fila) {
                    $resultado[] = $fila;
                }
            }
            $i = $j;
        }

        return $resultado;
    }

    /**
     * Seed order for the knockout: all group winners first, then runners-up, etc.
     * Lower tiers are rotated/reversed until no first-round match pits two teams
     * of the same group against each other (or the fewest possible clashes).
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

        return self::elegirRotacion($candidatos, $grupoDe);
    }

    /**
     * Picks the first rotation with 0 same-group clashes, or the one with the
     * minimum number of clashes when 0 is impossible.
     *
     * @param list<list<int>> $candidatos
     * @param array<int,int> $grupoDe
     * @return list<int>
     */
    private static function elegirRotacion(array $candidatos, array $grupoDe): array
    {
        $mejor = $candidatos[0] ?? [];
        $minChoques = PHP_INT_MAX;

        foreach ($candidatos as $orden) {
            $choques = self::choquesMismoGrupo($orden, $grupoDe);
            if ($choques === 0) {
                return $orden;
            }
            if ($choques < $minChoques) {
                $minChoques = $choques;
                $mejor = $orden;
            }
        }

        return $mejor;
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
