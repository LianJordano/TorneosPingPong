<?php

declare(strict_types=1);

namespace PingPong\Tests\Service;

use PHPUnit\Framework\TestCase;
use PingPong\Domain\Fiesta\SorteoFiesta;
use PingPong\Service\FiestaService;

final class FiestaServiceTest extends TestCase
{
    public function test_validar_config_en_eliminacion_ignora_campos_de_grupos_y_usa_defaults(): void
    {
        [$data, $errors] = FiestaService::validarConfig([
            'nombre'                 => 'Torneo Express',
            'modalidad'              => 'DOBLES',
            'formato'                => SorteoFiesta::ELIMINACION,
            'num_grupos'             => '0',
            'clasificados_por_grupo' => '99',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame(2, $data['num_grupos']);
        $this->assertSame(2, $data['clasificados_por_grupo']);
    }

    public function test_validar_config_en_grupos_rechaza_valores_fuera_de_rango(): void
    {
        [, $errors] = FiestaService::validarConfig([
            'nombre'                 => 'Copa Verano',
            'modalidad'              => 'INDIVIDUAL',
            'formato'                => SorteoFiesta::GRUPOS,
            'num_grupos'             => '0',
            'clasificados_por_grupo' => '9',
        ]);

        $this->assertArrayHasKey('num_grupos', $errors);
        $this->assertArrayHasKey('clasificados_por_grupo', $errors);
    }

    public function test_validar_config_en_grupos_rechaza_decimales(): void
    {
        [, $errors] = FiestaService::validarConfig([
            'nombre'                 => 'Copa Verano',
            'modalidad'              => 'INDIVIDUAL',
            'formato'                => SorteoFiesta::GRUPOS,
            'num_grupos'             => '2.5',
            'clasificados_por_grupo' => '1.2',
        ]);

        $this->assertArrayHasKey('num_grupos', $errors);
        $this->assertArrayHasKey('clasificados_por_grupo', $errors);
    }

    public function test_validar_marcador_acepta_enteros_validos_sin_empate(): void
    {
        $this->assertSame([11, 7], FiestaService::validarMarcador('11', '7'));
        $this->assertSame([0, 3], FiestaService::validarMarcador('0', '3'));
    }

    public function test_validar_marcador_rechaza_decimales_y_texto(): void
    {
        $this->expectException(\DomainException::class);
        FiestaService::validarMarcador('3.5', '1');
    }

    public function test_validar_marcador_rechaza_empate(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('empate');
        FiestaService::validarMarcador('11', '11');
    }

    public function test_validar_marcador_rechaza_negativos(): void
    {
        $this->expectException(\DomainException::class);
        FiestaService::validarMarcador('-1', '5');
    }

    public function test_partidos_bloqueados_marca_solo_los_que_tienen_siguiente_finalizado(): void
    {
        $eliminatoria = [
            ['id' => 10, 'ronda' => 1, 'siguiente_id' => 20, 'estado' => 'FINALIZADO'],
            ['id' => 11, 'ronda' => 1, 'siguiente_id' => 20, 'estado' => 'FINALIZADO'],
            ['id' => 12, 'ronda' => 1, 'siguiente_id' => 21, 'estado' => 'FINALIZADO'],
            ['id' => 20, 'ronda' => 2, 'siguiente_id' => 30, 'estado' => 'FINALIZADO'],
            ['id' => 21, 'ronda' => 2, 'siguiente_id' => 30, 'estado' => 'PENDIENTE'],
            ['id' => 30, 'ronda' => 3, 'siguiente_id' => null, 'estado' => 'PENDIENTE'],
        ];

        $bloqueados = FiestaService::partidosBloqueados($eliminatoria);

        $this->assertSame([10 => true, 11 => true], $bloqueados);
    }

    public function test_puede_editar_partido_bloquea_cuando_siguiente_ya_se_jugo(): void
    {
        $fiesta = ['estado' => 'ELIMINATORIA'];
        $partido = [
            'id'           => 10,
            'fase'         => 'ELIMINATORIA',
            'es_bye'       => 0,
            'equipo_a_id'  => 1,
            'equipo_b_id'  => 2,
            'siguiente_id' => 20,
        ];

        $this->assertFalse(FiestaService::puedeEditarPartido($partido, $fiesta, true, true, [10 => true]));
        $this->assertTrue(FiestaService::puedeEditarPartido($partido, $fiesta, true, false, []));
    }

    public function test_puede_editar_partido_permite_solo_a_admin_corregir_la_final_en_finalizado(): void
    {
        $fiesta = ['estado' => 'FINALIZADO'];
        $final = [
            'id'           => 30,
            'fase'         => 'ELIMINATORIA',
            'es_bye'       => 0,
            'equipo_a_id'  => 1,
            'equipo_b_id'  => 2,
            'siguiente_id' => null,
        ];
        $semi = [
            'id'           => 20,
            'fase'         => 'ELIMINATORIA',
            'es_bye'       => 0,
            'equipo_a_id'  => 1,
            'equipo_b_id'  => 2,
            'siguiente_id' => 30,
        ];

        $this->assertTrue(FiestaService::puedeEditarPartido($final, $fiesta, true, true, []));
        $this->assertFalse(FiestaService::puedeEditarPartido($final, $fiesta, true, false, []));
        $this->assertFalse(FiestaService::puedeEditarPartido($semi, $fiesta, true, true, []));
    }

    public function test_construir_guion_mantiene_esquema_compatible_con_ruleta(): void
    {
        $fiesta = ['modalidad' => 'DOBLES', 'formato' => 'ELIMINACION', 'num_grupos' => 2];
        $participantes = [
            ['id' => 1, 'nombre' => 'Ana'],
            ['id' => 2, 'nombre' => 'Beto'],
            ['id' => 3, 'nombre' => 'Cami'],
            ['id' => 4, 'nombre' => 'Dani'],
        ];
        $sorteo = SorteoFiesta::sortear([1, 2, 3, 4], true, SorteoFiesta::ELIMINACION, 1, static fn (array $xs): array => $xs);

        $guion = FiestaService::construirGuion($fiesta, $participantes, $sorteo);

        $this->assertSame('DOBLES', $guion['modalidad']);
        $this->assertSame('ELIMINACION', $guion['formato']);
        $this->assertSame(2, $guion['numGrupos']);
        $this->assertSame([1 => 'Ana', 2 => 'Beto', 3 => 'Cami', 4 => 'Dani'], $guion['participantes']);
        $this->assertArrayHasKey('equipos', $guion);
        $this->assertArrayHasKey('pasos', $guion);
        $this->assertArrayHasKey('cruces', $guion);
        $this->assertSame(1, $guion['rondas']);
    }
}
