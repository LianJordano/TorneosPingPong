<?php

declare(strict_types=1);

namespace PingPong\Domain;

/**
 * Calcula la tabla de posiciones única de la liga, aplicando las reglas de
 * desempate del doc (orden tipo ITTF):
 *
 *   1. Puntos de clasificación (partidos ganados × puntos por victoria).
 *   2. Resultado directo (head-to-head) ENTRE los empatados.
 *   3. Ratio de sets ENTRE los empatados.
 *   4. Ratio de puntos ENTRE los empatados.
 *   5. Ratio de sets global, luego ratio de puntos global, y por último sorteo.
 *
 * Los criterios 2-4 se recalculan considerando SOLO los partidos entre los
 * jugadores implicados en el empate (recursivamente por subgrupo).
 *
 * Es una clase pura: recibe datos ya agregados, no toca base de datos.
 */
final class TablaPosiciones
{
    /**
     * @param list<array{id:int,nombre:string}> $jugadores
     * @param list<array{a:int,b:int,ganador:?int,setsA:int,setsB:int,puntosA:int,puntosB:int}> $partidos
     *        Solo partidos FINALIZADOS de la liga.
     * @return list<array<string,mixed>> filas ordenadas con posición y criterio de desempate
     */
    public static function calcular(array $jugadores, array $partidos, int $puntosPorVictoria): array
    {
        $stats = [];
        foreach ($jugadores as $j) {
            $stats[$j['id']] = [
                'insc_id' => $j['id'], 'nombre' => $j['nombre'],
                'pj' => 0, 'pg' => 0, 'pp' => 0,
                'sf' => 0, 'sc' => 0, 'pf' => 0, 'pc' => 0,
            ];
        }

        foreach ($partidos as $m) {
            if (!isset($stats[$m['a']], $stats[$m['b']])) {
                continue;
            }
            $a = $m['a'];
            $b = $m['b'];
            $stats[$a]['pj']++;
            $stats[$b]['pj']++;
            $stats[$a]['sf'] += $m['setsA'];
            $stats[$a]['sc'] += $m['setsB'];
            $stats[$b]['sf'] += $m['setsB'];
            $stats[$b]['sc'] += $m['setsA'];
            $stats[$a]['pf'] += $m['puntosA'];
            $stats[$a]['pc'] += $m['puntosB'];
            $stats[$b]['pf'] += $m['puntosB'];
            $stats[$b]['pc'] += $m['puntosA'];
            if ($m['ganador'] === $a) {
                $stats[$a]['pg']++;
                $stats[$b]['pp']++;
            } elseif ($m['ganador'] === $b) {
                $stats[$b]['pg']++;
                $stats[$a]['pp']++;
            }
        }

        foreach ($stats as &$s) {
            $s['puntos'] = $s['pg'] * $puntosPorVictoria;
            $s['set_ratio'] = self::ratio($s['sf'], $s['sc']);
            $s['punto_ratio'] = self::ratio($s['pf'], $s['pc']);
        }
        unset($s);

        $filas = array_values($stats);
        usort($filas, static fn (array $x, array $y): int => $y['puntos'] <=> $x['puntos']);

        // Resolver empates por puntos, grupo por grupo.
        $resultado = [];
        $n = count($filas);
        $i = 0;
        while ($i < $n) {
            $j = $i;
            while ($j + 1 < $n && $filas[$j + 1]['puntos'] === $filas[$i]['puntos']) {
                $j++;
            }
            $grupo = array_slice($filas, $i, $j - $i + 1);
            if (count($grupo) > 1) {
                $grupo = self::resolverGrupo($grupo, $partidos);
            } else {
                $grupo[0]['desempate'] = null;
            }
            foreach ($grupo as $fila) {
                $resultado[] = $fila;
            }
            $i = $j + 1;
        }

        foreach ($resultado as $k => &$fila) {
            $fila['posicion'] = $k + 1;
        }
        unset($fila);

        return $resultado;
    }

