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

    public function test_validar_config_en_liga_fuerza_un_grupo_y_acepta_clasificados_validos(): void
    {
        [$data, $errors] = FiestaService::validarConfig([
            'nombre'                 => 'Liga Nocturna',
            'modalidad'              => 'INDIVIDUAL',
            'formato'                => SorteoFiesta::LIGA,
            'num_grupos'             => '99',
            'clasificados_por_grupo' => '4',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame(SorteoFiesta::LIGA, $data['formato']);
        $this->assertSame(1, $data['num_grupos']);
        $this->assertSame(4, $data['clasificados_por_grupo']);
    }

    public function test_validar_config_en_liga_rechaza_menos_de_dos_o_mas_de_ocho_clasificados(): void
    {
        [, $errorsBajo] = FiestaService::validarConfig([
            'nombre'                 => 'Liga Nocturna',
            'modalidad'              => 'DOBLES',
            'formato'                => SorteoFiesta::LIGA,
            'num_grupos'             => '1',
            'clasificados_por_grupo' => '1',
        ]);
        $this->assertArrayHasKey('clasificados_por_grupo', $errorsBajo);
        $this->assertArrayNotHasKey('num_grupos', $errorsBajo);

        [, $errorsAlto] = FiestaService::validarConfig([
            'nombre'                 => 'Liga Nocturna',
            'modalidad'              => 'DOBLES',
            'formato'                => SorteoFiesta::LIGA,
            'num_grupos'             => '1',
            'clasificados_por_grupo' => '9',
        ]);
        $this->assertArrayHasKey('clasificados_por_grupo', $errorsAlto);
    }

    public function test_construir_guion_para_liga_individual_incluye_pasos_liga_y_participantes(): void
    {
        $fiesta = ['modalidad' => 'INDIVIDUAL', 'formato' => SorteoFiesta::LIGA, 'num_grupos' => 1];
        $participantes = [
            ['id' => 10, 'nombre' => 'Ana'],
            ['id' => 20, 'nombre' => 'Beto'],
            ['id' => 30, 'nombre' => 'Cami'],
            ['id' => 40, 'nombre' => 'Dani'],
        ];
        $sorteo = SorteoFiesta::sortear([10, 20, 30, 40], false, SorteoFiesta::LIGA, 1, static fn (array $xs): array => $xs, 2);

        $guion = FiestaService::construirGuion($fiesta, $participantes, $sorteo);

        $this->assertSame('INDIVIDUAL', $guion['modalidad']);
        $this->assertSame(SorteoFiesta::LIGA, $guion['formato']);
        $this->assertSame(1, $guion['numGrupos']);
        $this->assertSame([10 => 'Ana', 20 => 'Beto', 30 => 'Cami', 40 => 'Dani'], $guion['participantes']);
        $this->assertSame([
            ['tipo' => 'liga', 'equipo' => 1, 'participante' => 10],
            ['tipo' => 'liga', 'equipo' => 2, 'participante' => 20],
            ['tipo' => 'liga', 'equipo' => 3, 'participante' => 30],
            ['tipo' => 'liga', 'equipo' => 4, 'participante' => 40],
        ], $guion['pasos']);
        $this->assertSame([], $guion['cruces']);
        $this->assertSame(0, $guion['rondas']);
    }

    public function test_validar_config_acepta_mejor_de_1_3_5_y_usa_default_1(): void
    {
        [$defaultData, $defaultErrors] = FiestaService::validarConfig([
            'nombre'    => 'Fiesta Default',
            'modalidad' => 'DOBLES',
            'formato'   => SorteoFiesta::ELIMINACION,
        ]);
        $this->assertSame([], $defaultErrors);
        $this->assertSame(1, $defaultData['mejor_de']);

        foreach ([1, 3, 5] as $mejorDe) {
            [$data, $errors] = FiestaService::validarConfig([
                'nombre'    => 'Fiesta Bo' . $mejorDe,
                'modalidad' => 'INDIVIDUAL',
                'formato'   => SorteoFiesta::ELIMINACION,
                'mejor_de'  => (string) $mejorDe,
            ]);
            $this->assertSame([], $errors);
            $this->assertSame($mejorDe, $data['mejor_de']);
        }
    }

    public function test_validar_config_rechaza_mejor_de_invalido(): void
    {
        foreach (['0', '2', '4', '7', 'abc'] as $invalido) {
            [, $errors] = FiestaService::validarConfig([
                'nombre'    => 'Fiesta Invalida',
                'modalidad' => 'DOBLES',
                'formato'   => SorteoFiesta::ELIMINACION,
                'mejor_de'  => $invalido,
            ]);
            $this->assertArrayHasKey('mejor_de', $errors, "Expected error for mejor_de={$invalido}");
        }
    }

    public function test_validar_marcador_sets_en_bo1_devuelve_marcador_sin_sets(): void
    {
        $res = FiestaService::validarMarcadorSets(1, ['11'], ['8']);

        $this->assertSame([
            'marcador_a' => 11,
            'marcador_b' => 8,
            'sets'       => null,
        ], $res);
    }

    public function test_validar_marcador_sets_acepta_2_0_y_2_1_en_bo3_y_3_2_en_bo5(): void
    {
        $bo3DosCero = FiestaService::validarMarcadorSets(3, ['11', '11', ''], ['7', '9', '']);
        $this->assertSame([
            'marcador_a' => 2,
            'marcador_b' => 0,
            'sets'       => [
                ['a' => 11, 'b' => 7],
                ['a' => 11, 'b' => 9],
            ],
        ], $bo3DosCero);

        $bo3DosUno = FiestaService::validarMarcadorSets(3, ['11', '8', '11'], ['9', '11', '6']);
        $this->assertSame([
            'marcador_a' => 2,
            'marcador_b' => 1,
            'sets'       => [
                ['a' => 11, 'b' => 9],
                ['a' => 8, 'b' => 11],
                ['a' => 11, 'b' => 6],
            ],
        ], $bo3DosUno);

        $bo5TresDos = FiestaService::validarMarcadorSets(5, ['11', '9', '11', '7', '12'], ['8', '11', '5', '11', '10']);
        $this->assertSame([
            'marcador_a' => 3,
            'marcador_b' => 2,
            'sets'       => [
                ['a' => 11, 'b' => 8],
                ['a' => 9, 'b' => 11],
                ['a' => 11, 'b' => 5],
                ['a' => 7, 'b' => 11],
                ['a' => 12, 'b' => 10],
            ],
        ], $bo5TresDos);
    }

    public function test_validar_marcador_sets_rechaza_empate_en_un_set(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('empate');
        FiestaService::validarMarcadorSets(3, ['11', '10'], ['7', '10']);
    }

    public function test_validar_marcador_sets_rechaza_partido_incompleto_1_1_en_bo3(): void
    {
        $this->expectException(\DomainException::class);
        FiestaService::validarMarcadorSets(3, ['11', '8', ''], ['7', '11', '']);
    }

    public function test_validar_marcador_sets_rechaza_set_extra_despues_de_2_0(): void
    {
        $this->expectException(\DomainException::class);
        FiestaService::validarMarcadorSets(3, ['11', '11', '11'], ['7', '9', '4']);
    }

    public function test_validar_marcador_sets_rechaza_set_a_medias(): void
    {
        $this->expectException(\DomainException::class);
        FiestaService::validarMarcadorSets(3, ['11', '11'], ['7', '']);
    }

    public function test_validar_marcador_sets_rechaza_hueco_entre_sets(): void
    {
        $this->expectException(\DomainException::class);
        FiestaService::validarMarcadorSets(3, ['11', '', '11'], ['7', '', '9']);
    }

    public function test_validar_config_serializa_overrides_de_sets_por_ronda_y_null_si_hereda_todo(): void
    {
        [$sinOverrides, $errSin] = FiestaService::validarConfig([
            'nombre'         => 'Fiesta Base',
            'modalidad'      => 'DOBLES',
            'formato'        => SorteoFiesta::GRUPOS,
            'num_grupos'     => '2',
            'clasificados_por_grupo' => '2',
            'mejor_de'       => '1',
            'mejor_de_elim'  => '',
            'mejor_de_semis' => '',
            'mejor_de_final' => '',
        ]);
        $this->assertSame([], $errSin);
        $this->assertNull($sinOverrides['sets_rondas_json']);

        [$conOverrides, $errCon] = FiestaService::validarConfig([
            'nombre'         => 'Fiesta Progresiva',
            'modalidad'      => 'INDIVIDUAL',
            'formato'        => SorteoFiesta::GRUPOS,
            'num_grupos'     => '2',
            'clasificados_por_grupo' => '2',
            'mejor_de'       => '1',
            'mejor_de_elim'  => '3',
            'mejor_de_semis' => '',
            'mejor_de_final' => '5',
        ]);
        $this->assertSame([], $errCon);
        $this->assertSame('{"eliminatoria":3,"final":5}', $conOverrides['sets_rondas_json']);

        [, $errInvalido] = FiestaService::validarConfig([
            'nombre'         => 'Fiesta Mala',
            'modalidad'      => 'DOBLES',
            'formato'        => SorteoFiesta::ELIMINACION,
            'mejor_de'       => '1',
            'mejor_de_final' => '7',
        ]);
        $this->assertArrayHasKey('mejor_de_final', $errInvalido);
    }

    public function test_mejor_de_para_partido_resuelve_grupos_cuartos_semis_y_final_con_y_sin_overrides(): void
    {
        // Sin overrides: todo hereda mejor_de = 3
        $fiestaSin = ['mejor_de' => 3, 'sets_rondas_json' => null];
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaSin, ['fase' => 'GRUPOS', 'ronda' => 1, 'siguiente_id' => null], 3));
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaSin, ['fase' => 'ELIMINATORIA', 'ronda' => 1, 'siguiente_id' => 10], 3));
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaSin, ['fase' => 'ELIMINATORIA', 'ronda' => 2, 'siguiente_id' => 20], 3));
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaSin, ['fase' => 'ELIMINATORIA', 'ronda' => 3, 'siguiente_id' => null], 3));

        // Con overrides: grupos = 1 (base), eliminatoria = 3, semis hereda eliminatoria (3), final = 5
        $fiestaParcial = [
            'mejor_de'         => 1,
            'sets_rondas_json' => '{"eliminatoria":3,"final":5}',
        ];
        $this->assertSame(1, FiestaService::mejorDeParaPartido($fiestaParcial, ['fase' => 'GRUPOS', 'ronda' => 1, 'siguiente_id' => null], 3));
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaParcial, ['fase' => 'ELIMINATORIA', 'ronda' => 1, 'siguiente_id' => 10], 3));
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaParcial, ['fase' => 'ELIMINATORIA', 'ronda' => 2, 'siguiente_id' => 20], 3));
        $this->assertSame(5, FiestaService::mejorDeParaPartido($fiestaParcial, ['fase' => 'ELIMINATORIA', 'ronda' => 3, 'siguiente_id' => null], 3));

        // Con override específico en semis (semifinal = 5, eliminatoria = 3, base = 1, sin override final => hereda eliminatoria = 3)
        $fiestaSemis = [
            'mejor_de'         => 1,
            'sets_rondas_json' => '{"eliminatoria":3,"semifinal":5}',
        ];
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaSemis, ['fase' => 'ELIMINATORIA', 'ronda' => 1, 'siguiente_id' => 10], 3));
        $this->assertSame(5, FiestaService::mejorDeParaPartido($fiestaSemis, ['fase' => 'ELIMINATORIA', 'ronda' => 2, 'siguiente_id' => 20], 3));
        $this->assertSame(3, FiestaService::mejorDeParaPartido($fiestaSemis, ['fase' => 'ELIMINATORIA', 'ronda' => 3, 'siguiente_id' => null], 3));
    }
}
