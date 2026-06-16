<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\FixtureRoundRobin;

final class FixtureRoundRobinTest extends TestCase
{
    /**
     * @dataProvider cantidadJugadores
     */
    public function test_genera_la_cantidad_correcta_de_partidos(int $k): void
    {
        $ids = range(1, $k);
        $partidos = FixtureRoundRobin::generar($ids);

        $esperado = FixtureRoundRobin::totalEsperado($k); // K·(K-1)/2
        $this->assertCount($esperado, $partidos, "Para {$k} jugadores deben ser {$esperado} partidos");
    }

    /** @return list<array{int}> */
    public static function cantidadJugadores(): array
    {
        return [[2], [3], [4], [5], [6], [7], [8], [10]];
    }

    public function test_cada_par_se_enfrenta_exactamente_una_vez(): void
    {
        $ids = [1, 2, 3, 4, 5];
        $partidos = FixtureRoundRobin::generar($ids);

        $vistos = [];
        foreach ($partidos as $p) {
            $this->assertNotSame($p['a'], $p['b'], 'Un jugador no puede enfrentarse a sí mismo');
            $clave = min($p['a'], $p['b']) . '-' . max($p['a'], $p['b']);
            $this->assertArrayNotHasKey($clave, $vistos, "El par {$clave} está repetido");
            $vistos[$clave] = true;
        }

        // Todos los pares posibles deben estar cubiertos.
        $this->assertCount(FixtureRoundRobin::totalEsperado(count($ids)), $vistos);
    }

    public function test_cada_jugador_juega_contra_todos_los_demas(): void
    {
        $ids = [10, 20, 30, 40];
        $partidos = FixtureRoundRobin::generar($ids);

        $rivales = array_fill_keys($ids, []);
        foreach ($partidos as $p) {
            $rivales[$p['a']][] = $p['b'];
            $rivales[$p['b']][] = $p['a'];
        }

        foreach ($ids as $id) {
            $esperados = array_values(array_diff($ids, [$id]));
            sort($rivales[$id]);
            $this->assertSame($esperados, $rivales[$id], "El jugador {$id} debe jugar contra todos los demás");
        }
    }

    public function test_menos_de_dos_jugadores_no_genera_partidos(): void
    {
        $this->assertSame([], FixtureRoundRobin::generar([1]));
        $this->assertSame([], FixtureRoundRobin::generar([]));
    }
}
