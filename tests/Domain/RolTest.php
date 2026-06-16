<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\Rol;

final class RolTest extends TestCase
{
    public function test_solo_admin_gestiona(): void
    {
        $this->assertTrue(Rol::ADMIN->puedeGestionar());
        $this->assertFalse(Rol::ANOTADOR->puedeGestionar());
        $this->assertFalse(Rol::ESPECTADOR->puedeGestionar());
    }

    public function test_admin_y_anotador_anotan(): void
    {
        $this->assertTrue(Rol::ADMIN->puedeAnotar());
        $this->assertTrue(Rol::ANOTADOR->puedeAnotar());
        $this->assertFalse(Rol::ESPECTADOR->puedeAnotar());
    }
}
