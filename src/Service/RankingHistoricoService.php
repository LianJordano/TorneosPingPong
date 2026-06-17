<?php

declare(strict_types=1);

namespace PingPong\Service;

use PingPong\Domain\PodioCalculator;
use PingPong\Domain\TablaPosiciones;
use PingPong\Repository\EstadisticaJugadorRepository;

final class RankingHistoricoService
{
    public function __construct(private EstadisticaJugadorRepository $repo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function ranking(array $filters = []): array
    {
        $ranking = $this->calcular($filters)['ranking'];
        return array_values($ranking);
    }

    /** @return array{modalidades:list<string>,years:list<int>,areas:list<string>,niveles:list<string>} */
    public function opcionesFiltros(): array
    {
        $years = [];
        $modalidades = [];
        foreach ($this->repo->torneosFinalizados() as $torneo) {
            $modalidad = (string) ($torneo['modalidad'] ?? '');
            if ($modalidad !== '') {
                $modalidades[$modalidad] = true;
            }

            $fecha = $this->fechaTorneo($torneo);
            if ($fecha !== '') {
                $years[(int) substr($fecha, 0, 4)] = true;
            }
        }

        $areas = [];
        $niveles = [];
        foreach ($this->repo->personas() as $persona) {
            $area = trim((string) ($persona['area'] ?? ''));
            if ($area !== '') {
                $areas[$area] = true;
            }

            $nivel = trim((string) ($persona['nivel'] ?? ''));
            if ($nivel !== '') {
                $niveles[$nivel] = true;
            }
        }

        $years = array_keys($years);
        rsort($years, SORT_NUMERIC);
        $areas = array_keys($areas);
        $niveles = array_keys($niveles);
        sort($areas, SORT_NATURAL | SORT_FLAG_CASE);
        sort($niveles, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'modalidades' => array_values(array_intersect(['INDIVIDUAL', 'DOBLES'], array_keys($modalidades))),
            'years' => array_values($years),
            'areas' => array_values($areas),
            'niveles' => array_values($niveles),
        ];
    }

    /** @return array<string,mixed>|null */
    public function perfil(int $personaId): ?array
    {
        $data = $this->calcular();
        if (!isset($data['personas'][$personaId])) {
            return null;
        }

        $ranking = $data['rankingPorPersona'][$personaId] ?? null;
        $stats = $data['stats'][$personaId] ?? $this->statsBase($data['personas'][$personaId]);
        $partidos = array_values(array_filter(
            $data['partidosVista'],
            static fn (array $m): bool => in_array($personaId, $m['personas'], true)
        ));
        usort($partidos, static fn (array $a, array $b): int => strcmp($b['orden_fecha'], $a['orden_fecha']) ?: ($b['id'] <=> $a['id']));

        $historial = $data['historialTorneos'][$personaId] ?? [];
        usort($historial, static fn (array $a, array $b): int => strcmp($b['orden_fecha'], $a['orden_fecha']) ?: ($b['torneo_id'] <=> $a['torneo_id']));

        return [
            'persona'          => $data['personas'][$personaId],
            'ranking'          => $ranking,
            'stats'            => $stats,
            'torneos'          => $historial,
            'partidosRecientes' => array_slice($partidos, 0, 15),
            'companeros'       => array_slice($data['companeros'][$personaId] ?? [], 0, 8),
        ];
    }

    /** @return array{ranking:list<array<string,mixed>>,rankingPorPersona:array<int,array<string,mixed>>,stats:array<int,array<string,mixed>>,personas:array<int,array<string,mixed>>,historialTorneos:array<int,list<array<string,mixed>>>,partidosVista:list<array<string,mixed>>,companeros:array<int,list<array<string,mixed>>>} */
    private function calcular(array $filters = []): array
    {
        $personas = [];
        foreach ($this->repo->personas() as $p) {
            $p['id'] = (int) $p['id'];
            $p['nombre_completo'] = trim((string) $p['apellido'] . ', ' . (string) $p['nombre']);
            $personas[$p['id']] = $p;
        }

        $stats = [];
        foreach ($personas as $id => $persona) {
            if (!$this->incluyePersona($persona, $filters)) {
                continue;
            }
            $stats[$id] = $this->statsBase($persona);
        }

        $inscripciones = $this->repo->inscripcionesFinalizadas();
        $inscripcionPersonas = [];
        $historial = [];
        $companeros = [];
        foreach ($inscripciones as $i) {
            if (!$this->incluyeTorneo($i, $filters)) {
                continue;
            }

            $inscId = (int) $i['id'];
            $torneoId = (int) $i['torneo_id'];
            $ids = array_values(array_filter([
                (int) $i['persona_id'],
                $i['persona_id_2'] !== null ? (int) $i['persona_id_2'] : null,
            ], static fn (?int $id): bool => $id !== null));
            $inscripcionPersonas[$inscId] = $ids;

            foreach ($ids as $personaId) {
                if (!isset($stats[$personaId])) {
                    continue;
                }
                $stats[$personaId]['torneosIds'][$torneoId] = true;
                $stats[$personaId]['modalidades'][$i['modalidad']] = ($stats[$personaId]['modalidades'][$i['modalidad']] ?? 0) + 1;
                $historial[$personaId][$torneoId] = [
                    'torneo_id' => $torneoId,
                    'nombre' => (string) $i['torneo_nombre'],
                    'modalidad' => (string) $i['modalidad'],
                    'fecha' => $this->fechaTorneo($i),
                    'orden_fecha' => $this->ordenFecha($i),
                    'equipo' => (string) $i['label'],
                    'posicion' => null,
                ];
            }

            if (count($ids) === 2) {
                [$a, $b] = $ids;
                $this->sumarCompanero($companeros, $a, $b, $personas[$b]['nombre_completo'] ?? 'Jugador #' . $b, $torneoId);
                $this->sumarCompanero($companeros, $b, $a, $personas[$a]['nombre_completo'] ?? 'Jugador #' . $a, $torneoId);
            }
        }

        $podios = $this->calcularPodios($inscripcionPersonas, $filters);
        foreach ($podios as $torneoId => $filas) {
            foreach ($filas as $fila) {
                $pos = (int) $fila['posicion'];
                foreach ($inscripcionPersonas[(int) $fila['insc_id']] ?? [] as $personaId) {
                    if (!isset($stats[$personaId])) {
                        continue;
                    }
                    if ($pos === 1) {
                        $stats[$personaId]['campeonatos']++;
                    } elseif ($pos === 2) {
                        $stats[$personaId]['segundos_lugares']++;
                    } elseif ($pos === 3) {
                        $stats[$personaId]['terceros_lugares']++;
                    }
                    $stats[$personaId]['mejor_finish'] = $stats[$personaId]['mejor_finish'] === null ? $pos : min($stats[$personaId]['mejor_finish'], $pos);
                    if (isset($historial[$personaId][$torneoId])) {
                        $historial[$personaId][$torneoId]['posicion'] = $pos;
                    }
                }
            }
        }

        $partidosVista = [];
        $secuencia = [];
        foreach ($this->repo->partidosFinalizadosReales() as $m) {
            if (!$this->incluyeTorneo($m, $filters)) {
                continue;
            }

            $aIds = $inscripcionPersonas[(int) $m['inscripcion_a_id']] ?? [];
            $bIds = $inscripcionPersonas[(int) $m['inscripcion_b_id']] ?? [];
            if ($aIds === [] || $bIds === []) {
                continue;
            }

            $ganador = (int) $m['ganador_inscripcion_id'];
            $ganaA = $ganador === (int) $m['inscripcion_a_id'];
            $ganaB = $ganador === (int) $m['inscripcion_b_id'];
            $setsA = (int) $m['sets_a'];
            $setsB = (int) $m['sets_b'];
            $puntosA = (int) $m['puntos_a'];
            $puntosB = (int) $m['puntos_b'];
            $fase = (string) $m['fase'];

            foreach ($aIds as $personaId) {
                if (!isset($stats[$personaId])) {
                    continue;
                }
                $this->sumarPartido($stats[$personaId], $m, $ganaA, $setsA, $setsB, $puntosA, $puntosB);
                $secuencia[$personaId][] = $ganaA;
            }
            foreach ($bIds as $personaId) {
                if (!isset($stats[$personaId])) {
                    continue;
                }
                $this->sumarPartido($stats[$personaId], $m, $ganaB, $setsB, $setsA, $puntosB, $puntosA);
                $secuencia[$personaId][] = $ganaB;
            }

            $partidosVista[] = [
                'id' => (int) $m['id'],
                'torneo_id' => (int) $m['torneo_id'],
                'torneo_nombre' => (string) $m['torneo_nombre'],
                'fecha' => $this->fechaTorneo($m),
                'orden_fecha' => $this->ordenFecha($m),
                'fase' => $fase,
                'a_label' => (string) $m['a_label'],
                'b_label' => (string) $m['b_label'],
                'score' => $setsA . '-' . $setsB,
                'puntos' => $puntosA . '-' . $puntosB,
                'ganador_label' => $ganaA ? (string) $m['a_label'] : ($ganaB ? (string) $m['b_label'] : 'Sin ganador'),
                'personas' => array_merge($aIds, $bIds),
            ];
        }

        foreach ($stats as $personaId => &$s) {
            $s['torneos_finalizados_jugados'] = count($s['torneosIds']);
            unset($s['torneosIds']);
            $s['partidos_perdidos'] = max(0, $s['partidos_jugados'] - $s['partidos_ganados']);
            $s['win_rate'] = $s['partidos_jugados'] > 0 ? round($s['partidos_ganados'] * 100 / $s['partidos_jugados'], 1) : 0.0;
            $s['diferencial_puntos'] = $s['puntos_favor'] - $s['puntos_contra'];
            $s['diferencial_sets'] = $s['sets_ganados'] - $s['sets_perdidos'];
            $s['podios'] = $s['campeonatos'] + $s['segundos_lugares'] + $s['terceros_lugares'];
            $s['score'] = (5 * $s['torneos_finalizados_jugados'])
                + (3 * $s['partidos_ganados'])
                + $s['partidos_jugados']
                + (30 * $s['campeonatos'])
                + (20 * $s['segundos_lugares'])
                + (12 * $s['terceros_lugares'])
                + (5 * $s['victorias_bracket']);
            [$s['racha_actual'], $s['mejor_racha']] = $this->rachas($secuencia[$personaId] ?? []);
        }
        unset($s);

        $ranking = array_values($stats);
        usort($ranking, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score']
                ?: $b['campeonatos'] <=> $a['campeonatos']
                ?: $b['segundos_lugares'] <=> $a['segundos_lugares']
                ?: $b['terceros_lugares'] <=> $a['terceros_lugares']
                ?: $b['win_rate'] <=> $a['win_rate']
                ?: $b['diferencial_puntos'] <=> $a['diferencial_puntos']
                ?: strcmp($a['nombre'], $b['nombre']);
        });

        $rankingPorPersona = [];
        foreach ($ranking as $idx => &$fila) {
            $fila['rank'] = $idx + 1;
            $rankingPorPersona[(int) $fila['persona_id']] = $fila;
        }
        unset($fila);

        foreach ($companeros as &$lista) {
            usort($lista, static fn (array $a, array $b): int => $b['torneos'] <=> $a['torneos'] ?: strcmp($a['nombre'], $b['nombre']));
            foreach ($lista as &$companero) {
                unset($companero['torneosIds']);
            }
            unset($companero);
        }
        unset($lista);

        $historialPorPersona = [];
        foreach ($historial as $personaId => $items) {
            $historialPorPersona[$personaId] = array_values($items);
        }

        return [
            'ranking' => $ranking,
            'rankingPorPersona' => $rankingPorPersona,
            'stats' => $stats,
            'personas' => $personas,
            'historialTorneos' => $historialPorPersona,
            'partidosVista' => $partidosVista,
            'companeros' => $companeros,
        ];
    }

