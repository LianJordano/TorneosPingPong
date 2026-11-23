<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain\Fiesta;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\BracketEliminacion;
use PingPong\Domain\Fiesta\TablaGrupoFiesta;

final class TablaGrupoFiestaTest extends TestCase
{
    public function test_ordena_por_victorias_y_diferencia(): void
    {
        $tabla = TablaGrupoFiesta::calcular([1, 2, 3], [
            ['a' => 1, 'b' => 2, 'ma' => 3, 'mb' => 0, 'ganador' => 1],
            ['a' => 2, 'b' => 3, 'ma' => 3, 'mb' => 2, 'ganador' => 2],
            ['a' => 3, 'b' => 1, 'ma' => 3, 'mb' => 1, 'ganador' => 3],
        ]);

        // 1: 1W diff +1, 2: 1W diff -2, 3: 1W diff +1 (favor 5 vs 4) → 3, 1, 2
        $this->assertSame([3, 1, 2], array_column($tabla, 'equipo'));
        $this->assertSame([1, 2, 3], array_column($tabla, 'posicion'));
        $this->assertSame(2, $tabla[0]['pj']);
    }

    public function test_empate_total_lo_define_el_resultado_directo(): void
    {
        $tabla = TablaGrupoFiesta::calcular([1, 2], [
            ['a' => 1, 'b' => 2, 'ma' => 2, 'mb' => 3, 'ganador' => 2],
            ['a' => 2, 'b' => 1, 'ma' => 2, 'mb' => 3, 'ganador' => 1],
        ]);
        // Fully tied; the latest head-to-head record wins → team 1.
        $this->assertSame([1, 2], array_column($tabla, 'equipo'));
    }

    public function test_equipos_sin_partidos_aparecen_con_cero(): void
    {
        $tabla = TablaGrupoFiesta::calcular([5, 6], []);
        $this->assertSame([5, 6], array_column($tabla, 'equipo'));
        $this->assertSame(0, $tabla[0]['pj']);
    }

    /** @return iterable<string,array{int}> */
    public static function cantidadDeGrupos(): iterable
    {
        yield '2 grupos' => [2];
        yield '3 grupos' => [3];
        yield '4 grupos' => [4];
    }

    /** @dataProvider cantidadDeGrupos */
    public function test_siembra_cruzada_evita_cruces_del_mismo_grupo_en_primera_ronda(int $grupos): void
    {
        $clasificados = [];
        $grupoDe = [];
        for ($g = 1; $g <= $grupos; $g++) {
            $clasificados[$g] = [$g * 10 + 1, $g * 10 + 2];
            $grupoDe[$g * 10 + 1] = $g;
            $grupoDe[$g * 10 + 2] = $g;
        }

        $orden = TablaGrupoFiesta::sembrar($clasificados, 2);

        $this->assertCount($grupos * 2, $orden);
        $this->assertSame(array_map(static fn (int $g): int => $g * 10 + 1, range(1, $grupos)), array_slice($orden, 0, $grupos), 'Group winners are the top seeds');
        foreach (BracketEliminacion::generar($orden)['partidos'] as $p) {
            if ($p['ronda'] === 1 && $p['a'] !== null && $p['b'] !== null) {
                $this->assertNotSame($grupoDe[$p['a']], $grupoDe[$p['b']], 'Same-group teams must not meet in round 1');
            }
        }
    }

    public function test_siembra_con_un_clasificado_por_grupo(): void
    {
        $this->assertSame([11, 21], TablaGrupoFiesta::sembrar([2 => [21, 22], 1 => [11, 12]], 1));
    }

    public function test_triple_empate_circular_mantiene_orden_estricto_sin_importar_orden_de_entrada(): void
    {
        // A beats B 3-1, B beats C 3-1, C beats A 3-1: completely symmetric 3-way tie.
        $partidos = [
            ['a' => 1, 'b' => 2, 'ma' => 3, 'mb' => 1, 'ganador' => 1],
            ['a' => 2, 'b' => 3, 'ma' => 3, 'mb' => 1, 'ganador' => 2],
            ['a' => 3, 'b' => 1, 'ma' => 3, 'mb' => 1, 'ganador' => 3],
        ];

        $orden1 = array_column(TablaGrupoFiesta::calcular([1, 2, 3], $partidos), 'equipo');
        $orden2 = array_column(TablaGrupoFiesta::calcular([2, 3, 1], $partidos), 'equipo');
        $orden3 = array_column(TablaGrupoFiesta::calcular([3, 1, 2], $partidos), 'equipo');
        $orden4 = array_column(TablaGrupoFiesta::calcular([3, 2, 1], $partidos), 'equipo');

        $this->assertSame([1, 2, 3], $orden1);
        $this->assertSame($orden1, $orden2);
        $this->assertSame($orden1, $orden3);
        $this->assertSame($orden1, $orden4);
    }

