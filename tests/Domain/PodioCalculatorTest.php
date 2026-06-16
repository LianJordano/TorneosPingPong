<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\PodioCalculator;

final class PodioCalculatorTest extends TestCase
{
    public function test_podio_de_un_cuadro_de_4(): void
    {
        // Semis (ronda 1) y final (ronda 2). Posición de liga: 10=1°, 20=2°, 30=3°, 40=4°.
        $bracket = [
            ['a' => 10, 'b' => 40, 'ganador' => 10, 'ronda' => 1, 'estado' => 'FINALIZADO'],
            ['a' => 20, 'b' => 30, 'ganador' => 20, 'ronda' => 1, 'estado' => 'FINALIZADO'],
            ['a' => 10, 'b' => 20, 'ganador' => 10, 'ronda' => 2, 'estado' => 'FINALIZADO'],
        ];
        $posLiga = [10 => 1, 20 => 2, 30 => 3, 40 => 4];

        $podio = PodioCalculator::calcular($bracket, 2, $posLiga);

        $orden = array_map(static fn ($p) => $p['insc_id'], $podio);
        // Campeón 10, finalista 20; los dos perdedores de semis se ordenan por liga: 30 (3°) antes que 40 (4°).
        $this->assertSame([10, 20, 30, 40], $orden);
        $this->assertSame(1, $podio[0]['posicion']);
        $this->assertSame(3, $podio[0]['hasta_ronda']); // campeón llegó "más allá" de la final (ronda 2 + 1)
        $this->assertSame(1, $podio[2]['hasta_ronda']); // 3°: perdió en semifinal (ronda 1)
    }

    public function test_desempate_de_semifinalistas_por_posicion_de_liga(): void
    {
        // Ambos semifinalistas perdedores (30 y 40) cayeron en ronda 1.
        // 40 fue mejor en la liga (pos 2) que 30 (pos 3): 40 debe ir 3°.
        $bracket = [
            ['a' => 10, 'b' => 40, 'ganador' => 10, 'ronda' => 1, 'estado' => 'FINALIZADO'],
            ['a' => 20, 'b' => 30, 'ganador' => 20, 'ronda' => 1, 'estado' => 'FINALIZADO'],
            ['a' => 10, 'b' => 20, 'ganador' => 10, 'ronda' => 2, 'estado' => 'FINALIZADO'],
        ];
        $posLiga = [10 => 1, 20 => 4, 40 => 2, 30 => 3];

        $podio = PodioCalculator::calcular($bracket, 2, $posLiga);
        $orden = array_map(static fn ($p) => $p['insc_id'], $podio);

        $this->assertSame(40, $orden[2]); // 3° por mejor posición de liga
        $this->assertSame(30, $orden[3]);
    }
}
