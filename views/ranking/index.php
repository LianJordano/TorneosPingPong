<?php
/** @var list<array<string,mixed>> $ranking */
/** @var list<array<string,mixed>> $rows */
/** @var array{per_page:int|string,page:int,total:int,total_paginas:int,opciones:list<int>,desde:int,hasta:int} $paginacion */
/** @var array{modalidad:string,year:string,area:string,nivel:string} $filters */
/** @var array{modalidades:list<string>,years:list<int>,areas:list<string>,niveles:list<string>} $opcionesFiltros */
$fmtPct = static fn (float $v): string => number_format($v, 1, ',', '.') . '%';
$fmtDiff = static fn (int $v): string => ($v > 0 ? '+' : '') . (string) $v;
$top = $ranking[0] ?? null;

$baseQuery = array_filter([
    'modalidad' => $filters['modalidad'],
    'year' => $filters['year'],
    'area' => $filters['area'],
    'nivel' => $filters['nivel'],
], static fn (string $v): bool => $v !== '');

$rankingUrl = static function (array $extra) use ($baseQuery): string {
    $q = array_merge($baseQuery, array_filter($extra, static fn ($v): bool => $v !== null));
    return '/ranking' . ($q === [] ? '' : '?' . http_build_query($q));
};

$perPage = $paginacion['per_page'];
$paginaActual = $paginacion['page'];
$totalPaginas = $paginacion['total_paginas'];
$modalidadLabel = static fn (string $m): string => match ($m) {
    'INDIVIDUAL' => 'Individual',
    'DOBLES' => 'Dobles',
    default => $m,
};
$activeFilters = [];
if ($filters['modalidad'] !== '') {
    $activeFilters[] = 'Modalidad: ' . $modalidadLabel($filters['modalidad']);
}
if ($filters['year'] !== '') {
    $activeFilters[] = 'Año: ' . $filters['year'];
}
if ($filters['area'] !== '') {
    $activeFilters[] = 'Área: ' . $filters['area'];
}
if ($filters['nivel'] !== '') {
    $activeFilters[] = 'Nivel: ' . $filters['nivel'];
}
$medallas = [1 => ['gold', 'Oro'], 2 => ['silver', 'Plata'], 3 => ['bronze', 'Bronce']];
?>
<section class="hero ranking-hero" aria-labelledby="ranking-title">
    <div class="hero-grid">
        <div>
            <span class="eyebrow"><?= icon('ranking') ?> Competencia</span>
            <h1 id="ranking-title">Ranking histórico</h1>
            <p>Suma participación, victorias, partidos, podios y triunfos en eliminatoria. Solo cuentan torneos finalizados.</p>
            <div class="meta-row">
                <span class="meta-pill"><?= icon('users') ?> <?= e((string) count($ranking)) ?> jugador<?= count($ranking) === 1 ? '' : 'es' ?> en el ranking</span>
                <span class="meta-pill"><?= icon('info') ?> Los BYE no cuentan</span>
                <span class="meta-pill"><?= icon('users') ?> En dobles suman ambos jugadores</span>
            </div>
        </div>
        <?php if ($top !== null): ?>
            <div class="hero-side">
                <a class="hero-card ranking-champion" href="/personas/<?= e((string) $top['persona_id']) ?>">
                    <span class="hero-card__label"><?= icon('crown') ?> Número 1 histórico</span>
                    <strong><?= e($top['nombre']) ?></strong>
                    <small><span class="num"><?= e((string) $top['score']) ?></span> puntos · <span class="num"><?= e((string) $top['campeonatos']) ?></span> título<?= (int) $top['campeonatos'] === 1 ? '' : 's' ?></small>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card ranking-filters" aria-labelledby="ranking-filters-title">
    <h2 id="ranking-filters-title" class="sr-only">Filtros del ranking</h2>
    <form class="toolbar ranking-toolbar-form" method="get" action="/ranking">
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
            <button class="btn btn--primary" type="submit"><?= icon('filter') ?> Aplicar</button>
            <?php if ($activeFilters !== []): ?>
                <a class="btn btn--ghost" href="/ranking"><?= icon('x') ?> Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="filter-summary cluster" aria-live="polite">
        <?php if ($activeFilters === []): ?>
            <span class="muted small">Sin filtros: se muestra el histórico completo.</span>
        <?php else: ?>
            <span class="muted small">Filtros activos:</span>
            <?php foreach ($activeFilters as $filterLabel): ?>
                <span class="badge badge--blue"><?= icon('filter') ?> <?= e($filterLabel) ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="stats stats--compact" aria-label="Totales del ranking">
    <article class="stat stat--compact">
        <span class="stat__label"><?= icon('paddle') ?> Partidos registrados</span>
        <strong class="stat__num num"><?= e((string) array_sum(array_column($ranking, 'partidos_jugados'))) ?></strong>
        <span class="stat__hint">Participaciones individuales normalizadas</span>
    </article>
    <article class="stat stat--compact">
        <span class="stat__label"><?= icon('trophy') ?> Campeonatos</span>
        <strong class="stat__num num"><?= e((string) array_sum(array_column($ranking, 'campeonatos'))) ?></strong>
        <span class="stat__hint">Derivados de la final de cada eliminatoria</span>
    </article>
    <article class="stat stat--compact">
        <span class="stat__label"><?= icon('target') ?> Mejor efectividad</span>
        <strong class="stat__num num"><?= $top !== null ? e($fmtPct((float) max(array_column($ranking, 'win_rate')))) : '0,0%' ?></strong>
        <span class="stat__hint">Entre jugadores con historial</span>
    </article>
