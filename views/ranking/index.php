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
$activeFilters = [];
if ($filters['modalidad'] !== '') {
    $activeFilters[] = 'Modalidad: ' . $filters['modalidad'];
}
if ($filters['year'] !== '') {
    $activeFilters[] = 'Ano: ' . $filters['year'];
}
if ($filters['area'] !== '') {
    $activeFilters[] = 'Area: ' . $filters['area'];
}
if ($filters['nivel'] !== '') {
    $activeFilters[] = 'Nivel: ' . $filters['nivel'];
}
?>
<section class="page-head page-head--hero ranking-hero">
    <div>
        <span class="eyebrow">Historical leaderboard</span>
        <h1>Ranking historico</h1>
        <p class="muted">Formula v1 transparente: participacion, victorias, partidos, podios y triunfos de bracket. Solo torneos FINALIZADOS.</p>
        <div class="meta-row">
            <span class="meta-pill">Jugadores rankeados: <?= e((string) count($ranking)) ?></span>
            <span class="meta-pill">BYE excluido</span>
            <span class="meta-pill">Dobles suma a ambos jugadores</span>
        </div>
    </div>
    <?php if ($top !== null): ?>
        <div class="ranking-hero__champion">
            <span class="hero-card__label">Numero 1 historico</span>
            <strong><?= e($top['nombre']) ?></strong>
            <span><?= e((string) $top['score']) ?> pts · <?= e((string) $top['campeonatos']) ?> titulos</span>
        </div>
    <?php endif; ?>
</section>

