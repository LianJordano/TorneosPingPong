<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\BracketEliminacion;

final class BracketEliminacionTest extends TestCase
{
    public function test_potencia_de_2_y_orden_de_siembra(): void
    {
        $this->assertSame(4, BracketEliminacion::siguientePotenciaDe2(3));
        $this->assertSame(8, BracketEliminacion::siguientePotenciaDe2(5));
        $this->assertSame(8, BracketEliminacion::siguientePotenciaDe2(8));

        // Siembra estándar: 1 y 2 en extremos opuestos.
        $this->assertSame([1, 4, 2, 3], BracketEliminacion::ordenSiembra(4));
        $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], BracketEliminacion::ordenSiembra(8));
    }

    public function test_cuadro_de_4_sin_byes(): void
    {
        $bracket = BracketEliminacion::generar([10, 20, 30, 40]); // seeds 1..4
        $this->assertSame(4, $bracket['tamano']);
        $this->assertSame(2, $bracket['rondas']);
        $this->assertCount(3, $bracket['partidos']); // 2 semis + 1 final

        $r1 = array_values(array_filter($bracket['partidos'], static fn ($p) => $p['ronda'] === 1));
        // Cruce estándar: 1° vs 4° y 2° vs 3°
        $this->assertSame([10, 40], [$r1[0]['a'], $r1[0]['b']]);
        $this->assertSame([20, 30], [$r1[1]['a'], $r1[1]['b']]);
        foreach ($r1 as $p) {
            $this->assertFalse($p['bye']);
            $this->assertSame('PENDIENTE', $p['estado']);
        }
    }

    public function test_cuadro_de_2_es_la_final(): void
    {
        $bracket = BracketEliminacion::generar([7, 9]);
        $this->assertSame(2, $bracket['tamano']);
        $this->assertSame(1, $bracket['rondas']);
        $this->assertCount(1, $bracket['partidos']);
        $this->assertSame([7, 9], [$bracket['partidos'][0]['a'], $bracket['partidos'][0]['b']]);
    }

    public function test_cuadro_de_3_genera_un_bye_para_el_primero(): void
    {
        $bracket = BracketEliminacion::generar([100, 200, 300]); // seeds 1,2,3 ; seed4 = bye
        $this->assertSame(4, $bracket['tamano']);

        $byes = array_values(array_filter($bracket['partidos'], static fn ($p) => $p['bye']));
        $this->assertCount(1, $byes);
        // El bye es del 1° sembrado y queda FINALIZADO con él como ganador.
        $this->assertSame(100, $byes[0]['ganador']);
        $this->assertSame('FINALIZADO', $byes[0]['estado']);

        // El 1° ya está colocado en la final (ronda 2) esperando rival.
        $final = array_values(array_filter($bracket['partidos'], static fn ($p) => $p['ronda'] === 2))[0];
        $this->assertSame(100, $final['a']);
        $this->assertNull($final['b']);

        // El partido real es 2° vs 3°.
        $real = array_values(array_filter(
            $bracket['partidos'],
            static fn ($p) => $p['ronda'] === 1 && !$p['bye']
        ))[0];
        $this->assertSame([200, 300], [$real['a'], $real['b']]);
    }

    public function test_cuadro_de_6_tiene_dos_byes_para_los_mejores(): void
    {
        $bracket = BracketEliminacion::generar([1, 2, 3, 4, 5, 6]); // ids = seeds
        $this->assertSame(8, $bracket['tamano']);
        $this->assertSame(3, $bracket['rondas']);
        $this->assertCount(7, $bracket['partidos']); // 4 + 2 + 1

        $byes = array_values(array_filter($bracket['partidos'], static fn ($p) => $p['bye']));
        $this->assertCount(2, $byes); // seeds 7 y 8 faltan → byes para 1° y 2°
        $ganadoresBye = array_map(static fn ($p) => $p['ganador'], $byes);
        sort($ganadoresBye);
        $this->assertSame([1, 2], $ganadoresBye);
    }
}