    /** @param array<string,mixed> $persona @return array<string,mixed> */
    private function statsBase(array $persona): array
    {
        return [
            'persona_id' => (int) $persona['id'],
            'nombre' => (string) $persona['nombre_completo'],
            'area' => $persona['area'] ?? null,
            'nivel' => $persona['nivel'] ?? null,
            'activo' => (int) $persona['activo'],
            'torneosIds' => [],
            'torneos_finalizados_jugados' => 0,
            'partidos_jugados' => 0,
            'partidos_ganados' => 0,
            'partidos_perdidos' => 0,
            'victorias_liga' => 0,
            'victorias_bracket' => 0,
            'sets_ganados' => 0,
            'sets_perdidos' => 0,
            'puntos_favor' => 0,
            'puntos_contra' => 0,
            'diferencial_puntos' => 0,
            'diferencial_sets' => 0,
            'campeonatos' => 0,
            'segundos_lugares' => 0,
            'terceros_lugares' => 0,
            'podios' => 0,
            'mejor_finish' => null,
            'win_rate' => 0.0,
            'score' => 0,
            'racha_actual' => 0,
            'mejor_racha' => 0,
            'modalidades' => [],
            'individual' => ['partidos' => 0, 'ganados' => 0],
            'dobles' => ['partidos' => 0, 'ganados' => 0],
        ];
    }

