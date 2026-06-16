<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\SorteoParejas;

final class SorteoParejasTest extends TestCase
{
    /** Barajador identidad: deja la lista tal cual para tener resultados deterministas. */
    private static function identidad(): callable
    {
        return static fn (array $xs): array => $xs;
    }

    public function test_cantidad_par_arma_parejas_sin_sobrante(): void
    {
        $resultado = SorteoParejas::emparejar([1, 2, 3, 4], self::identidad());

        $this->assertSame([[1, 2], [3, 4]], $resultado['parejas']);
        $this->assertNull($resultado['sobrante']);
    }

    public function test_cantidad_impar_deja_sobrante(): void
    {
        $resultado = SorteoParejas::emparejar([1, 2, 3, 4, 5], self::identidad());

        $this->assertSame([[1, 2], [3, 4]], $resultado['parejas']);
        $this->assertSame(5, $resultado['sobrante']);
    }

    public function test_cada_id_aparece_exactamente_una_vez(): void
    {
        $ids = [10, 20, 30, 40, 50, 60];
        $resultado = SorteoParejas::emparejar($ids, self::identidad());

        $planos = [];
        foreach ($resultado['parejas'] as [$a, $b]) {
            $planos[] = $a;
            $planos[] = $b;
        }
        if ($resultado['sobrante'] !== null) {
            $planos[] = $resultado['sobrante'];
        }

        sort($planos);
        $this->assertSame($ids, $planos, 'Todas las inscripciones deben quedar asignadas una sola vez');
    }

    public function test_respeta_el_orden_del_barajador(): void
    {
        // Barajador que invierte la lista: las parejas deben salir invertidas.
        $invertir = static fn (array $xs): array => array_reverse($xs);
        $resultado = SorteoParejas::emparejar([1, 2, 3, 4], $invertir);

        $this->assertSame([[4, 3], [2, 1]], $resultado['parejas']);
    }

    public function test_lista_vacia_o_unitaria(): void
    {
        $vacio = SorteoParejas::emparejar([], self::identidad());
        $this->assertSame([], $vacio['parejas']);
        $this->assertNull($vacio['sobrante']);

        $uno = SorteoParejas::emparejar([7], self::identidad());
        $this->assertSame([], $uno['parejas']);
        $this->assertSame(7, $uno['sobrante']);
    }
}
