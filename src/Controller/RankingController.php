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

        $ranking = $this->ranking->ranking($filters);
        $paginacion = $this->paginar($ranking);

        return $this->render('ranking/index', [
            'title' => 'Ranking historico',
            'ranking' => $ranking,
            'rows' => $paginacion['rows'],
            'paginacion' => $paginacion['meta'],
            'filters' => $filters,
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
}
