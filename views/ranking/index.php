<?php
/** @var list<array<string,mixed>> $ranking */
/** @var list<array<string,mixed>> $rankingFiltrado */
/** @var list<array<string,mixed>> $podio */
/** @var list<array<string,mixed>> $rows */
/** @var array{per_page:int|string,page:int,total:int,total_paginas:int,opciones:list<int>,desde:int,hasta:int} $paginacion */
/** @var array{modalidad:string,year:string,area:string,nivel:string} $filters */
/** @var string $busqueda */
/** @var string $orden */
/** @var array{modalidades:list<string>,years:list<int>,areas:list<string>,niveles:list<string>} $opcionesFiltros */

$fmtPct = static fn (float $v): string => number_format($v, 1, ',', '.') . '%';
$fmtDiff = static fn (int $v): string => ($v > 0 ? '+' : '') . (string) $v;
$top = $ranking[0] ?? null;
$maxScore = $top !== null ? max(1, (int) $top['score']) : 1;

$perPage = $paginacion['per_page'];
$paginaActual = $paginacion['page'];
$totalPaginas = $paginacion['total_paginas'];

$baseQuery = array_filter([
    'modalidad' => $filters['modalidad'],
    'year' => $filters['year'],
    'area' => $filters['area'],
    'nivel' => $filters['nivel'],
    'q' => $busqueda,
    'sort' => $orden !== 'score' ? $orden : '',
    'per_page' => $perPage !== 25 ? (string) $perPage : '',
], static fn (string $v): bool => $v !== '');

$rankingUrl = static function (array $extra) use ($baseQuery): string {
    $q = $baseQuery;
    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = (string) $v;
        }
    }
    return '/ranking' . ($q === [] ? '' : '?' . http_build_query($q));
};

$modalidadLabel = static fn (string $m): string => match ($m) {
    'INDIVIDUAL' => 'Individual',
    'DOBLES' => 'Dobles',
    default => $m,
};

$activeFilters = [];
if ($filters['modalidad'] !== '') {
    $activeFilters[] = ['label' => 'Modalidad: ' . $modalidadLabel($filters['modalidad']), 'clear' => ['modalidad' => null, 'page' => null]];
}
if ($filters['year'] !== '') {
    $activeFilters[] = ['label' => 'Año: ' . $filters['year'], 'clear' => ['year' => null, 'page' => null]];
}
if ($filters['area'] !== '') {
    $activeFilters[] = ['label' => 'Área: ' . $filters['area'], 'clear' => ['area' => null, 'page' => null]];
}
if ($filters['nivel'] !== '') {
    $activeFilters[] = ['label' => 'Nivel: ' . $filters['nivel'], 'clear' => ['nivel' => null, 'page' => null]];
}
if ($busqueda !== '') {
    $activeFilters[] = ['label' => 'Búsqueda: "' . $busqueda . '"', 'clear' => ['q' => null, 'page' => null]];
}

$medallas = [1 => ['gold', 'Oro'], 2 => ['silver', 'Plata'], 3 => ['bronze', 'Bronce']];
$mostrarPodio = $podio !== [] && $busqueda === '' && $paginaActual === 1 && $orden === 'score';
$podioOrdenado = [];
if (isset($podio[1])) {
    $podioOrdenado[] = ['slot' => 'silver', 'pos' => 2, 'etiqueta' => '2.º puesto', 'data' => $podio[1]];
}
if (isset($podio[0])) {
    $podioOrdenado[] = ['slot' => 'gold', 'pos' => 1, 'etiqueta' => 'Número 1', 'data' => $podio[0]];
}
if (isset($podio[2])) {
    $podioOrdenado[] = ['slot' => 'bronze', 'pos' => 3, 'etiqueta' => '3.º puesto', 'data' => $podio[2]];
}