    /**
     * Ordena un grupo empatado en puntos aplicando los criterios 2-5 sobre los
     * partidos entre los implicados, y etiqueta el criterio que rompió cada empate.
     *
     * @param list<array<string,mixed>> $grupo
     * @param list<array{a:int,b:int,ganador:?int,setsA:int,setsB:int,puntosA:int,puntosB:int}> $partidos
     * @return list<array<string,mixed>>
     */
    private static function resolverGrupo(array $grupo, array $partidos): array
    {
        $ids = array_column($grupo, 'insc_id');
        $enGrupo = array_flip($ids);

        // Estadísticas SOLO entre los empatados.
        $intra = [];
        foreach ($ids as $id) {
            $intra[$id] = ['w' => 0, 'sf' => 0, 'sc' => 0, 'pf' => 0, 'pc' => 0];
        }
        foreach ($partidos as $m) {
            if (!isset($enGrupo[$m['a']], $enGrupo[$m['b']])) {
                continue;
            }
            $a = $m['a'];
            $b = $m['b'];
            $intra[$a]['sf'] += $m['setsA'];
            $intra[$a]['sc'] += $m['setsB'];
            $intra[$b]['sf'] += $m['setsB'];
            $intra[$b]['sc'] += $m['setsA'];
            $intra[$a]['pf'] += $m['puntosA'];
            $intra[$a]['pc'] += $m['puntosB'];
            $intra[$b]['pf'] += $m['puntosB'];
            $intra[$b]['pc'] += $m['puntosA'];
            if ($m['ganador'] === $a) {
                $intra[$a]['w']++;
            } elseif ($m['ganador'] === $b) {
                $intra[$b]['w']++;
            }
        }

        // Devuelve [cmp, criterio]: primer criterio en que difieren x e y.
        $comparar = static function (array $x, array $y) use ($intra): array {
            $xi = $intra[$x['insc_id']];
            $yi = $intra[$y['insc_id']];

            if ($xi['w'] !== $yi['w']) {
                return [$yi['w'] <=> $xi['w'], 'Head-to-head'];
            }
            if (($c = self::cmpRatio($xi['sf'], $xi['sc'], $yi['sf'], $yi['sc'])) !== 0) {
                return [$c, 'Ratio de sets (entre empatados)'];
            }
            if (($c = self::cmpRatio($xi['pf'], $xi['pc'], $yi['pf'], $yi['pc'])) !== 0) {
                return [$c, 'Ratio de puntos (entre empatados)'];
            }
            if (($c = self::cmpRatio($x['sf'], $x['sc'], $y['sf'], $y['sc'])) !== 0) {
                return [$c, 'Ratio de sets (global)'];
            }
            if (($c = self::cmpRatio($x['pf'], $x['pc'], $y['pf'], $y['pc'])) !== 0) {
                return [$c, 'Ratio de puntos (global)'];
            }
            return [0, 'Sorteo'];
        };

        usort($grupo, static fn (array $x, array $y): int => $comparar($x, $y)[0]);

        foreach ($grupo as $k => &$fila) {
            $fila['desempate'] = $k === 0 ? null : $comparar($grupo[$k - 1], $fila)[1];
        }
        unset($fila);

        return $grupo;
    }

    /** Ratio favor/contra para mostrar (null si no jugó). */
    private static function ratio(int $favor, int $contra): ?float
    {
        if ($favor === 0 && $contra === 0) {
            return null;
        }
        return $contra === 0 ? (float) $favor : round($favor / $contra, 3);
    }

    /** Compara dos ratios favor/contra de mayor a menor (para usort). */
    private static function cmpRatio(int $xf, int $xc, int $yf, int $yc): int
    {
        $rx = $xc === 0 ? ($xf > 0 ? PHP_FLOAT_MAX : 0.0) : $xf / $xc;
        $ry = $yc === 0 ? ($yf > 0 ? PHP_FLOAT_MAX : 0.0) : $yf / $yc;
        return $ry <=> $rx;
    }
}
