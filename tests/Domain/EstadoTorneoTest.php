<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\EstadoTorneo;

final class EstadoTorneoTest extends TestCase
{
    public function test_transiciones_validas_desde_borrador(): void
    {
        $borrador = EstadoTorneo::BORRADOR;
        $this->assertTrue($borrador->puedeTransicionarA(EstadoTorneo::INSCRIPCION_ABIERTA));
        $this->assertTrue($borrador->puedeTransicionarA(EstadoTorneo::CANCELADO));
        $this->assertFalse($borrador->puedeTransicionarA(EstadoTorneo::FINALIZADO));
        $this->assertFalse($borrador->puedeTransicionarA(EstadoTorneo::FASE_LIGA));
    }

    public function test_inscripcion_abierta_permite_inscripciones_y_editar(): void
    {
        $estado = EstadoTorneo::INSCRIPCION_ABIERTA;
        $this->assertTrue($estado->permiteInscripciones());
        $this->assertTrue($estado->permiteEditarConfig());
    }

    public function test_inscripcion_cerrada_no_permite_inscripciones_ni_editar(): void
    {
        $estado = EstadoTorneo::INSCRIPCION_CERRADA;
        $this->assertFalse($estado->permiteInscripciones());
        $this->assertFalse($estado->permiteEditarConfig());
        // Se puede reabrir o avanzar a la liga
        $this->assertTrue($estado->puedeTransicionarA(EstadoTorneo::INSCRIPCION_ABIERTA));
        $this->assertTrue($estado->puedeTransicionarA(EstadoTorneo::FASE_LIGA));
    }

    public function test_estados_terminales_no_admiten_transiciones(): void
    {
        $this->assertTrue(EstadoTorneo::FINALIZADO->esTerminal());
        $this->assertTrue(EstadoTorneo::CANCELADO->esTerminal());
        $this->assertSame([], EstadoTorneo::FINALIZADO->siguientesPosibles());
        $this->assertFalse(EstadoTorneo::FINALIZADO->puedeTransicionarA(EstadoTorneo::FASE_LIGA));
    }

    public function test_borrador_no_permite_inscripciones(): void
    {
        $this->assertFalse(EstadoTorneo::BORRADOR->permiteInscripciones());
        $this->assertTrue(EstadoTorneo::BORRADOR->permiteEditarConfig());
    }
}