$sortLink = static function (string $campo, string $label, ?string $title = null) use ($orden, $rankingUrl): string {
    $activo = $orden === $campo;
    $href = $rankingUrl(['sort' => $campo === 'score' ? null : $campo, 'page' => null]);
    $inner = $title !== null ? '<abbr title="' . e($title) . '">' . e($label) . '</abbr>' : e($label);
    return '<a class="sort-link' . ($activo ? ' is-active' : '') . '" href="' . e($href) . '">'
        . $inner
        . ($activo ? ' <span class="rk-sort-dot" aria-hidden="true">▼</span>' : '')
        . '</a>';
};

$totalPartidos = (int) array_sum(array_column($ranking, 'partidos_jugados'));
$totalCampeonatos = (int) array_sum(array_column($ranking, 'campeonatos'));
$mejorEfectividad = $ranking !== [] ? (float) max(array_column($ranking, 'win_rate')) : 0.0;
?>

<section class="hero ranking-hero" aria-labelledby="ranking-title">
    <div class="hero-grid">
        <div>
            <span class="eyebrow"><?= icon('ranking') ?> Competencia</span>
            <h1 id="ranking-title">Ranking histórico</h1>
            <p>Clasificación acumulada de torneos finalizados: títulos, podios, victorias y constancia en cancha.</p>
            <div class="meta-row">
                <span class="meta-pill"><?= icon('users') ?> <?= e((string) count($ranking)) ?> jugador<?= count($ranking) === 1 ? '' : 'es' ?></span>
                <span class="meta-pill"><?= icon('paddle') ?> <?= e((string) $totalPartidos) ?> partidos</span>
                <span class="meta-pill"><?= icon('trophy') ?> <?= e((string) $totalCampeonatos) ?> título<?= $totalCampeonatos === 1 ? '' : 's' ?></span>
                <span class="meta-pill"><?= icon('target') ?> Mejor efectividad: <?= e($fmtPct($mejorEfectividad)) ?></span>
            </div>
        </div>
        <div class="hero-side rk-mode-side">
            <span class="rk-mode-side__label">Vista rápida</span>
            <nav class="rk-mode-tabs" aria-label="Filtrar por modalidad">
                <a class="rk-mode-tab<?= $filters['modalidad'] === '' ? ' is-active' : '' ?>"
                   href="<?= e($rankingUrl(['modalidad' => null, 'page' => null])) ?>">Todas</a>
                <?php foreach ($opcionesFiltros['modalidades'] as $mod): ?>
                    <a class="rk-mode-tab<?= $filters['modalidad'] === $mod ? ' is-active' : '' ?>"
                       href="<?= e($rankingUrl(['modalidad' => $mod, 'page' => null])) ?>"><?= e($modalidadLabel($mod)) ?></a>
                <?php endforeach; ?>
            </nav>
            <p class="rk-mode-side__hint">Los BYE no suman · En dobles suman ambos integrantes.</p>
        </div>
    </div>
</section>

