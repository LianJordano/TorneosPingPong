<?php

declare(strict_types=1);

namespace PingPong\Tests\Service;

use PHPUnit\Framework\TestCase;
use PingPong\Repository\EstadisticaJugadorRepositoryInterface;
use PingPong\Service\RankingHistoricoService;

final class RankingHistoricoServiceTest extends TestCase
{
    public function test_opciones_filtros_incluye_modalidades_y_anios_de_fiesta_y_torneo(): void
    {
        $repo = new FakeEstadisticaJugadorRepository(
            personas: [
                ['id' => 1, 'nombre' => 'Ana', 'apellido' => 'Gomez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
            ],
            torneos: [
                [
                    'origen' => 'TORNEO',
                    'id' => 1,
                    'nombre' => 'Liga 2025',
                    'modalidad' => 'INDIVIDUAL',
                    'puntos_por_victoria' => 2,
                    'fecha_inicio' => '2025-05-01',
                    'fecha_fin' => '2025-05-10',
                    'created_at' => '2025-05-01 10:00:00',
                ],
                [
                    'origen' => 'FIESTA',
                    'id' => 1,
                    'nombre' => 'Fiesta Verano 2026',
                    'modalidad' => 'DOBLES',
                    'puntos_por_victoria' => 2,
                    'fecha_inicio' => '2026-01-15',
                    'fecha_fin' => '2026-01-16',
                    'created_at' => '2026-01-10 12:00:00',
                ],
            ],
        );

        $service = new RankingHistoricoService($repo);
        $opciones = $service->opcionesFiltros();

        $this->assertSame(['INDIVIDUAL', 'DOBLES'], $opciones['modalidades']);
        $this->assertSame([2026, 2025], $opciones['years']);
        $this->assertSame(['Tech'], $opciones['areas']);
        $this->assertSame(['A'], $opciones['niveles']);
    }

    public function test_ranking_y_perfil_integran_fiesta_con_grupos_eliminatoria_podio_y_sin_sets_falsos(): void
    {
        $repo = new FakeEstadisticaJugadorRepository(
            personas: [
                ['id' => 1, 'nombre' => 'Ana', 'apellido' => 'Gomez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
                ['id' => 2, 'nombre' => 'Beto', 'apellido' => 'Perez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
                ['id' => 3, 'nombre' => 'Cami', 'apellido' => 'Ruiz', 'area' => 'Ops', 'nivel' => 'B', 'activo' => 1],
            ],
            torneos: [
                [
                    'origen' => 'FIESTA',
                    'id' => 10,
                    'nombre' => 'Copa Fiesta Individual',
                    'modalidad' => 'INDIVIDUAL',
                    'puntos_por_victoria' => 2,
                    'fecha_inicio' => '2026-11-20',
                    'fecha_fin' => '2026-11-20',
                    'created_at' => '2026-11-20 18:00:00',
                ],
            ],
            inscripciones: [
                [
                    'origen' => 'FIESTA', 'id' => 101, 'torneo_id' => 10,
                    'persona_id' => 1, 'persona_id_2' => null,
                    'torneo_nombre' => 'Copa Fiesta Individual', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-20', 'created_at' => '2026-11-20 18:00:00',
                    'persona_label' => 'Gomez, Ana', 'persona_2_label' => null, 'label' => 'Gomez, Ana',
                ],
                [
                    'origen' => 'FIESTA', 'id' => 102, 'torneo_id' => 10,
                    'persona_id' => 2, 'persona_id_2' => null,
                    'torneo_nombre' => 'Copa Fiesta Individual', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-20', 'created_at' => '2026-11-20 18:00:00',
                    'persona_label' => 'Perez, Beto', 'persona_2_label' => null, 'label' => 'Perez, Beto',
                ],
                [
                    'origen' => 'FIESTA', 'id' => 103, 'torneo_id' => 10,
                    'persona_id' => 3, 'persona_id_2' => null,
                    'torneo_nombre' => 'Copa Fiesta Individual', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-20', 'created_at' => '2026-11-20 18:00:00',
                    'persona_label' => 'Ruiz, Cami', 'persona_2_label' => null, 'label' => 'Ruiz, Cami',
                ],
            ],
            partidos: [
                // Fase GRUPOS: Ana vence a Beto 11-7
                [
                    'origen' => 'FIESTA', 'id' => 501, 'torneo_id' => 10,
                    'fase' => 'GRUPOS', 'ronda' => 1, 'orden_en_ronda' => 0,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 101, 'inscripcion_b_id' => 102, 'ganador_inscripcion_id' => 101,
                    'torneo_nombre' => 'Copa Fiesta Individual', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-20', 'created_at' => '2026-11-20 18:00:00',
                    'a_persona_id' => 1, 'a_persona_id_2' => null, 'b_persona_id' => 2, 'b_persona_id_2' => null,
                    'a_label' => 'Gomez, Ana', 'b_label' => 'Perez, Beto',
                    'sets_a' => 0, 'sets_b' => 0, 'puntos_a' => 11, 'puntos_b' => 7,
                ],
                // Fase ELIMINATORIA (semi): Beto vence a Cami 11-9 (Ana tuvo BYE en semi, no aparece en partidos reales)
                [
                    'origen' => 'FIESTA', 'id' => 502, 'torneo_id' => 10,
                    'fase' => 'ELIMINATORIA', 'ronda' => 1, 'orden_en_ronda' => 1,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 102, 'inscripcion_b_id' => 103, 'ganador_inscripcion_id' => 102,
                    'torneo_nombre' => 'Copa Fiesta Individual', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-20', 'created_at' => '2026-11-20 18:00:00',
                    'a_persona_id' => 2, 'a_persona_id_2' => null, 'b_persona_id' => 3, 'b_persona_id_2' => null,
                    'a_label' => 'Perez, Beto', 'b_label' => 'Ruiz, Cami',
                    'sets_a' => 0, 'sets_b' => 0, 'puntos_a' => 11, 'puntos_b' => 9,
                ],
                // Fase ELIMINATORIA (final): Ana vence a Beto 11-5
                [
                    'origen' => 'FIESTA', 'id' => 503, 'torneo_id' => 10,
                    'fase' => 'ELIMINATORIA', 'ronda' => 2, 'orden_en_ronda' => 0,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 101, 'inscripcion_b_id' => 102, 'ganador_inscripcion_id' => 101,
                    'torneo_nombre' => 'Copa Fiesta Individual', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-20', 'created_at' => '2026-11-20 18:00:00',
                    'a_persona_id' => 1, 'a_persona_id_2' => null, 'b_persona_id' => 2, 'b_persona_id_2' => null,
                    'a_label' => 'Gomez, Ana', 'b_label' => 'Perez, Beto',
                    'sets_a' => 0, 'sets_b' => 0, 'puntos_a' => 11, 'puntos_b' => 5,
                ],
            ],
            jugadoresPorTorneo: [
                'FIESTA:10' => [
                    ['id' => 101, 'nombre' => 'Gomez, Ana'],
                    ['id' => 102, 'nombre' => 'Perez, Beto'],
                    ['id' => 103, 'nombre' => 'Ruiz, Cami'],
                ],
            ],
            ligaPorTorneo: [
                'FIESTA:10' => [
                    ['a' => 101, 'b' => 102, 'ganador' => 101, 'setsA' => 0, 'setsB' => 0, 'puntosA' => 11, 'puntosB' => 7],
                ],
            ],
            bracketPorTorneo: [
                'FIESTA:10' => [
                    // BYE para Ana en ronda 1
                    ['a' => 101, 'b' => null, 'ganador' => 101, 'ronda' => 1, 'estado' => 'FINALIZADO'],
                    ['a' => 102, 'b' => 103, 'ganador' => 102, 'ronda' => 1, 'estado' => 'FINALIZADO'],
                    ['a' => 101, 'b' => 102, 'ganador' => 101, 'ronda' => 2, 'estado' => 'FINALIZADO'],
                ],
            ],
        );

        $service = new RankingHistoricoService($repo);
        $ranking = $service->ranking();

        $this->assertCount(3, $ranking);

        // #1 Ana: campeona, 1 victoria en GRUPOS (liga), 1 en ELIMINATORIA (bracket), 0 sets falsos
        $ana = $ranking[0];
        $this->assertSame(1, $ana['persona_id']);
        $this->assertSame(1, $ana['torneos_finalizados_jugados']);
        $this->assertSame(2, $ana['partidos_jugados']);
        $this->assertSame(2, $ana['partidos_ganados']);
        $this->assertSame(0, $ana['partidos_perdidos']);
        $this->assertSame(1, $ana['victorias_liga']);
        $this->assertSame(1, $ana['victorias_bracket']);
        $this->assertSame(0, $ana['sets_ganados']);
        $this->assertSame(0, $ana['sets_perdidos']);
        $this->assertSame(22, $ana['puntos_favor']);
        $this->assertSame(12, $ana['puntos_contra']);
        $this->assertSame(10, $ana['diferencial_puntos']);
        $this->assertSame(1, $ana['campeonatos']);
        $this->assertSame(0, $ana['segundos_lugares']);
        $this->assertSame(0, $ana['terceros_lugares']);
        $this->assertSame(100.0, $ana['win_rate']);
        $this->assertSame(2, $ana['racha_actual']);
        $this->assertSame(2, $ana['mejor_racha']);
        // score = 5*1 + 3*2 + 2 + 30*1 + 5*1 = 48
        $this->assertSame(48, $ana['score']);

        // #2 Beto: 2do lugar, 1 victoria bracket, 3 partidos jugados
        $beto = $ranking[1];
        $this->assertSame(2, $beto['persona_id']);
        $this->assertSame(1, $beto['segundos_lugares']);
        $this->assertSame(3, $beto['partidos_jugados']);
        $this->assertSame(1, $beto['partidos_ganados']);
        $this->assertSame(2, $beto['partidos_perdidos']);

        // #3 Cami: 3er lugar
        $cami = $ranking[2];
        $this->assertSame(3, $cami['persona_id']);
        $this->assertSame(1, $cami['terceros_lugares']);

        // Perfil de Ana: url /fiesta/10, origen FIESTA, score directo sin puntos duplicados
        $perfil = $service->perfil(1);
        $this->assertNotNull($perfil);
        $this->assertCount(1, $perfil['torneos']);
        $this->assertSame('FIESTA', $perfil['torneos'][0]['origen']);
        $this->assertSame('/fiesta/10', $perfil['torneos'][0]['url']);
        $this->assertSame(1, $perfil['torneos'][0]['posicion']);

        $this->assertCount(2, $perfil['partidosRecientes']);
        $this->assertSame('FIESTA', $perfil['partidosRecientes'][0]['origen']);
        $this->assertSame('/fiesta/10', $perfil['partidosRecientes'][0]['url']);
        $this->assertSame('11-5', $perfil['partidosRecientes'][0]['score']);
        $this->assertSame('', $perfil['partidosRecientes'][0]['puntos']);
    }

    public function test_fiesta_dobles_otorga_credito_a_ambos_jugadores_y_registra_companeros(): void
    {
        $repo = new FakeEstadisticaJugadorRepository(
            personas: [
                ['id' => 1, 'nombre' => 'Ana', 'apellido' => 'Gomez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
                ['id' => 2, 'nombre' => 'Beto', 'apellido' => 'Perez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
                ['id' => 3, 'nombre' => 'Cami', 'apellido' => 'Ruiz', 'area' => 'Ops', 'nivel' => 'B', 'activo' => 1],
                ['id' => 4, 'nombre' => 'Dani', 'apellido' => 'Soto', 'area' => 'Ops', 'nivel' => 'B', 'activo' => 1],
            ],
            torneos: [
                [
                    'origen' => 'FIESTA',
                    'id' => 5,
                    'nombre' => 'Fiesta Dobles',
                    'modalidad' => 'DOBLES',
                    'puntos_por_victoria' => 2,
                    'fecha_inicio' => '2026-11-21',
                    'fecha_fin' => '2026-11-21',
                    'created_at' => '2026-11-21 20:00:00',
                ],
            ],
            inscripciones: [
                [
                    'origen' => 'FIESTA', 'id' => 201, 'torneo_id' => 5,
                    'persona_id' => 1, 'persona_id_2' => 2,
                    'torneo_nombre' => 'Fiesta Dobles', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-11-21', 'fecha_fin' => '2026-11-21', 'created_at' => '2026-11-21 20:00:00',
                    'persona_label' => 'Gomez, Ana', 'persona_2_label' => 'Perez, Beto', 'label' => 'Gomez / Perez',
                ],
                [
                    'origen' => 'FIESTA', 'id' => 202, 'torneo_id' => 5,
                    'persona_id' => 3, 'persona_id_2' => 4,
                    'torneo_nombre' => 'Fiesta Dobles', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-11-21', 'fecha_fin' => '2026-11-21', 'created_at' => '2026-11-21 20:00:00',
                    'persona_label' => 'Ruiz, Cami', 'persona_2_label' => 'Soto, Dani', 'label' => 'Ruiz / Soto',
                ],
            ],
            partidos: [
                [
                    'origen' => 'FIESTA', 'id' => 601, 'torneo_id' => 5,
                    'fase' => 'ELIMINATORIA', 'ronda' => 1, 'orden_en_ronda' => 0,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 201, 'inscripcion_b_id' => 202, 'ganador_inscripcion_id' => 201,
                    'torneo_nombre' => 'Fiesta Dobles', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-11-21', 'fecha_fin' => '2026-11-21', 'created_at' => '2026-11-21 20:00:00',
                    'a_persona_id' => 1, 'a_persona_id_2' => 2, 'b_persona_id' => 3, 'b_persona_id_2' => 4,
                    'a_label' => 'Gomez / Perez', 'b_label' => 'Ruiz / Soto',
                    'sets_a' => 0, 'sets_b' => 0, 'puntos_a' => 15, 'puntos_b' => 12,
                ],
            ],
            jugadoresPorTorneo: [
                'FIESTA:5' => [
                    ['id' => 201, 'nombre' => 'Gomez / Perez'],
                    ['id' => 202, 'nombre' => 'Ruiz / Soto'],
                ],
            ],
            ligaPorTorneo: ['FIESTA:5' => []],
            bracketPorTorneo: [
                'FIESTA:5' => [
                    ['a' => 201, 'b' => 202, 'ganador' => 201, 'ronda' => 1, 'estado' => 'FINALIZADO'],
                ],
            ],
        );

        $service = new RankingHistoricoService($repo);

        $perfilAna = $service->perfil(1);
        $perfilBeto = $service->perfil(2);
        $this->assertNotNull($perfilAna);
        $this->assertNotNull($perfilBeto);

        foreach ([$perfilAna, $perfilBeto] as $perfil) {
            $this->assertSame(1, $perfil['stats']['campeonatos']);
            $this->assertSame(1, $perfil['stats']['dobles']['partidos']);
            $this->assertSame(1, $perfil['stats']['dobles']['ganados']);
            $this->assertSame(15, $perfil['stats']['puntos_favor']);
            $this->assertSame(12, $perfil['stats']['puntos_contra']);
        }

        $this->assertSame(2, $perfilAna['companeros'][0]['persona_id']);
        $this->assertSame(1, $perfilAna['companeros'][0]['torneos']);
        $this->assertSame(1, $perfilBeto['companeros'][0]['persona_id']);
        $this->assertSame(1, $perfilBeto['companeros'][0]['torneos']);
    }

    public function test_cero_colisiones_de_ids_entre_torneo_y_fiesta_con_mismos_ids(): void
    {
        $repo = new FakeEstadisticaJugadorRepository(
            personas: [
                ['id' => 1, 'nombre' => 'Ana', 'apellido' => 'Gomez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
                ['id' => 2, 'nombre' => 'Beto', 'apellido' => 'Perez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
                ['id' => 3, 'nombre' => 'Cami', 'apellido' => 'Ruiz', 'area' => 'Ops', 'nivel' => 'B', 'activo' => 1],
                ['id' => 4, 'nombre' => 'Dani', 'apellido' => 'Soto', 'area' => 'Ops', 'nivel' => 'B', 'activo' => 1],
            ],
            torneos: [
                // TORNEO id = 1
                [
                    'origen' => 'TORNEO', 'id' => 1, 'nombre' => 'Torneo Oficial #1', 'modalidad' => 'DOBLES',
                    'puntos_por_victoria' => 2, 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-05',
                    'created_at' => '2026-10-01 10:00:00',
                ],
                // FIESTA id = 1 (mismo id!)
                [
                    'origen' => 'FIESTA', 'id' => 1, 'nombre' => 'Fiesta Express #1', 'modalidad' => 'DOBLES',
                    'puntos_por_victoria' => 2, 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01',
                    'created_at' => '2026-11-01 10:00:00',
                ],
            ],
            inscripciones: [
                // TORNEO inscripcion id = 1 (Ana + Beto), id = 2 (Cami + Dani)
                [
                    'origen' => 'TORNEO', 'id' => 1, 'torneo_id' => 1, 'persona_id' => 1, 'persona_id_2' => 2,
                    'torneo_nombre' => 'Torneo Oficial #1', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-05', 'created_at' => '2026-10-01 10:00:00',
                    'persona_label' => 'Gomez, Ana', 'persona_2_label' => 'Perez, Beto', 'label' => 'Gomez / Perez',
                ],
                [
                    'origen' => 'TORNEO', 'id' => 2, 'torneo_id' => 1, 'persona_id' => 3, 'persona_id_2' => 4,
                    'torneo_nombre' => 'Torneo Oficial #1', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-05', 'created_at' => '2026-10-01 10:00:00',
                    'persona_label' => 'Ruiz, Cami', 'persona_2_label' => 'Soto, Dani', 'label' => 'Ruiz / Soto',
                ],
                // FIESTA equipo id = 1 (Cami + Dani — mismos IDs pero distinta pareja!), id = 2 (Ana + Beto)
                [
                    'origen' => 'FIESTA', 'id' => 1, 'torneo_id' => 1, 'persona_id' => 3, 'persona_id_2' => 4,
                    'torneo_nombre' => 'Fiesta Express #1', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01', 'created_at' => '2026-11-01 10:00:00',
                    'persona_label' => 'Ruiz, Cami', 'persona_2_label' => 'Soto, Dani', 'label' => 'Ruiz / Soto',
                ],
                [
                    'origen' => 'FIESTA', 'id' => 2, 'torneo_id' => 1, 'persona_id' => 1, 'persona_id_2' => 2,
                    'torneo_nombre' => 'Fiesta Express #1', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01', 'created_at' => '2026-11-01 10:00:00',
                    'persona_label' => 'Gomez, Ana', 'persona_2_label' => 'Perez, Beto', 'label' => 'Gomez / Perez',
                ],
            ],
            partidos: [
                // TORNEO final: Ana/Beto (id=1) ganan a Cami/Dani (id=2) 2-0 (22-14)
                [
                    'origen' => 'TORNEO', 'id' => 1, 'torneo_id' => 1,
                    'fase' => 'ELIMINATORIA', 'ronda' => 1, 'orden_en_ronda' => 0,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 1, 'inscripcion_b_id' => 2, 'ganador_inscripcion_id' => 1,
                    'torneo_nombre' => 'Torneo Oficial #1', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-05', 'created_at' => '2026-10-01 10:00:00',
                    'a_persona_id' => 1, 'a_persona_id_2' => 2, 'b_persona_id' => 3, 'b_persona_id_2' => 4,
                    'a_label' => 'Gomez / Perez', 'b_label' => 'Ruiz / Soto',
                    'sets_a' => 2, 'sets_b' => 0, 'puntos_a' => 22, 'puntos_b' => 14,
                ],
                // FIESTA final: Cami/Dani (id=1) ganan a Ana/Beto (id=2) 11-8
                [
                    'origen' => 'FIESTA', 'id' => 1, 'torneo_id' => 1,
                    'fase' => 'ELIMINATORIA', 'ronda' => 1, 'orden_en_ronda' => 0,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 1, 'inscripcion_b_id' => 2, 'ganador_inscripcion_id' => 1,
                    'torneo_nombre' => 'Fiesta Express #1', 'modalidad' => 'DOBLES',
                    'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-01', 'created_at' => '2026-11-01 10:00:00',
                    'a_persona_id' => 3, 'a_persona_id_2' => 4, 'b_persona_id' => 1, 'b_persona_id_2' => 2,
                    'a_label' => 'Ruiz / Soto', 'b_label' => 'Gomez / Perez',
                    'sets_a' => 0, 'sets_b' => 0, 'puntos_a' => 11, 'puntos_b' => 8,
                ],
            ],
            jugadoresPorTorneo: [
                'TORNEO:1' => [
                    ['id' => 1, 'nombre' => 'Gomez / Perez'],
                    ['id' => 2, 'nombre' => 'Ruiz / Soto'],
                ],
                'FIESTA:1' => [
                    ['id' => 1, 'nombre' => 'Ruiz / Soto'],
                    ['id' => 2, 'nombre' => 'Gomez / Perez'],
                ],
            ],
            ligaPorTorneo: ['TORNEO:1' => [], 'FIESTA:1' => []],
            bracketPorTorneo: [
                'TORNEO:1' => [
                    ['a' => 1, 'b' => 2, 'ganador' => 1, 'ronda' => 1, 'estado' => 'FINALIZADO'],
                ],
                'FIESTA:1' => [
                    ['a' => 1, 'b' => 2, 'ganador' => 1, 'ronda' => 1, 'estado' => 'FINALIZADO'],
                ],
            ],
        );

        $service = new RankingHistoricoService($repo);
        $perfilAna = $service->perfil(1);
        $this->assertNotNull($perfilAna);

        // Ana jugó 2 torneos (TORNEO:1 y FIESTA:1), ganó 1 y salió 2da en el otro
        $this->assertSame(2, $perfilAna['stats']['torneos_finalizados_jugados']);
        $this->assertSame(1, $perfilAna['stats']['campeonatos']);
        $this->assertSame(1, $perfilAna['stats']['segundos_lugares']);
        $this->assertSame(2, $perfilAna['stats']['partidos_jugados']);
        $this->assertSame(1, $perfilAna['stats']['partidos_ganados']);
        $this->assertSame(1, $perfilAna['stats']['partidos_perdidos']);
        // Sets solo del torneo oficial (2-0), fiesta no suma sets falsos
        $this->assertSame(2, $perfilAna['stats']['sets_ganados']);
        $this->assertSame(0, $perfilAna['stats']['sets_perdidos']);
        // Puntos combinados: 22 + 8 = 30 a favor, 14 + 11 = 25 en contra
        $this->assertSame(30, $perfilAna['stats']['puntos_favor']);
        $this->assertSame(25, $perfilAna['stats']['puntos_contra']);
        // Racha: ganó en octubre, perdió en noviembre => racha_actual = 0, mejor_racha = 1
        $this->assertSame(0, $perfilAna['stats']['racha_actual']);
        $this->assertSame(1, $perfilAna['stats']['mejor_racha']);

        // Compañero Beto aparece con 2 torneos juntos (no 1 por colisión de ID!)
        $this->assertSame(2, $perfilAna['companeros'][0]['torneos']);

        // Historial tiene ambos torneos con sus URLs y posiciones correctas
        $this->assertCount(2, $perfilAna['torneos']);
        $this->assertSame('FIESTA', $perfilAna['torneos'][0]['origen']);
        $this->assertSame('/fiesta/1', $perfilAna['torneos'][0]['url']);
        $this->assertSame(2, $perfilAna['torneos'][0]['posicion']);
        $this->assertSame('TORNEO', $perfilAna['torneos'][1]['origen']);
        $this->assertSame('/torneos/1', $perfilAna['torneos'][1]['url']);
        $this->assertSame(1, $perfilAna['torneos'][1]['posicion']);

        // Partidos recientes: fiesta muestra score directo, torneo muestra sets y puntos
        $this->assertCount(2, $perfilAna['partidosRecientes']);
        $this->assertSame('FIESTA', $perfilAna['partidosRecientes'][0]['origen']);
        $this->assertSame('11-8', $perfilAna['partidosRecientes'][0]['score']);
        $this->assertSame('', $perfilAna['partidosRecientes'][0]['puntos']);
        $this->assertSame('TORNEO', $perfilAna['partidosRecientes'][1]['origen']);
        $this->assertSame('2-0', $perfilAna['partidosRecientes'][1]['score']);
        $this->assertSame('22-14', $perfilAna['partidosRecientes'][1]['puntos']);
    }

    public function test_filtros_por_modalidad_y_anio_aplican_a_torneos_y_fiestas(): void
    {
        $repo = new FakeEstadisticaJugadorRepository(
            personas: [
                ['id' => 1, 'nombre' => 'Ana', 'apellido' => 'Gomez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
                ['id' => 2, 'nombre' => 'Beto', 'apellido' => 'Perez', 'area' => 'Tech', 'nivel' => 'A', 'activo' => 1],
            ],
            torneos: [
                [
                    'origen' => 'TORNEO', 'id' => 1, 'nombre' => 'Oficial 2025', 'modalidad' => 'INDIVIDUAL',
                    'puntos_por_victoria' => 2, 'fecha_inicio' => '2025-04-01', 'fecha_fin' => '2025-04-10',
                    'created_at' => '2025-04-01 10:00:00',
                ],
                [
                    'origen' => 'FIESTA', 'id' => 2, 'nombre' => 'Fiesta 2026', 'modalidad' => 'INDIVIDUAL',
                    'puntos_por_victoria' => 2, 'fecha_inicio' => '2026-06-15', 'fecha_fin' => '2026-06-15',
                    'created_at' => '2026-06-15 10:00:00',
                ],
            ],
            inscripciones: [
                [
                    'origen' => 'TORNEO', 'id' => 10, 'torneo_id' => 1, 'persona_id' => 1, 'persona_id_2' => null,
                    'torneo_nombre' => 'Oficial 2025', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2025-04-01', 'fecha_fin' => '2025-04-10', 'created_at' => '2025-04-01 10:00:00',
                    'persona_label' => 'Gomez, Ana', 'persona_2_label' => null, 'label' => 'Gomez, Ana',
                ],
                [
                    'origen' => 'TORNEO', 'id' => 11, 'torneo_id' => 1, 'persona_id' => 2, 'persona_id_2' => null,
                    'torneo_nombre' => 'Oficial 2025', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2025-04-01', 'fecha_fin' => '2025-04-10', 'created_at' => '2025-04-01 10:00:00',
                    'persona_label' => 'Perez, Beto', 'persona_2_label' => null, 'label' => 'Perez, Beto',
                ],
                [
                    'origen' => 'FIESTA', 'id' => 20, 'torneo_id' => 2, 'persona_id' => 1, 'persona_id_2' => null,
                    'torneo_nombre' => 'Fiesta 2026', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-06-15', 'fecha_fin' => '2026-06-15', 'created_at' => '2026-06-15 10:00:00',
                    'persona_label' => 'Gomez, Ana', 'persona_2_label' => null, 'label' => 'Gomez, Ana',
                ],
                [
                    'origen' => 'FIESTA', 'id' => 21, 'torneo_id' => 2, 'persona_id' => 2, 'persona_id_2' => null,
                    'torneo_nombre' => 'Fiesta 2026', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-06-15', 'fecha_fin' => '2026-06-15', 'created_at' => '2026-06-15 10:00:00',
                    'persona_label' => 'Perez, Beto', 'persona_2_label' => null, 'label' => 'Perez, Beto',
                ],
            ],
            partidos: [
                [
                    'origen' => 'TORNEO', 'id' => 100, 'torneo_id' => 1, 'fase' => 'ELIMINATORIA', 'ronda' => 1, 'orden_en_ronda' => 0,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 10, 'inscripcion_b_id' => 11, 'ganador_inscripcion_id' => 10,
                    'torneo_nombre' => 'Oficial 2025', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2025-04-01', 'fecha_fin' => '2025-04-10', 'created_at' => '2025-04-01 10:00:00',
                    'a_persona_id' => 1, 'a_persona_id_2' => null, 'b_persona_id' => 2, 'b_persona_id_2' => null,
                    'a_label' => 'Gomez, Ana', 'b_label' => 'Perez, Beto',
                    'sets_a' => 2, 'sets_b' => 0, 'puntos_a' => 22, 'puntos_b' => 10,
                ],
                [
                    'origen' => 'FIESTA', 'id' => 200, 'torneo_id' => 2, 'fase' => 'ELIMINATORIA', 'ronda' => 1, 'orden_en_ronda' => 0,
                    'estado' => 'FINALIZADO', 'tipo_resultado' => 'NORMAL',
                    'inscripcion_a_id' => 20, 'inscripcion_b_id' => 21, 'ganador_inscripcion_id' => 21,
                    'torneo_nombre' => 'Fiesta 2026', 'modalidad' => 'INDIVIDUAL',
                    'fecha_inicio' => '2026-06-15', 'fecha_fin' => '2026-06-15', 'created_at' => '2026-06-15 10:00:00',
                    'a_persona_id' => 1, 'a_persona_id_2' => null, 'b_persona_id' => 2, 'b_persona_id_2' => null,
                    'a_label' => 'Gomez, Ana', 'b_label' => 'Perez, Beto',
                    'sets_a' => 0, 'sets_b' => 0, 'puntos_a' => 7, 'puntos_b' => 11,
                ],
            ],
            jugadoresPorTorneo: [
                'TORNEO:1' => [['id' => 10, 'nombre' => 'Gomez, Ana'], ['id' => 11, 'nombre' => 'Perez, Beto']],
                'FIESTA:2' => [['id' => 20, 'nombre' => 'Gomez, Ana'], ['id' => 21, 'nombre' => 'Perez, Beto']],
            ],
            ligaPorTorneo: ['TORNEO:1' => [], 'FIESTA:2' => []],
            bracketPorTorneo: [
                'TORNEO:1' => [['a' => 10, 'b' => 11, 'ganador' => 10, 'ronda' => 1, 'estado' => 'FINALIZADO']],
                'FIESTA:2' => [['a' => 20, 'b' => 21, 'ganador' => 21, 'ronda' => 1, 'estado' => 'FINALIZADO']],
            ],
        );

        $service = new RankingHistoricoService($repo);
        $ranking2026 = $service->ranking(['year' => '2026', 'modalidad' => 'INDIVIDUAL']);

        $this->assertSame(2, $ranking2026[0]['persona_id']);
        $this->assertSame(1, $ranking2026[0]['campeonatos']);
        $this->assertSame(1, $ranking2026[0]['torneos_finalizados_jugados']);
        $this->assertSame(11, $ranking2026[0]['puntos_favor']);
    }
}

final class FakeEstadisticaJugadorRepository implements EstadisticaJugadorRepositoryInterface
{
    /**
     * @param list<array<string,mixed>> $personas
     * @param list<array<string,mixed>> $torneos
     * @param list<array<string,mixed>> $inscripciones
     * @param list<array<string,mixed>> $partidos
     * @param array<string,list<array{id:int,nombre:string}>> $jugadoresPorTorneo
     * @param array<string,list<array{a:int,b:int,ganador:?int,setsA:int,setsB:int,puntosA:int,puntosB:int}>> $ligaPorTorneo
     * @param array<string,list<array{a:?int,b:?int,ganador:?int,ronda:int,estado:string}>> $bracketPorTorneo
     */
    public function __construct(
        private array $personas = [],
        private array $torneos = [],
        private array $inscripciones = [],
        private array $partidos = [],
        private array $jugadoresPorTorneo = [],
        private array $ligaPorTorneo = [],
        private array $bracketPorTorneo = [],
    ) {
    }

    public function personas(): array
    {
        return $this->personas;
    }

    public function torneosFinalizados(): array
    {
        return $this->torneos;
    }

    public function inscripcionesFinalizadas(): array
    {
        return $this->inscripciones;
    }

    public function partidosFinalizadosReales(): array
    {
        return $this->partidos;
    }

    public function ligaAgregada(int $torneoId, string $origen = 'TORNEO'): array
    {
        return $this->ligaPorTorneo[$origen . ':' . $torneoId] ?? [];
    }

    public function jugadoresTorneo(int $torneoId, string $origen = 'TORNEO'): array
    {
        return $this->jugadoresPorTorneo[$origen . ':' . $torneoId] ?? [];
    }

    public function bracketPodio(int $torneoId, string $origen = 'TORNEO'): array
    {
        return $this->bracketPorTorneo[$origen . ':' . $torneoId] ?? [];
    }
}
