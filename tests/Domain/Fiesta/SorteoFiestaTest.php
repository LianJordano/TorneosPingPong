<?php

declare(strict_types=1);

namespace PingPong\Tests\Domain\Fiesta;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\Fiesta\SorteoFiesta;

final class SorteoFiestaTest extends TestCase
{
    private static function identidad(): callable
    {
        return static fn (array $xs): array => $xs;
    }

    public function test_dobles_revela_primero_todos_los_capitanes_y_luego_sus_companeros(): void
    {
        $r = SorteoFiesta::sortear([1, 2, 3, 4, 5, 6], true, SorteoFiesta::ELIMINACION, 1, self::identidad());

        $this->assertSame([
            ['numero' => 1, 'p1' => 1, 'p2' => 4],
            ['numero' => 2, 'p1' => 2, 'p2' => 5],
            ['numero' => 3, 'p1' => 3, 'p2' => 6],
        ], $r['equipos']);

        $tipos = array_column($r['guion'], 'tipo');
        $this->assertSame(['capitan', 'capitan', 'capitan', 'companero', 'companero', 'companero', 'llave', 'llave', 'llave'], $tipos);
        $this->assertSame([1, 2, 3], array_column(array_slice($r['guion'], 0, 3), 'participante'));
        $this->assertSame([4, 5, 6], array_column(array_slice($r['guion'], 3, 3), 'participante'));
    }

    public function test_cada_participante_queda_en_un_solo_equipo(): void
    {
        $ids = [10, 20, 30, 40, 50, 60, 70, 80];
        $r = SorteoFiesta::sortear($ids, true, SorteoFiesta::GRUPOS, 2);

        $planos = [];
        foreach ($r['equipos'] as $e) {
            $planos[] = $e['p1'];
            $planos[] = $e['p2'];
        }
        sort($planos);
        $this->assertSame($ids, $planos);
    }

    public function test_individual_no_tiene_pasos_de_parejas(): void
    {
        $r = SorteoFiesta::sortear([1, 2, 3, 4], false, SorteoFiesta::ELIMINACION, 1, self::identidad());

        $this->assertSame(['llave', 'llave', 'llave', 'llave'], array_column($r['guion'], 'tipo'));
        $this->assertNull($r['equipos'][0]['p2']);
    }

    public function test_eliminacion_ubica_cada_equipo_en_su_cruce_de_primera_ronda(): void
    {
        $r = SorteoFiesta::sortear([1, 2, 3, 4], false, SorteoFiesta::ELIMINACION, 1, self::identidad());

        // Standard seeding over the draw order: 1 vs 4 and 2 vs 3.
        $llaves = [];
        foreach ($r['guion'] as $paso) {
            $llaves[$paso['equipo']] = [$paso['partido'], $paso['lado']];
        }
        $this->assertSame([0, 'a'], $llaves[1]);
        $this->assertSame([0, 'b'], $llaves[4]);
        $this->assertSame([1, 'a'], $llaves[2]);
        $this->assertSame([1, 'b'], $llaves[3]);
    }

    public function test_eliminacion_con_cantidad_no_potencia_de_dos_genera_byes(): void
    {
        $r = SorteoFiesta::sortear([1, 2, 3, 4, 5], false, SorteoFiesta::ELIMINACION, 1, self::identidad());

        $this->assertSame(8, $r['bracket']['tamano']);
        $byes = array_filter($r['bracket']['partidos'], static fn (array $p): bool => $p['bye']);
        $this->assertCount(3, $byes);
        $this->assertCount(5, $r['guion']);
    }

    public function test_grupos_reparte_equipos_balanceados(): void
    {
        $r = SorteoFiesta::sortear(range(1, 7), false, SorteoFiesta::GRUPOS, 2, self::identidad());

        $this->assertSame([1 => 1, 2 => 2, 3 => 1, 4 => 2, 5 => 1, 6 => 2, 7 => 1], $r['grupos']);
        $this->assertSame(array_fill(0, 7, 'grupo'), array_column($r['guion'], 'tipo'));
    }

