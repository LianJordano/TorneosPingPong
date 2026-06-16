<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\ReglasMarcador;
use PingPong\Domain\TipoResultado;

final class TipoResultadoTest extends TestCase
{
    public function test_que_exige_cada_tipo(): void
    {
        $this->assertTrue(TipoResultado::NORMAL->requiereSetsCompletos());
        $this->assertFalse(TipoResultado::WALKOVER->requiereSetsCompletos());

        $this->assertFalse(TipoResultado::NORMAL->requiereGanadorManual());
        $this->assertTrue(TipoResultado::WALKOVER->requiereGanadorManual());
        $this->assertTrue(TipoResultado::RETIRO->requiereGanadorManual());

        // Solo NORMAL y RETIRO admiten sets.
        $this->assertTrue(TipoResultado::NORMAL->permiteSets());
        $this->assertTrue(TipoResultado::RETIRO->permiteSets());
        $this->assertFalse(TipoResultado::WALKOVER->permiteSets());
        $this->assertFalse(TipoResultado::NO_PRESENTADO->permiteSets());
    }

    public function test_sets_invalidos_para_retiro(): void
    {
        $reglas = new ReglasMarcador(11, 2, 5);
        // El set 2 (11-10) es inválido; los demás son válidos.
        $invalidos = $reglas->setsInvalidos([[11, 9], [11, 10], [11, 0]]);
        $this->assertSame([2], $invalidos);

        // Sets parciales válidos (un retiro tras 1-0) → sin errores.
        $this->assertSame([], $reglas->setsInvalidos([[11, 7]]));
    }
}
