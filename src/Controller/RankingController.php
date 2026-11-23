<?php

declare(strict_types=1);

namespace PingPong\Controller;

use PingPong\Http\Controller;
use PingPong\Service\RankingHistoricoService;
use PingPong\Support\Request;
use PingPong\Support\View;

final class RankingController extends Controller
{
    public function __construct(View $view, private RankingHistoricoService $ranking)
    {
        parent::__construct($view);
    }

    public function index(): string
    {
        if (($guard = $this->requireAuth()) !== null) {
            return $guard;
        }

        $opciones = $this->ranking->opcionesFiltros();
        $filters = $this->filters($opciones);

        $rankingCompleto = $this->ranking->ranking($filters);
        $podio = array_slice($rankingCompleto, 0, 3);

        $busqueda = trim((string) Request::query('q', ''));
        $orden = $this->orden();

        $rankingFiltrado = $this->filtrarYOrdenar($rankingCompleto, $busqueda, $orden);
        $paginacion = $this->paginar($rankingFiltrado);

        return $this->render('ranking/index', [
            'title' => 'Ranking histórico',
            'ranking' => $rankingCompleto,
            'rankingFiltrado' => $rankingFiltrado,
            'podio' => $podio,
            'rows' => $paginacion['rows'],
            'paginacion' => $paginacion['meta'],
            'filters' => $filters,
            'busqueda' => $busqueda,
            'orden' => $orden,
            'opcionesFiltros' => $opciones,
        ]);
    }

    /**
     * Rebana el ranking completo segun los parametros per_page/page (estilo ATP).
     *
     * @param list<array<string,mixed>> $ranking
     * @return array{rows:list<array<string,mixed>>,meta:array{per_page:int|string,page:int,total:int,total_paginas:int,opciones:list<int>,desde:int,hasta:int}}
     */
    private function paginar(array $ranking): array
    {
        $opciones = [25, 50, 100, 150];
        $total = count($ranking);

        $perPageRaw = strtolower((string) Request::query('per_page', '25'));
        $perPage = $perPageRaw === 'all'
            ? 'all'
            : (in_array((int) $perPageRaw, $opciones, true) ? (int) $perPageRaw : 25);

        if ($perPage === 'all') {
            $page = 1;
            $totalPaginas = 1;
            $rows = $ranking;
            $desde = $total === 0 ? 0 : 1;
            $hasta = $total;
        } else {
            $totalPaginas = max(1, (int) ceil($total / $perPage));
            $page = max(1, (int) Request::query('page', '1'));
            $page = min($page, $totalPaginas);
            $offset = ($page - 1) * $perPage;
            $rows = array_slice($ranking, $offset, $perPage);
            $desde = $total === 0 ? 0 : $offset + 1;
            $hasta = min($offset + $perPage, $total);
        }

        return [
            'rows' => $rows,
            'meta' => [
                'per_page' => $perPage,
                'page' => $page,
                'total' => $total,
                'total_paginas' => $totalPaginas,
                'opciones' => $opciones,
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ];
    }

    /** @param array{modalidades:list<string>,years:list<int>,areas:list<string>,niveles:list<string>} $opciones @return array{modalidad:string,year:string,area:string,nivel:string} */
    private function filters(array $opciones): array
    {
        $modalidad = strtoupper((string) Request::query('modalidad', ''));
        if (!in_array($modalidad, $opciones['modalidades'], true)) {
            $modalidad = '';
        }

        $year = Request::query('year', '') ?? '';
        if ($year !== '' && !in_array((int) $year, $opciones['years'], true)) {
            $year = '';
        }

        $area = Request::query('area', '') ?? '';
        if ($area !== '' && !in_array($area, $opciones['areas'], true)) {
            $area = '';
        }

        $nivel = Request::query('nivel', '') ?? '';
        if ($nivel !== '' && !in_array($nivel, $opciones['niveles'], true)) {
            $nivel = '';
        }

        return [
            'modalidad' => $modalidad,
            'year' => $year,
            'area' => $area,
            'nivel' => $nivel,
        ];
    }

    private function orden(): string
    {
        $raw = strtolower(trim((string) Request::query('sort', 'score')));
        $permitidos = ['score', 'win_rate', 'campeonatos', 'partidos_ganados', 'diferencial_puntos'];
        return in_array($raw, $permitidos, true) ? $raw : 'score';
    }

    /**
     * Filtra por texto (nombre o área) preservando el rank real y aplica orden secundario opcional.
     *
     * @param list<array<string,mixed>> $ranking
     * @return list<array<string,mixed>>
     */
    private function filtrarYOrdenar(array $ranking, string $busqueda, string $orden): array
    {
        $lista = $ranking;

        if ($busqueda !== '') {
            $needle = mb_strtolower($busqueda);
            $lista = array_values(array_filter(
                $lista,
                static function (array $row) use ($needle): bool {
                    $nombre = mb_strtolower((string) ($row['nombre'] ?? ''));
                    $area = mb_strtolower((string) ($row['area'] ?? ''));
                    return str_contains($nombre, $needle) || str_contains($area, $needle);
                }
            ));
        }

        if ($orden !== 'score') {
            usort($lista, static function (array $a, array $b) use ($orden): int {
                $cmp = ($b[$orden] ?? 0) <=> ($a[$orden] ?? 0);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ((int) ($a['rank'] ?? 0)) <=> ((int) ($b['rank'] ?? 0));
            });
        }

        return $lista;
    }
}