<section class="card ranking-filters">
    <div class="section-head">
        <div>
            <span class="eyebrow">Filtros</span>
            <h2>Recortar ranking</h2>
        </div>
        <a class="btn" href="/ranking">Limpiar filtros</a>
    </div>

    <form class="ranking-filter-form" method="get" action="/ranking">
        <label class="field">
            <span>Modalidad</span>
            <select name="modalidad">
                <option value="">Todas</option>
                <?php foreach ($opcionesFiltros['modalidades'] as $modalidad): ?>
                    <option value="<?= e($modalidad) ?>" <?= $filters['modalidad'] === $modalidad ? 'selected' : '' ?>><?= e($modalidad) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Ano</span>
            <select name="year">
                <option value="">Todos</option>
                <?php foreach ($opcionesFiltros['years'] as $year): ?>
                    <option value="<?= e((string) $year) ?>" <?= $filters['year'] === (string) $year ? 'selected' : '' ?>><?= e((string) $year) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Area</span>
            <select name="area">
                <option value="">Todas</option>
                <?php foreach ($opcionesFiltros['areas'] as $area): ?>
                    <option value="<?= e($area) ?>" <?= $filters['area'] === $area ? 'selected' : '' ?>><?= e($area) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Nivel</span>
            <select name="nivel">
                <option value="">Todos</option>
                <?php foreach ($opcionesFiltros['niveles'] as $nivel): ?>
                    <option value="<?= e($nivel) ?>" <?= $filters['nivel'] === $nivel ? 'selected' : '' ?>><?= e($nivel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="ranking-filter-form__actions">
            <button class="btn btn--primary" type="submit">Aplicar filtros</button>
        </div>
    </form>

    <div class="filter-summary">
        <?php if ($activeFilters === []): ?>
            <span class="muted">Sin filtros activos. Se muestra el historico completo.</span>
        <?php else: ?>
            <span class="muted">Filtros activos:</span>
            <?php foreach ($activeFilters as $filterLabel): ?>
                <span class="badge badge--blue"><?= e($filterLabel) ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="stats stats--compact">
    <article class="stat stat--compact">
        <span class="stat__label">Partidos registrados</span>
        <strong class="stat__num"><?= e((string) array_sum(array_column($ranking, 'partidos_jugados'))) ?></strong>
        <span class="stat__hint">Participaciones individuales normalizadas</span>
    </article>
    <article class="stat stat--compact">
        <span class="stat__label">Campeonatos</span>
        <strong class="stat__num"><?= e((string) array_sum(array_column($ranking, 'campeonatos'))) ?></strong>
        <span class="stat__hint">Derivados del bracket final</span>
    </article>
    <article class="stat stat--compact">
        <span class="stat__label">Mejor win rate</span>
        <strong class="stat__num"><?= $top !== null ? e($fmtPct((float) max(array_column($ranking, 'win_rate')))) : '0,0%' ?></strong>
        <span class="stat__hint">Entre jugadores con historial</span>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Scoreboard</span>
            <h2>Tabla general</h2>
        </div>
        <span class="badge badge--blue">v1 formula oficial</span>
    </div>

    <?php if ($ranking === []): ?>
        <p class="empty-state">No hay jugadores con historial para los filtros seleccionados.</p>
    <?php else: ?>
        <div class="ranking-toolbar">
            <span class="ranking-toolbar__count">
                Mostrando <?= e((string) $paginacion['desde']) ?>&ndash;<?= e((string) $paginacion['hasta']) ?> de <?= e((string) $paginacion['total']) ?> jugadores
            </span>
            <div class="per-page">
                <span class="per-page__label">Por pagina</span>
                <?php foreach ($paginacion['opciones'] as $opcion): ?>
                    <a class="per-page__opt <?= $perPage === $opcion ? 'is-active' : '' ?>" href="<?= e($rankingUrl(['per_page' => (string) $opcion])) ?>"><?= e((string) $opcion) ?></a>
                <?php endforeach; ?>
                <a class="per-page__opt <?= $perPage === 'all' ? 'is-active' : '' ?>" href="<?= e($rankingUrl(['per_page' => 'all'])) ?>">Todos</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table table--ranking">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Jugador</th>
                        <th>Score</th>
                        <th>Torneos</th>
                        <th>Partidos</th>
                        <th>G/P</th>
                        <th>Win rate</th>
                        <th>Podio</th>
                        <th>Bracket W</th>
                        <th>Dif. pts</th>
                        <th>Modalidad</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $fila): ?>
                    <tr class="rank-row rank-row--<?= (int) $fila['rank'] <= 3 ? e((string) $fila['rank']) : 'normal' ?>">
                        <td><span class="rank-badge">#<?= e((string) $fila['rank']) ?></span></td>
                        <td>
                            <a class="table__title" href="/personas/<?= e((string) $fila['persona_id']) ?>"><?= e($fila['nombre']) ?></a>
                            <span class="table__sub"><?= e($fila['area'] ?? 'Sin area') ?> · <?= e($fila['nivel'] ?? 'Sin nivel') ?></span>
                        </td>
                        <td><strong><?= e((string) $fila['score']) ?></strong></td>
                        <td><?= e((string) $fila['torneos_finalizados_jugados']) ?></td>
                        <td><?= e((string) $fila['partidos_jugados']) ?></td>
                        <td><?= e((string) $fila['partidos_ganados']) ?>/<?= e((string) $fila['partidos_perdidos']) ?></td>
                        <td><?= e($fmtPct((float) $fila['win_rate'])) ?></td>
                        <td><?= e((string) $fila['campeonatos']) ?> / <?= e((string) $fila['segundos_lugares']) ?> / <?= e((string) $fila['terceros_lugares']) ?></td>
                        <td><?= e((string) $fila['victorias_bracket']) ?></td>
                        <td><?= e($fmtDiff((int) $fila['diferencial_puntos'])) ?></td>
                        <td>
                            <span class="badge badge--gray">I <?= e((string) $fila['individual']['partidos']) ?></span>
                            <span class="badge badge--purple">D <?= e((string) $fila['dobles']['partidos']) ?></span>
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
            ?>
            <nav class="pagination" aria-label="Paginacion del ranking">
                <a class="pagination__btn <?= $paginaActual <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= e($rankingUrl(['per_page' => (string) $perPage, 'page' => $paginaActual > 1 ? (string) ($paginaActual - 1) : null])) ?>"
                   <?= $paginaActual <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>&laquo; Anterior</a>

                <?php if ($inicio > 1): ?>
                    <a class="pagination__page" href="<?= e($rankingUrl(['per_page' => (string) $perPage, 'page' => '1'])) ?>">1</a>
                    <?php if ($inicio > 2): ?><span class="pagination__gap">&hellip;</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($n = $inicio; $n <= $fin; $n++): ?>
                    <a class="pagination__page <?= $n === $paginaActual ? 'is-active' : '' ?>"
                       href="<?= e($rankingUrl(['per_page' => (string) $perPage, 'page' => (string) $n])) ?>"><?= e((string) $n) ?></a>
                <?php endfor; ?>

                <?php if ($fin < $totalPaginas): ?>
                    <?php if ($fin < $totalPaginas - 1): ?><span class="pagination__gap">&hellip;</span><?php endif; ?>
                    <a class="pagination__page" href="<?= e($rankingUrl(['per_page' => (string) $perPage, 'page' => (string) $totalPaginas])) ?>"><?= e((string) $totalPaginas) ?></a>
                <?php endif; ?>

                <a class="pagination__btn <?= $paginaActual >= $totalPaginas ? 'is-disabled' : '' ?>"
                   href="<?= e($rankingUrl(['per_page' => (string) $perPage, 'page' => $paginaActual < $totalPaginas ? (string) ($paginaActual + 1) : null])) ?>"
                   <?= $paginaActual >= $totalPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente &raquo;</a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card ranking-formula">
    <h2>Formula aplicada</h2>
    <p class="muted">score = 5 * torneos finalizados jugados + 3 * partidos ganados + partidos jugados + 30 * campeonatos + 20 * segundos lugares + 12 * terceros lugares + 5 * victorias en bracket.</p>
    <p class="muted small">Desempates: campeonatos, segundos lugares, terceros lugares, win rate y diferencial de puntos.</p>
</section>