    /** @param array<string,mixed> $stats @param array<string,mixed> $m */
    private function sumarPartido(array &$stats, array $m, bool $gano, int $setsFavor, int $setsContra, int $puntosFavor, int $puntosContra): void
    {
        $stats['partidos_jugados']++;
        $stats['sets_ganados'] += $setsFavor;
        $stats['sets_perdidos'] += $setsContra;
        $stats['puntos_favor'] += $puntosFavor;
        $stats['puntos_contra'] += $puntosContra;
        $bucket = $m['modalidad'] === 'DOBLES' ? 'dobles' : 'individual';
        $stats[$bucket]['partidos']++;
        if ($gano) {
            $stats['partidos_ganados']++;
            $stats[$bucket]['ganados']++;
            if ($m['fase'] === 'ELIMINATORIA') {
                $stats['victorias_bracket']++;
            } else {
                $stats['victorias_liga']++;
            }
        }
    }

    /** @param array<int,list<array<string,mixed>>> $companeros */
    private function sumarCompanero(array &$companeros, int $personaId, int $companeroId, string $nombre, int $torneoId): void
    {
        $companeros[$personaId][$companeroId] ??= ['persona_id' => $companeroId, 'nombre' => $nombre, 'torneosIds' => []];
        $companeros[$personaId][$companeroId]['torneosIds'][$torneoId] = true;
        $companeros[$personaId][$companeroId]['torneos'] = count($companeros[$personaId][$companeroId]['torneosIds']);
    }

