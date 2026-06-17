<?php
/** @var array<string,mixed> $perfil */
$persona = $perfil['persona'];
$stats = $perfil['stats'];
$ranking = $perfil['ranking'];
$torneos = $perfil['torneos'];
$partidos = $perfil['partidosRecientes'];
$companeros = $perfil['companeros'];
$fmtPct = static fn (float $v): string => number_format($v, 1, ',', '.') . '%';
$fmtDiff = static fn (int $v): string => ($v > 0 ? '+' : '') . (string) $v;
$bestFinish = $stats['mejor_finish'] === null ? 'Sin podio' : '#' . (string) $stats['mejor_finish'];
?>
<section class="page-head page-head--hero profile-hero">
    <div>
        <span class="eyebrow">Player profile</span>
        <h1><?= e($persona['nombre_completo']) ?></h1>
        <p class="muted">Perfil historico por persona.id #<?= e((string) $persona['id']) ?>, con estadisticas acumuladas solo de torneos FINALIZADOS.</p>
        <div class="meta-row">
            <span class="meta-pill"><?= e($persona['area'] ?? 'Sin area') ?></span>
            <span class="meta-pill"><?= e($persona['nivel'] ?? 'Sin nivel') ?></span>
            <span class="meta-pill"><?= (int) $persona['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span>
            <?php if (!empty($persona['identificador_interno'])): ?>
                <span class="meta-pill">ID <?= e($persona['identificador_interno']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="profile-rank-card">
        <span class="hero-card__label">Ranking historico</span>
        <strong><?= $ranking !== null ? '#' . e((string) $ranking['rank']) : 'Sin ranking' ?></strong>
        <span><?= e((string) $stats['score']) ?> puntos · <?= e((string) $stats['podios']) ?> podios</span>
    </div>
</section>

<section class="stats stats--compact">
    <article class="stat stat--compact"><span class="stat__label">Torneos</span><strong class="stat__num"><?= e((string) $stats['torneos_finalizados_jugados']) ?></strong><span class="stat__hint">Finalizados jugados</span></article>
    <article class="stat stat--compact"><span class="stat__label">Partidos</span><strong class="stat__num"><?= e((string) $stats['partidos_jugados']) ?></strong><span class="stat__hint"><?= e((string) $stats['partidos_ganados']) ?>G / <?= e((string) $stats['partidos_perdidos']) ?>P</span></article>
    <article class="stat stat--compact"><span class="stat__label">Win rate</span><strong class="stat__num"><?= e($fmtPct((float) $stats['win_rate'])) ?></strong><span class="stat__hint">Racha actual <?= e((string) $stats['racha_actual']) ?> · mejor <?= e((string) $stats['mejor_racha']) ?></span></article>
    <article class="stat stat--compact"><span class="stat__label">Puntos</span><strong class="stat__num"><?= e($fmtDiff((int) $stats['diferencial_puntos'])) ?></strong><span class="stat__hint"><?= e((string) $stats['puntos_favor']) ?> a favor / <?= e((string) $stats['puntos_contra']) ?> contra</span></article>
</section>

<section class="profile-grid">
    <article class="card">
        <div class="section-head">
            <div><span class="eyebrow">Performance</span><h2>Resumen competitivo</h2></div>
        </div>
        <div class="metric-list">
            <div><span>Campeonatos</span><strong><?= e((string) $stats['campeonatos']) ?></strong></div>
            <div><span>Segundos lugares</span><strong><?= e((string) $stats['segundos_lugares']) ?></strong></div>
            <div><span>Terceros lugares</span><strong><?= e((string) $stats['terceros_lugares']) ?></strong></div>
            <div><span>Mejor finish</span><strong><?= e($bestFinish) ?></strong></div>
            <div><span>Victorias liga</span><strong><?= e((string) $stats['victorias_liga']) ?></strong></div>
            <div><span>Victorias bracket</span><strong><?= e((string) $stats['victorias_bracket']) ?></strong></div>
            <div><span>Sets</span><strong><?= e((string) $stats['sets_ganados']) ?>-<?= e((string) $stats['sets_perdidos']) ?></strong></div>
            <div><span>Diferencial sets</span><strong><?= e($fmtDiff((int) $stats['diferencial_sets'])) ?></strong></div>
        </div>
    </article>

    <article class="card">
        <div class="section-head">
            <div><span class="eyebrow">Splits</span><h2>Individual vs dobles</h2></div>
        </div>
        <div class="split-cards">
            <div class="split-card">
                <span>Individual</span>
                <strong><?= e((string) $stats['individual']['partidos']) ?> PJ</strong>
                <small><?= e((string) $stats['individual']['ganados']) ?> victorias</small>
            </div>
            <div class="split-card split-card--purple">
                <span>Dobles</span>
                <strong><?= e((string) $stats['dobles']['partidos']) ?> PJ</strong>
                <small><?= e((string) $stats['dobles']['ganados']) ?> victorias</small>
            </div>
        </div>
        <?php if ($companeros !== []): ?>
            <h3 class="mini-title">Companeros frecuentes</h3>
            <div class="partner-list">
                <?php foreach ($companeros as $c): ?>
                    <a href="/personas/<?= e((string) $c['persona_id']) ?>"><strong><?= e($c['nombre']) ?></strong><span><?= e((string) $c['torneos']) ?> torneo<?= (int) $c['torneos'] === 1 ? '' : 's' ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">Sin historial de parejas en dobles.</p>
        <?php endif; ?>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <div><span class="eyebrow">Tournament history</span><h2>Historial de torneos</h2></div>
        <span class="badge badge--gray"><?= e((string) count($torneos)) ?> registros</span>
    </div>
    <?php if ($torneos === []): ?>
        <p class="empty-state">Este jugador todavia no registra torneos finalizados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Torneo</th><th>Fecha</th><th>Modalidad</th><th>Equipo</th><th>Finish</th></tr></thead>
                <tbody>
                <?php foreach ($torneos as $t): ?>
                    <tr>
                        <td><a href="/torneos/<?= e((string) $t['torneo_id']) ?>"><?= e($t['nombre']) ?></a></td>
                        <td><?= e($t['fecha']) ?></td>
                        <td><span class="badge <?= $t['modalidad'] === 'DOBLES' ? 'badge--purple' : 'badge--blue' ?>"><?= e($t['modalidad']) ?></span></td>
                        <td><?= e($t['equipo']) ?></td>
                        <td><?= $t['posicion'] === null ? '—' : '#' . e((string) $t['posicion']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="section-head">
        <div><span class="eyebrow">Recent form</span><h2>Partidos recientes</h2></div>
    </div>
    <?php if ($partidos === []): ?>
        <p class="empty-state">Sin partidos reales finalizados. Los BYE no cuentan como partido jugado.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--matches">
                <thead><tr><th>Torneo</th><th>Fase</th><th>Cruce</th><th>Sets</th><th>Puntos</th><th>Ganador</th></tr></thead>
                <tbody>
                <?php foreach ($partidos as $m): ?>
                    <tr>
                        <td><a href="/torneos/<?= e((string) $m['torneo_id']) ?>"><?= e($m['torneo_nombre']) ?></a><span class="table__sub"><?= e($m['fecha']) ?></span></td>
                        <td><span class="badge badge--gray"><?= e($m['fase']) ?></span></td>
                        <td><?= e($m['a_label']) ?> <span class="muted">vs</span> <?= e($m['b_label']) ?></td>
                        <td><strong><?= e($m['score']) ?></strong></td>
                        <td><?= e($m['puntos']) ?></td>
                        <td><?= e($m['ganador_label']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