</section>

<section class="card" aria-labelledby="ranking-table-title">
    <div class="section-head">
        <div>
            <span class="eyebrow">Clasificación</span>
            <h2 id="ranking-table-title">Tabla general</h2>
        </div>
        <span class="badge badge--blue">Fórmula oficial v1</span>
    </div>

    <?php if ($ranking === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('ranking') ?></span>
            <p class="empty-state__title">Sin jugadores en el ranking</p>
            <p>No hay jugadores con historial para los filtros seleccionados.</p>
            <?php if ($activeFilters !== []): ?>
                <div class="actions"><a class="btn btn--secondary" href="/ranking">Quitar filtros</a></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="spread ranking-listbar">
            <p class="muted small mb-0">
                Mostrando <strong class="num"><?= e((string) $paginacion['desde']) ?>–<?= e((string) $paginacion['hasta']) ?></strong>
                de <strong class="num"><?= e((string) $paginacion['total']) ?></strong> jugadores
            </p>
            <nav class="per-page" aria-label="Jugadores por página">
                <span class="per-page__label">Por página</span>
                <?php foreach ($paginacion['opciones'] as $opcion): ?>
                    <a class="per-page__opt" href="<?= e($rankingUrl(['per_page' => (string) $opcion])) ?>"<?= $perPage === $opcion ? ' aria-current="true"' : '' ?>><?= e((string) $opcion) ?></a>
                <?php endforeach; ?>
                <a class="per-page__opt" href="<?= e($rankingUrl(['per_page' => 'all'])) ?>"<?= $perPage === 'all' ? ' aria-current="true"' : '' ?>>Todos</a>
            </nav>
        </div>

        <div class="table-wrap">
            <table class="table table--stack ranking-table">
                <thead>
                    <tr>
                        <th scope="col">Pos.</th>
                        <th scope="col">Jugador</th>
                        <th scope="col" class="text-right">Puntaje</th>
                        <th scope="col" class="text-right">Torneos</th>
                        <th scope="col" class="text-right">Partidos</th>
                        <th scope="col" class="text-right"><abbr title="Ganados / perdidos">G/P</abbr></th>
                        <th scope="col" class="text-right">Efectividad</th>
                        <th scope="col" class="text-right"><abbr title="Campeonatos / segundos / terceros">Podios</abbr></th>
                        <th scope="col" class="text-right"><abbr title="Victorias en eliminatoria">Vict. elim.</abbr></th>
                        <th scope="col" class="text-right"><abbr title="Diferencia de puntos">Dif. pts</abbr></th>
                        <th scope="col">Modalidad</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $fila): ?>
                    <?php
                    $rank = (int) $fila['rank'];
                    $medalla = $medallas[$rank] ?? null;
                    ?>
                    <tr class="rank-row<?= $medalla !== null ? ' rank-row--' . $medalla[0] : '' ?>">
                        <td data-label="Posición" class="num">
                            <?php if ($medalla !== null): ?>
                                <span class="medal-badge medal-badge--<?= $medalla[0] ?>"><?= icon('medal') ?> #<?= e((string) $rank) ?><span class="sr-only"> (<?= $medalla[1] ?>)</span></span>
                            <?php else: ?>
                                <span class="rank-num">#<?= e((string) $rank) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="cell--primary">
                            <a class="player-cell" href="/personas/<?= e((string) $fila['persona_id']) ?>">
                                <span class="avatar" aria-hidden="true"><?= e(initials($fila['nombre'])) ?></span>
                                <span>
                                    <span class="player-cell__name"><?= e($fila['nombre']) ?></span>
                                    <span class="table__sub"><?= e($fila['area'] ?? 'Sin área') ?> · <?= e($fila['nivel'] ?? 'Sin nivel') ?></span>
                                </span>
                            </a>
                        </td>
                        <td data-label="Puntaje" class="num"><strong><?= e((string) $fila['score']) ?></strong></td>
                        <td data-label="Torneos" class="num"><?= e((string) $fila['torneos_finalizados_jugados']) ?></td>
                        <td data-label="Partidos" class="num"><?= e((string) $fila['partidos_jugados']) ?></td>
                        <td data-label="Ganados / perdidos" class="num"><?= e((string) $fila['partidos_ganados']) ?>/<?= e((string) $fila['partidos_perdidos']) ?></td>
                        <td data-label="Efectividad" class="num"><?= e($fmtPct((float) $fila['win_rate'])) ?></td>
                        <td data-label="Podios (1.º / 2.º / 3.º)" class="num"><?= e((string) $fila['campeonatos']) ?> / <?= e((string) $fila['segundos_lugares']) ?> / <?= e((string) $fila['terceros_lugares']) ?></td>
                        <td data-label="Victorias en eliminatoria" class="num"><?= e((string) $fila['victorias_bracket']) ?></td>
                        <td data-label="Diferencia de puntos" class="num"><?= e($fmtDiff((int) $fila['diferencial_puntos'])) ?></td>
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
    <h2 id="ranking-formula-title" class="ranking-formula__title"><?= icon('info') ?> Cómo se calcula</h2>
    <p class="small">Puntaje = 5 × torneos finalizados jugados + 3 × partidos ganados + partidos jugados + 30 × campeonatos + 20 × segundos lugares + 12 × terceros lugares + 5 × victorias en eliminatoria.</p>
    <p class="muted small mb-0">Desempates: campeonatos, segundos lugares, terceros lugares, efectividad y diferencia de puntos.</p>
</section>