    /** @param array<int,list<int>> $inscripcionPersonas @param array<string,mixed> $filters @return array<int,list<array<string,mixed>>> */
    private function calcularPodios(array $inscripcionPersonas, array $filters = []): array
    {
        $podios = [];
        foreach ($this->repo->torneosFinalizados() as $torneo) {
            if (!$this->incluyeTorneo($torneo, $filters)) {
                continue;
            }

            $torneoId = (int) $torneo['id'];
            $bracket = $this->repo->bracketPodio($torneoId);
            if ($bracket === []) {
                continue;
            }
            $totalRondas = max(array_map(static fn (array $p): int => (int) $p['ronda'], $bracket));
            $tabla = TablaPosiciones::calcular(
                $this->repo->jugadoresTorneo($torneoId),
                $this->repo->ligaAgregada($torneoId),
                (int) $torneo['puntos_por_victoria']
            );
            $posicionLiga = [];
            foreach ($tabla as $fila) {
                $posicionLiga[(int) $fila['insc_id']] = (int) $fila['posicion'];
            }
            $podios[$torneoId] = array_values(array_filter(
                PodioCalculator::calcular($bracket, $totalRondas, $posicionLiga),
                static fn (array $fila): bool => isset($inscripcionPersonas[(int) $fila['insc_id']])
            ));
        }
        return $podios;
    }

    /** @param array<string,mixed> $persona @param array<string,mixed> $filters */
    private function incluyePersona(array $persona, array $filters): bool
    {
        if (($filters['area'] ?? '') !== '' && (string) ($persona['area'] ?? '') !== (string) $filters['area']) {
            return false;
        }

        if (($filters['nivel'] ?? '') !== '' && (string) ($persona['nivel'] ?? '') !== (string) $filters['nivel']) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $filters */
    private function incluyeTorneo(array $row, array $filters): bool
    {
        if (($filters['modalidad'] ?? '') !== '' && (string) ($row['modalidad'] ?? '') !== (string) $filters['modalidad']) {
            return false;
        }

        if (($filters['year'] ?? '') !== '' && (int) substr($this->fechaTorneo($row), 0, 4) !== (int) $filters['year']) {
            return false;
        }

        return true;
    }

    /** @param list<bool> $resultados @return array{0:int,1:int} */
    private function rachas(array $resultados): array
    {
        $actual = 0;
        for ($i = count($resultados) - 1; $i >= 0; $i--) {
            if (!$resultados[$i]) {
                break;
            }
            $actual++;
        }

        $mejor = 0;
        $corriente = 0;
        foreach ($resultados as $gano) {
            $corriente = $gano ? $corriente + 1 : 0;
            $mejor = max($mejor, $corriente);
        }
        return [$actual, $mejor];
    }

    /** @param array<string,mixed> $row */
    private function fechaTorneo(array $row): string
    {
        return (string) ($row['fecha_fin'] ?: ($row['fecha_inicio'] ?: substr((string) $row['created_at'], 0, 10)));
    }

    /** @param array<string,mixed> $row */
    private function ordenFecha(array $row): string
    {
        return $this->fechaTorneo($row) . ' ' . str_pad((string) ($row['torneo_id'] ?? $row['id'] ?? 0), 10, '0', STR_PAD_LEFT);
    }
}
