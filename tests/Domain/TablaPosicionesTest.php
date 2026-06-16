<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\TablaPosiciones;

final class TablaPosicionesTest extends TestCase
{
    /** Helper para construir un partido agregado. */
    private function m(int $a, int $b, int $ganador, int $sa, int $sb, int $pa, int $pb): array
    {
        return ['a' => $a, 'b' => $b, 'ganador' => $ganador, 'setsA' => $sa, 'setsB' => $sb, 'puntosA' => $pa, 'puntosB' => $pb];
    }

    public function test_ordena_por_puntos(): void
    {
        $jugadores = [['id' => 1, 'nombre' => 'A'], ['id' => 2, 'nombre' => 'B'], ['id' => 3, 'nombre' => 'C']];
        $partidos = [
            $this->m(1, 2, 1, 2, 0, 22, 10),
            $this->m(1, 3, 1, 2, 0, 22, 10),
            $this->m(2, 3, 2, 2, 0, 22, 10),
        ];
        $tabla = TablaPosiciones::calcular($jugadores, $partidos, 1);

        $this->assertSame(1, $tabla[0]['insc_id']); // A: 2 victorias
        $this->assertSame(2, $tabla[1]['insc_id']); // B: 1
        $this->assertSame(3, $tabla[2]['insc_id']); // C: 0
        $this->assertSame(2, $tabla[0]['puntos']);
    }

    public function test_empate_de_dos_se_rompe_por_head_to_head(): void
    {
        // A y B terminan 2-1; A le ganó a B.
        $jugadores = [['id' => 1, 'nombre' => 'A'], ['id' => 2, 'nombre' => 'B'], ['id' => 3, 'nombre' => 'C'], ['id' => 4, 'nombre' => 'D']];
        $partidos = [
            $this->m(1, 2, 1, 2, 0, 22, 10), // A vence a B
            $this->m(1, 3, 1, 2, 0, 22, 10), // A vence a C
            $this->m(2, 3, 2, 2, 0, 22, 10), // B vence a C
            $this->m(2, 4, 2, 2, 0, 22, 10), // B vence a D
            $this->m(4, 1, 4, 2, 0, 22, 10), // D vence a A
            $this->m(3, 4, 3, 2, 0, 22, 10), // C vence a D
        ];
        $tabla = TablaPosiciones::calcular($jugadores, $partidos, 1);

        // A y B empatan en 2 puntos; A queda arriba por head-to-head.
        $this->assertSame(2, $tabla[0]['puntos']);
        $this->assertSame(2, $tabla[1]['puntos']);
        $this->assertSame(1, $tabla[0]['insc_id']);
        $this->assertSame(2, $tabla[1]['insc_id']);
        $this->assertSame('Head-to-head', $tabla[1]['desempate']);
    }

    public function test_triple_empate_cae_a_ratio_de_sets_entre_empatados(): void
    {
        // Ciclo A>B, B>C, C>A: todos 1 victoria. Se separa por ratio de sets mutuo.
        $jugadores = [['id' => 1, 'nombre' => 'A'], ['id' => 2, 'nombre' => 'B'], ['id' => 3, 'nombre' => 'C']];
        $partidos = [
            $this->m(1, 2, 1, 2, 0, 22, 10), // A vence a B 2-0
            $this->m(2, 3, 2, 2, 0, 22, 10), // B vence a C 2-0
            $this->m(3, 1, 3, 2, 1, 22, 18), // C vence a A 2-1
        ];
        $tabla = TablaPosiciones::calcular($jugadores, $partidos, 1);

        // Todos 1 punto; ratios de sets entre empatados: A 3/2, B 2/2, C 2/3.
        $this->assertSame(1, $tabla[0]['insc_id']); // A mejor ratio
        $this->assertSame(2, $tabla[1]['insc_id']); // B
        $this->assertSame(3, $tabla[2]['insc_id']); // C
        $this->assertSame('Ratio de sets (entre empatados)', $tabla[1]['desempate']);
        $this->assertSame('Ratio de sets (entre empatados)', $tabla[2]['desempate']);
    }

    public function test_asigna_posiciones_consecutivas(): void
    {
        $jugadores = [['id' => 1, 'nombre' => 'A'], ['id' => 2, 'nombre' => 'B']];
        $partidos = [$this->m(1, 2, 1, 2, 0, 22, 10)];
        $tabla = TablaPosiciones::calcular($jugadores, $partidos, 1);

        $this->assertSame(1, $tabla[0]['posicion']);
        $this->assertSame(2, $tabla[1]['posicion']);
    }
}
