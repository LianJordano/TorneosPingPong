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
}
