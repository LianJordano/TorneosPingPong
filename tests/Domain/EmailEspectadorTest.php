<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\EmailEspectador;

final class EmailEspectadorTest extends TestCase
{
    public function test_usa_el_email_real_si_es_valido_y_esta_disponible(): void
    {
        $this->assertSame(
            'juan@dom.com',
            EmailEspectador::generar(5, 'juan@dom.com', true),
        );
    }

    public function test_normaliza_a_minusculas_y_recorta_espacios(): void
    {
        $this->assertSame(
            'juan@dom.com',
            EmailEspectador::generar(5, '  JUAN@Dom.COM ', true),
        );
    }

    public function test_genera_sintetico_si_no_hay_email(): void
    {
        $this->assertSame('jugador5@pingpong.local', EmailEspectador::generar(5, null, true));
        $this->assertSame('jugador5@pingpong.local', EmailEspectador::generar(5, '', true));
        $this->assertSame('jugador5@pingpong.local', EmailEspectador::generar(5, '   ', true));
    }

    public function test_genera_sintetico_si_el_email_real_esta_en_uso(): void
    {
        $this->assertSame(
            'jugador9@pingpong.local',
            EmailEspectador::generar(9, 'juan@dom.com', false),
        );
    }

    public function test_genera_sintetico_si_el_email_real_es_invalido(): void
    {
        $this->assertSame(
            'jugador7@pingpong.local',
            EmailEspectador::generar(7, 'no-es-un-mail', true),
        );
    }

    public function test_el_sintetico_es_unico_por_persona(): void
    {
        $this->assertNotSame(
            EmailEspectador::generar(1, null, true),
            EmailEspectador::generar(2, null, true),
        );
    }
}