    public function test_dobles_impar_se_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('número par');
        SorteoFiesta::sortear([1, 2, 3, 4, 5], true, SorteoFiesta::ELIMINACION);
    }

    public function test_pocos_participantes_se_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('al menos 4 participantes');
        SorteoFiesta::sortear([1, 2], true, SorteoFiesta::ELIMINACION);
    }

    public function test_grupos_sin_equipos_suficientes_se_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cada grupo necesita al menos 2');
        SorteoFiesta::sortear([1, 2, 3, 4, 5], false, SorteoFiesta::GRUPOS, 3);
    }

    public function test_formato_invalido_se_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Formato de torneo inválido');
        SorteoFiesta::validar(4, false, 'SUIZO', 1);
    }

    public function test_grupos_menor_a_uno_se_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('al menos 1 grupo');
        SorteoFiesta::validar(4, false, SorteoFiesta::GRUPOS, 0);
    }

    public function test_individual_con_menos_de_dos_participantes_se_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('al menos 2 participantes');
        SorteoFiesta::sortear([1], false, SorteoFiesta::ELIMINACION);
    }

    public function test_un_solo_grupo_asigna_todos_al_grupo_uno(): void
    {
        $r = SorteoFiesta::sortear([1, 2, 3, 4], false, SorteoFiesta::GRUPOS, 1, self::identidad(), 2);

        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 1], $r['grupos']);
        $this->assertCount(4, $r['guion']);
    }

    public function test_validar_clasificados_rechaza_mas_clasificados_que_el_grupo_mas_chico(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('grupo más chico tendría 2 equipos');
        SorteoFiesta::validarClasificados(2, 3, 5);
    }

    public function test_validar_clasificados_rechaza_un_grupo_con_un_solo_clasificado(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('al menos 2 equipos');
        SorteoFiesta::validarClasificados(1, 1, 4);
    }

    public function test_validar_invoca_validar_clasificados_en_formato_grupos(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('al menos 2 equipos');
        SorteoFiesta::validar(4, false, SorteoFiesta::GRUPOS, 1, 1);
    }

    public function test_validar_ignora_clasificados_en_formato_eliminacion(): void
    {
        SorteoFiesta::validar(4, false, SorteoFiesta::ELIMINACION, 1, 1);
        $this->addToAssertionCount(1);
    }

    public function test_liga_dobles_asigna_todos_al_grupo_uno_sin_pasos_de_grupo(): void
    {
        $r = SorteoFiesta::sortear([1, 2, 3, 4, 5, 6], true, SorteoFiesta::LIGA, 1, self::identidad(), 2);

        $this->assertSame([1 => 1, 2 => 1, 3 => 1], $r['grupos']);
        $this->assertSame(0, $r['bracket']['tamano']);
        $this->assertSame([], $r['bracket']['partidos']);
        $this->assertSame(
            ['capitan', 'capitan', 'capitan', 'companero', 'companero', 'companero'],
            array_column($r['guion'], 'tipo'),
        );
    }

    public function test_liga_individual_emite_pasos_tipo_liga_con_participante(): void
    {
        $r = SorteoFiesta::sortear([10, 20, 30, 40], false, SorteoFiesta::LIGA, 1, self::identidad(), 2);

        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 1], $r['grupos']);
        $this->assertSame([
            ['tipo' => 'liga', 'equipo' => 1, 'participante' => 10],
            ['tipo' => 'liga', 'equipo' => 2, 'participante' => 20],
            ['tipo' => 'liga', 'equipo' => 3, 'participante' => 30],
            ['tipo' => 'liga', 'equipo' => 4, 'participante' => 40],
        ], $r['guion']);
    }

    public function test_liga_rechaza_menos_de_dos_clasificados(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('al menos 2 equipos');
        SorteoFiesta::validar(4, false, SorteoFiesta::LIGA, 1, 1);
    }

    public function test_liga_rechaza_mas_clasificados_que_equipos(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('grupo más chico tendría 3 equipos');
        SorteoFiesta::validar(6, true, SorteoFiesta::LIGA, 1, 4);
    }
}