    public function test_triple_empate_se_desempata_por_minitabla_entre_empatados(): void
    {
        // 4 teams: 1, 2, 3 all finish with 2W, diff +3, favor 9 overall.
        // Team 4 loses to all three, compensating their overall diffs.
        // Mini-table among {1, 2, 3}:
        //   1 beat 2 (3-0), lost to 3 (2-3) => 1W, diff +2, favor 5
        //   3 beat 1 (3-2), lost to 2 (2-3) => 1W, diff  0, favor 5
        //   2 beat 3 (3-2), lost to 1 (0-3) => 1W, diff -2, favor 3
        // Vs team 4:
        //   1 beat 4 (4-3) => +1 => overall: 2W, diff +3, favor 9
        //   3 beat 4 (4-1) => +3 => overall: 2W, diff +3, favor 9
        //   2 beat 4 (6-1) => +5 => overall: 2W, diff +3, favor 9
        $partidos = [
            ['a' => 1, 'b' => 2, 'ma' => 3, 'mb' => 0, 'ganador' => 1],
            ['a' => 2, 'b' => 3, 'ma' => 3, 'mb' => 2, 'ganador' => 2],
            ['a' => 3, 'b' => 1, 'ma' => 3, 'mb' => 2, 'ganador' => 3],
            ['a' => 1, 'b' => 4, 'ma' => 4, 'mb' => 3, 'ganador' => 1],
            ['a' => 3, 'b' => 4, 'ma' => 4, 'mb' => 1, 'ganador' => 3],
            ['a' => 2, 'b' => 4, 'ma' => 6, 'mb' => 1, 'ganador' => 2],
        ];

        // Regardless of input order, mini-table orders them 1, 3, 2, 4.
        $this->assertSame([1, 3, 2, 4], array_column(TablaGrupoFiesta::calcular([2, 3, 1, 4], $partidos), 'equipo'));
        $this->assertSame([1, 3, 2, 4], array_column(TablaGrupoFiesta::calcular([4, 1, 2, 3], $partidos), 'equipo'));
    }

    public function test_siembra_con_tres_clasificados_por_grupo(): void
    {
        $clasificados = [
            1 => [11, 12, 13],
            2 => [21, 22, 23],
        ];
        $grupoDe = [11 => 1, 12 => 1, 13 => 1, 21 => 2, 22 => 2, 23 => 2];

        $orden = TablaGrupoFiesta::sembrar($clasificados, 3);

        $this->assertCount(6, $orden);
        $this->assertSame([11, 21], array_slice($orden, 0, 2));
        foreach (BracketEliminacion::generar($orden)['partidos'] as $p) {
            if ($p['ronda'] === 1 && $p['a'] !== null && $p['b'] !== null) {
                $this->assertNotSame($grupoDe[$p['a']], $grupoDe[$p['b']]);
            }
        }
    }

    public function test_siembra_con_grupos_desbalanceados(): void
    {
        $clasificados = [
            1 => [11, 12, 13],
            2 => [21, 22],
        ];

        $orden = TablaGrupoFiesta::sembrar($clasificados, 3);

        $this->assertCount(5, $orden);
        $this->assertSame([11, 21], array_slice($orden, 0, 2));
    }

    public function test_siembra_elige_minimo_de_choques_cuando_cero_es_imposible(): void
    {
        // Group 1 has 3 qualifiers, Group 2 has 1 qualifier (4 teams total, bracket of 4).
        // First-round matches are (seed1 vs seed4) and (seed2 vs seed3).
        // Tiers: pos0=[11, 21], pos1=[12], pos2=[13].
        // Wait: to force a choice between 2 clashes and 1 clash, use 3 groups where group 1 has 4 qualifiers and groups 2 & 3 have 2 each (8 teams -> 4 R1 matches).
        // Group 1 has 4 teams out of 8, but let's make group 1 have 5 teams out of 8 so at least 1 same-group clash is mathematically unavoidable (pigeonhole principle: 5 teams in 4 matches => >= 1 clash).
        $clasificados = [
            1 => [11, 12, 13, 14, 15],
            2 => [21, 22],
            3 => [31],
        ];
        $grupoDe = [11 => 1, 12 => 1, 13 => 1, 14 => 1, 15 => 1, 21 => 2, 22 => 2, 31 => 3];

        $orden = TablaGrupoFiesta::sembrar($clasificados, 5);

        $choques = 0;
        foreach (BracketEliminacion::generar($orden)['partidos'] as $p) {
            if ($p['ronda'] === 1 && $p['a'] !== null && $p['b'] !== null && $grupoDe[$p['a']] === $grupoDe[$p['b']]) {
                $choques++;
            }
        }
        // Pigeonhole minimum is 1; naive candidate[0] could have more, our fallback must achieve the minimum (1).
        $this->assertSame(1, $choques);
    }
}