<?php if ($mostrarPodio): ?>
<section class="rk-podium" aria-label="Podio del ranking">
    <?php foreach ($podioOrdenado as $item): ?>
        <?php
        $d = $item['data'];
        $slot = $item['slot'];
        $pos = $item['pos'];
        ?>
        <a class="rk-podium__card rk-podium__card--<?= e($slot) ?>" href="/personas/<?= e((string) $d['persona_id']) ?>">
            <div class="rk-podium__top">
                <span class="rk-podium__rank">
                    <?= $pos === 1 ? icon('crown') : icon('medal') ?> #<?= e((string) $pos) ?> · <?= e($item['etiqueta']) ?>
                </span>
                <span class="rk-podium__rate"><?= e($fmtPct((float) $d['win_rate'])) ?></span>
            </div>

            <div class="rk-podium__player">
                <span class="avatar rk-podium__avatar" aria-hidden="true"><?= e(initials((string) $d['nombre'])) ?></span>
                <div class="rk-podium__id">
                    <strong class="rk-podium__name"><?= e((string) $d['nombre']) ?></strong>
                    <span class="rk-podium__meta"><?= e((string) ($d['area'] ?? 'Sin área')) ?> · <?= e((string) ($d['nivel'] ?? 'Sin nivel')) ?></span>
                </div>
            </div>

            <div class="rk-podium__score">
                <strong class="num"><?= e((string) $d['score']) ?></strong>
                <span>pts</span>
            </div>

            <div class="rk-podium__stats">
                <span title="Campeonatos"><?= icon('trophy') ?> <?= e((string) $d['campeonatos']) ?> título<?= (int) $d['campeonatos'] === 1 ? '' : 's' ?></span>
                <span title="Partidos ganados / perdidos"><?= e((string) $d['partidos_ganados']) ?>G · <?= e((string) $d['partidos_perdidos']) ?>P</span>
                <span title="Torneos jugados"><?= e((string) $d['torneos_finalizados_jugados']) ?> torneos</span>
            </div>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="card ranking-filters" aria-labelledby="ranking-filters-title">
    <h2 id="ranking-filters-title" class="sr-only">Filtros y búsqueda del ranking</h2>
    <form class="toolbar ranking-toolbar-form" method="get" action="/ranking">
        <?php if ($orden !== 'score'): ?>
            <input type="hidden" name="sort" value="<?= e($orden) ?>">
        <?php endif; ?>
        <?php if ($perPage !== 25): ?>
            <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
        <?php endif; ?>

        <div class="field rk-search-field">
            <label for="rk-q">Buscar jugador</label>
            <input id="rk-q" type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre o área…" autocomplete="off">
        </div>
        <div class="field">
            <label for="rk-modalidad">Modalidad</label>
            <select id="rk-modalidad" name="modalidad">
                <option value="">Todas</option>
                <?php foreach ($opcionesFiltros['modalidades'] as $modalidad): ?>
                    <option value="<?= e($modalidad) ?>" <?= $filters['modalidad'] === $modalidad ? 'selected' : '' ?>><?= e($modalidadLabel($modalidad)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="rk-year">Año</label>
            <select id="rk-year" name="year">
                <option value="">Todos</option>
                <?php foreach ($opcionesFiltros['years'] as $year): ?>
                    <option value="<?= e((string) $year) ?>" <?= $filters['year'] === (string) $year ? 'selected' : '' ?>><?= e((string) $year) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="rk-area">Área</label>
            <select id="rk-area" name="area">
                <option value="">Todas</option>
                <?php foreach ($opcionesFiltros['areas'] as $area): ?>
                    <option value="<?= e($area) ?>" <?= $filters['area'] === $area ? 'selected' : '' ?>><?= e($area) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="rk-nivel">Nivel</label>
            <select id="rk-nivel" name="nivel">
                <option value="">Todos</option>
                <?php foreach ($opcionesFiltros['niveles'] as $nivel): ?>
                    <option value="<?= e($nivel) ?>" <?= $filters['nivel'] === $nivel ? 'selected' : '' ?>><?= e($nivel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <button class="btn btn--primary" type="submit"><?= icon('filter') ?> Filtrar</button>
            <?php if ($activeFilters !== [] || $orden !== 'score'): ?>
                <a class="btn btn--ghost" href="/ranking"><?= icon('x') ?> Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($activeFilters !== []): ?>
        <div class="filter-summary cluster" aria-live="polite">
            <span class="muted small">Activos:</span>
            <?php foreach ($activeFilters as $chip): ?>
                <a class="rk-filter-chip" href="<?= e($rankingUrl($chip['clear'])) ?>" title="Quitar filtro">
                    <?= e($chip['label']) ?> <span aria-hidden="true">×</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card rk-table-card" aria-labelledby="ranking-table-title">
    <div class="section-head">
        <div>
            <span class="eyebrow">Clasificación oficial</span>
            <h2 id="ranking-table-title">Tabla general</h2>
        </div>
        <div class="rk-table-head-right">
            <?php if ($orden !== 'score'): ?>
                <a class="badge badge--blue" href="<?= e($rankingUrl(['sort' => null, 'page' => null])) ?>" title="Volver al orden oficial por puntaje">
                    Ordenado por <?= e(match ($orden) {
                        'win_rate' => 'Efectividad',
                        'campeonatos' => 'Títulos',
                        'partidos_ganados' => 'Victorias',
                        'diferencial_puntos' => 'Dif. puntos',
                        default => 'Puntaje',
                    }) ?> ×
                </a>
            <?php endif; ?>
            <span class="badge badge--gray">Fórmula v1</span>
        </div>
    </div>

    <?php if ($rankingFiltrado === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('ranking') ?></span>
            <p class="empty-state__title">Sin jugadores para mostrar</p>
            <p><?= $busqueda !== '' ? 'No encontramos jugadores que coincidan con "' . e($busqueda) . '".' : 'No hay jugadores con historial para los filtros seleccionados.' ?></p>
            <?php if ($activeFilters !== []): ?>
                <div class="actions"><a class="btn btn--secondary" href="/ranking">Quitar filtros</a></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="spread ranking-listbar">
            <p class="muted small mb-0">
                Mostrando <strong class="num"><?= e((string) $paginacion['desde']) ?>–<?= e((string) $paginacion['hasta']) ?></strong>
                de <strong class="num"><?= e((string) $paginacion['total']) ?></strong> jugador<?= $paginacion['total'] === 1 ? '' : 'es' ?>
                <?php if ($busqueda !== ''): ?>
                    <span class="muted"> (se conserva el puesto real del ranking)</span>
                <?php endif; ?>
            </p>
            <nav class="per-page" aria-label="Jugadores por página">
                <span class="per-page__label">Ver</span>
                <?php foreach ($paginacion['opciones'] as $opcion): ?>
                    <a class="per-page__opt" href="<?= e($rankingUrl(['per_page' => (string) $opcion, 'page' => null])) ?>"<?= $perPage === $opcion ? ' aria-current="true"' : '' ?>><?= e((string) $opcion) ?></a>
                <?php endforeach; ?>
                <a class="per-page__opt" href="<?= e($rankingUrl(['per_page' => 'all', 'page' => null])) ?>"<?= $perPage === 'all' ? ' aria-current="true"' : '' ?>>Todos</a>
            </nav>
        </div>

        <div class="table-wrap">
            <table class="table table--stack ranking-table">
                <thead>
                    <tr>
                        <th scope="col">Pos.</th>
                        <th scope="col">Jugador</th>
                        <th scope="col" class="text-right<?= $orden === 'score' ? ' is-sorted' : '' ?>"><?= $sortLink('score', 'Puntaje') ?></th>
                        <th scope="col" class="text-right<?= $orden === 'partidos_ganados' ? ' is-sorted' : '' ?>"><?= $sortLink('partidos_ganados', 'Récord', 'Partidos ganados y perdidos') ?></th>
                        <th scope="col" class="text-right<?= $orden === 'win_rate' ? ' is-sorted' : '' ?>"><?= $sortLink('win_rate', 'Efectividad') ?></th>
                        <th scope="col" class="text-right<?= $orden === 'campeonatos' ? ' is-sorted' : '' ?>"><?= $sortLink('campeonatos', 'Podios', '1.º / 2.º / 3.º puesto') ?></th>
                        <th scope="col" class="text-right"><abbr title="Torneos finalizados / Victorias en eliminatoria">Torn. · Elim.</abbr></th>
                        <th scope="col" class="text-right<?= $orden === 'diferencial_puntos' ? ' is-sorted' : '' ?>"><?= $sortLink('diferencial_puntos', 'Dif. pts', 'Diferencia de puntos') ?></th>
                        <th scope="col">Modalidad</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $fila): ?>
                    <?php
                    $rank = (int) $fila['rank'];
                    $medalla = $medallas[$rank] ?? null;
                    $score = (int) $fila['score'];
                    $scorePct = min(100, (int) round(($score / $maxScore) * 100));
                    $winRate = (float) $fila['win_rate'];
                    $rateTier = $winRate >= 65.0 ? 'high' : ($winRate >= 45.0 ? 'mid' : 'low');
                    $dif = (int) $fila['diferencial_puntos'];
                    $difTier = $dif > 0 ? 'pos' : ($dif < 0 ? 'neg' : 'zero');
                    $c1 = (int) $fila['campeonatos'];
                    $c2 = (int) $fila['segundos_lugares'];
                    $c3 = (int) $fila['terceros_lugares'];
                    ?>
                    <tr class="rank-row<?= $medalla !== null ? ' rank-row--' . $medalla[0] : '' ?>">
                        <td data-label="Posición" class="num">
                            <?php if ($medalla !== null): ?>
                                <span class="medal-badge medal-badge--<?= $medalla[0] ?>"><?= $rank === 1 ? icon('crown') : icon('medal') ?> #<?= e((string) $rank) ?><span class="sr-only"> (<?= $medalla[1] ?>)</span></span>
                            <?php else: ?>
                                <span class="rank-num">#<?= e((string) $rank) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="cell--primary">
                            <a class="player-cell" href="/personas/<?= e((string) $fila['persona_id']) ?>">
                                <span class="avatar" aria-hidden="true"><?= e(initials((string) $fila['nombre'])) ?></span>
                                <span>
                                    <span class="player-cell__name"><?= e((string) $fila['nombre']) ?></span>
                                    <span class="table__sub"><?= e((string) ($fila['area'] ?? 'Sin área')) ?> · <?= e((string) ($fila['nivel'] ?? 'Sin nivel')) ?></span>
                                </span>
                            </a>
                        </td>
                        <td data-label="Puntaje" class="num">
                            <div class="rk-score-cell">
                                <strong class="rk-score-pill"><?= e((string) $score) ?></strong>
                                <span class="rk-score-bar" aria-hidden="true"><span style="width: <?= e((string) $scorePct) ?>%"></span></span>
                            </div>
                        </td>
                        <td data-label="Récord (G-P)" class="num">
                            <span class="rk-record">
                                <span class="rk-record__w"><?= e((string) $fila['partidos_ganados']) ?>G</span>
                                <span class="rk-record__sep" aria-hidden="true">·</span>
                                <span class="rk-record__l"><?= e((string) $fila['partidos_perdidos']) ?>P</span>
                            </span>
                        </td>
                        <td data-label="Efectividad" class="num">
                            <div class="rk-rate">
                                <span class="rk-rate__val"><?= e($fmtPct($winRate)) ?></span>
                                <span class="rk-rate__bar rk-rate__bar--<?= $rateTier ?>" aria-hidden="true">
                                    <span style="width: <?= e((string) min(100, (int) round($winRate))) ?>%"></span>
                                </span>
                            </div>
                        </td>
                        <td data-label="Podios" class="num">
                            <?php if ($c1 + $c2 + $c3 === 0): ?>
                                <span class="muted">–</span>
                            <?php else: ?>
                                <span class="rk-medals">
                                    <?php if ($c1 > 0): ?><span class="rk-medal rk-medal--gold" title="<?= e((string) $c1) ?> campeonato(s)">1.º×<?= e((string) $c1) ?></span><?php endif; ?>
                                    <?php if ($c2 > 0): ?><span class="rk-medal rk-medal--silver" title="<?= e((string) $c2) ?> segundo(s) lugar(es)">2.º×<?= e((string) $c2) ?></span><?php endif; ?>
                                    <?php if ($c3 > 0): ?><span class="rk-medal rk-medal--bronze" title="<?= e((string) $c3) ?> tercer(os) lugar(es)">3.º×<?= e((string) $c3) ?></span><?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Torneos · Eliminatoria" class="num">
                            <span class="rk-activity">
                                <strong><?= e((string) $fila['torneos_finalizados_jugados']) ?></strong>
                                <span class="muted">· <?= e((string) $fila['victorias_bracket']) ?> elim.</span>
                            </span>
                        </td>
                        <td data-label="Diferencia de puntos" class="num">
                            <span class="rk-diff rk-diff--<?= $difTier ?>"><?= e($fmtDiff($dif)) ?></span>
                        </td>
                        <td data-label="Modalidad">
                            <span class="cluster nowrap">
                                <span class="badge badge--gray" title="Partidos individuales"><abbr title="Individual">I</abbr> <?= e((string) $fila['individual']['partidos']) ?></span>
                                <span class="badge badge--purple" title="Partidos de dobles"><abbr title="Dobles">D</abbr> <?= e((string) $fila['dobles']['partidos']) ?></span>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($perPage !== 'all' && $totalPaginas > 1): ?>
            <?php
            $ventana = 2;
            $inicio = max(1, $paginaActual - $ventana);
            $fin = min($totalPaginas, $paginaActual + $ventana);
            $urlPag = static fn (int $n): string => $rankingUrl(['per_page' => (string) $perPage, 'page' => (string) $n]);
            ?>
            <nav class="pagination" aria-label="Paginación del ranking">
                <span>Página <strong class="num"><?= e((string) $paginaActual) ?></strong> de <strong class="num"><?= e((string) $totalPaginas) ?></strong></span>
                <div class="pagination__pages">
                    <?php if ($paginaActual > 1): ?>
                        <a class="pagination__page" href="<?= e($urlPag($paginaActual - 1)) ?>" aria-label="Página anterior" rel="prev"><?= icon('chevron-left') ?></a>
                    <?php else: ?>
                        <span class="pagination__page is-disabled" aria-hidden="true"><?= icon('chevron-left') ?></span>
                    <?php endif; ?>

                    <?php if ($inicio > 1): ?>
                        <a class="pagination__page" href="<?= e($urlPag(1)) ?>" aria-label="Página 1">1</a>
                        <?php if ($inicio > 2): ?><span class="pagination__page is-disabled" aria-hidden="true">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($n = $inicio; $n <= $fin; $n++): ?>
                        <?php if ($n === $paginaActual): ?>
                            <span class="pagination__page" aria-current="page"><?= e((string) $n) ?></span>
                        <?php else: ?>
                            <a class="pagination__page" href="<?= e($urlPag($n)) ?>" aria-label="Página <?= e((string) $n) ?>"><?= e((string) $n) ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($fin < $totalPaginas): ?>
                        <?php if ($fin < $totalPaginas - 1): ?><span class="pagination__page is-disabled" aria-hidden="true">…</span><?php endif; ?>
                        <a class="pagination__page" href="<?= e($urlPag($totalPaginas)) ?>" aria-label="Página <?= e((string) $totalPaginas) ?>"><?= e((string) $totalPaginas) ?></a>
                    <?php endif; ?>

                    <?php if ($paginaActual < $totalPaginas): ?>
                        <a class="pagination__page" href="<?= e($urlPag($paginaActual + 1)) ?>" aria-label="Página siguiente" rel="next"><?= icon('chevron-right') ?></a>
                    <?php else: ?>
                        <span class="pagination__page is-disabled" aria-hidden="true"><?= icon('chevron-right') ?></span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card card--muted ranking-formula" aria-labelledby="ranking-formula-title">
    <h2 id="ranking-formula-title" class="ranking-formula__title"><?= icon('info') ?> ¿Cómo se suma puntaje?</h2>
    <div class="rk-formula-grid">
        <div class="rk-formula-item rk-formula-item--gold"><strong>+30</strong><span>Campeón</span></div>
        <div class="rk-formula-item rk-formula-item--silver"><strong>+20</strong><span>2.º lugar</span></div>
        <div class="rk-formula-item rk-formula-item--bronze"><strong>+12</strong><span>3.º lugar</span></div>
        <div class="rk-formula-item"><strong>+5</strong><span>Torneo jugado</span></div>
        <div class="rk-formula-item"><strong>+5</strong><span>Vict. eliminatoria</span></div>
        <div class="rk-formula-item"><strong>+3</strong><span>Partido ganado</span></div>
        <div class="rk-formula-item"><strong>+1</strong><span>Partido jugado</span></div>
    </div>
    <p class="muted small mb-0">Desempates: campeonatos → 2.º lugares → 3.º lugares → efectividad → diferencia de puntos.</p>
</section>
