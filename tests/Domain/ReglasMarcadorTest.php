<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\ReglasMarcador;

final class ReglasMarcadorTest extends TestCase
{
    public function test_sets_para_ganar_segun_formato(): void
    {
        $this->assertSame(1, (new ReglasMarcador(formatoSets: 1))->setsParaGanar());
        $this->assertSame(2, (new ReglasMarcador(formatoSets: 3))->setsParaGanar());
        $this->assertSame(3, (new ReglasMarcador(formatoSets: 5))->setsParaGanar());
        $this->assertSame(4, (new ReglasMarcador(formatoSets: 7))->setsParaGanar());
    }

    public function test_set_valido(): void
    {
        $r = new ReglasMarcador(11, 2, 3);
        $this->assertTrue($r->setValido(11, 9));   // normal
        $this->assertTrue($r->setValido(11, 0));   // paliza
        $this->assertTrue($r->setValido(12, 10));  // deuce
        $this->assertTrue($r->setValido(9, 11));   // gana B
        $this->assertFalse($r->setValido(11, 10)); // diferencia 1
        $this->assertFalse($r->setValido(13, 10)); // gana por más de 2 tras deuce
        $this->assertFalse($r->setValido(9, 7));   // no llega a 11
        $this->assertFalse($r->setValido(11, 11)); // empate imposible
    }

    public function test_partido_valido_mejor_de_3(): void
    {
        $r = new ReglasMarcador(11, 2, 3);

        $ok = $r->validarPartido([[11, 9], [11, 7]]);
        $this->assertTrue($ok['valido']);
        $this->assertSame('A', $ok['ganador']);
        $this->assertSame(2, $ok['setsA']);

        $tres = $r->validarPartido([[11, 9], [9, 11], [11, 8]]);
        $this->assertTrue($tres['valido']);
        $this->assertSame('A', $tres['ganador']);
    }

    public function test_partido_invalido(): void
    {
        $r = new ReglasMarcador(11, 2, 3);

        // No definido: un solo set
        $this->assertFalse($r->validarPartido([[11, 9]])['valido']);
        // 1-1: nadie llegó a 2 sets
        $this->assertFalse($r->validarPartido([[11, 9], [5, 11]])['valido']);
        // Sets de más: ya estaba 2-0 y hay un tercero
        $this->assertFalse($r->validarPartido([[11, 9], [11, 7], [11, 5]])['valido']);
        // Set inválido en el medio
        $this->assertFalse($r->validarPartido([[11, 9], [11, 10]])['valido']);
    }

    public function test_partido_a_un_solo_set(): void
    {
        $r = new ReglasMarcador(11, 2, 1);
        $ok = $r->validarPartido([[11, 8]]);
        $this->assertTrue($ok['valido']);
        $this->assertSame('A', $ok['ganador']);
        // Dos sets en formato a 1 set → inválido
        $this->assertFalse($r->validarPartido([[11, 8], [11, 6]])['valido']);
    }

    public function test_resumen_agrega_sets_y_puntos(): void
    {
        $resumen = ReglasMarcador::resumen([[11, 9], [9, 11], [11, 7]]);
        $this->assertSame(2, $resumen['setsA']);
        $this->assertSame(1, $resumen['setsB']);
        $this->assertSame(31, $resumen['puntosA']);
        $this->assertSame(27, $resumen['puntosB']);
    }
}
