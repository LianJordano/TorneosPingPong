<?php

declare(strict_types=1);

namespace PingPong\Tests;

use PHPUnit\Framework\TestCase;
use PingPong\Support\Env;

/**
 * Pruebas de humo de la Fase 0: confirman que el harness de PHPUnit,
 * el autoload y las utilidades base funcionan.
 * La lógica de dominio (fixture, bracket, desempates) se prueba en fases siguientes.
 */
final class SmokeTest extends TestCase
{
    public function test_helper_e_escapa_html(): void
    {
        $this->assertSame('&lt;b&gt;', e('<b>'));
        $this->assertSame('&quot;x&quot;', e('"x"'));
        $this->assertSame('', e(null));
    }

    public function test_env_lee_valores_y_comillas(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'env_');
        file_put_contents(
            $tmp,
            "FOO=bar\n# comentario\nCITADO=\"hola mundo\"\nFLAG=true\n"
        );

        Env::reset();
        Env::load($tmp);

        $this->assertSame('bar', Env::get('FOO'));
        $this->assertSame('hola mundo', Env::get('CITADO'));
        $this->assertTrue(Env::bool('FLAG'));
        $this->assertSame('def', Env::get('NO_EXISTE', 'def'));

        unlink($tmp);
    }
}
