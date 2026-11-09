<?php
/** @var array<string,mixed> $perfil */
$persona = $perfil['persona'];
$stats = $perfil['stats'];
$ranking = $perfil['ranking'];
$torneos = $perfil['torneos'];
$partidos = $perfil['partidosRecientes'];
$companeros = $perfil['companeros'];
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$fmtPct = static fn (float $v): string => number_format($v, 1, ',', '.') . '%';
$fmtDiff = static fn (int $v): string => ($v > 0 ? '+' : '') . (string) $v;
$bestFinish = $stats['mejor_finish'] === null ? 'Sin podio' : '#' . (string) $stats['mejor_finish'];
$activo = (int) $persona['activo'] === 1;
$medalla = static fn (?int $pos): string => match ($pos) {
    1 => 'gold',
    2 => 'silver',
    3 => 'bronze',
    default => '',
};
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/personas">Jugadores</a></li>
        <li><span aria-current="page"><?= e($persona['nombre_completo']) ?></span></li>
    </ol>
</nav>

<section class="hero profile-hero" aria-labelledby="profile-name">
    <div class="hero-grid">
        <div class="profile-hero__main">
            <span class="avatar avatar--lg profile-hero__avatar" aria-hidden="true"><?= e(initials($persona['nombre_completo'])) ?></span>
            <div class="profile-hero__text">
                <span class="eyebrow"><?= icon('user') ?> Perfil de jugador</span>
                <h1 id="profile-name"><?= e($persona['nombre_completo']) ?></h1>
                <p>Estadísticas acumuladas de torneos finalizados.</p>
                <div class="meta-row">
                    <span class="meta-pill"><?= icon('layers') ?> <?= e($persona['area'] ?? 'Sin área') ?></span>
                    <span class="meta-pill"><?= icon('tag') ?> <?= e($persona['nivel'] ?? 'Sin nivel') ?></span>
                    <span class="meta-pill"><?= icon($activo ? 'check-circle' : 'x-circle') ?> <?= $activo ? 'Activo' : 'Inactivo' ?></span>
                    <?php if (!empty($persona['identificador_interno'])): ?>
                        <span class="meta-pill"><?= icon('key') ?> ID <?= e($persona['identificador_interno']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($puedeGestionar): ?>
                    <div class="actions mt-4">
                        <a class="btn" href="/personas/<?= e((string) $persona['id']) ?>/editar"><?= icon('edit') ?> Editar datos</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-card profile-rank-card">
                <span class="hero-card__label">Ranking histórico</span>
                <strong class="num"><?= $ranking !== null ? '#' . e((string) $ranking['rank']) : 'Sin ranking' ?></strong>
                <small><?= e((string) $stats['score']) ?> puntos · <?= e((string) $stats['podios']) ?> podio<?= (int) $stats['podios'] === 1 ? '' : 's' ?></small>
                <a class="profile-rank-card__link" href="/ranking">Ver ranking completo <?= icon('arrow-right') ?></a>
            </div>
        </div>
    </div>
</section>

<section class="stats stats--compact" aria-label="Resumen de estadísticas">
    <article class="stat stat--compact">
        <span class="stat__label"><?= icon('trophy') ?> Torneos</span>
        <strong class="stat__num num"><?= e((string) $stats['torneos_finalizados_jugados']) ?></strong>
        <span class="stat__hint">Finalizados jugados</span>
    </article>
    <article class="stat stat--compact">
        <span class="stat__label"><?= icon('paddle') ?> Partidos</span>
        <strong class="stat__num num"><?= e((string) $stats['partidos_jugados']) ?></strong>
        <span class="stat__hint"><?= e((string) $stats['partidos_ganados']) ?> ganados · <?= e((string) $stats['partidos_perdidos']) ?> perdidos</span>
    </article>
    <article class="stat stat--compact">
        <span class="stat__label"><?= icon('target') ?> Efectividad</span>
        <strong class="stat__num num"><?= e($fmtPct((float) $stats['win_rate'])) ?></strong>
        <span class="stat__hint">Racha actual <?= e((string) $stats['racha_actual']) ?> · mejor <?= e((string) $stats['mejor_racha']) ?></span>
    </article>
    <article class="stat stat--compact">
        <span class="stat__label"><?= icon('activity') ?> Diferencia de puntos</span>
        <strong class="stat__num num"><?= e($fmtDiff((int) $stats['diferencial_puntos'])) ?></strong>
        <span class="stat__hint"><?= e((string) $stats['puntos_favor']) ?> a favor · <?= e((string) $stats['puntos_contra']) ?> en contra</span>
    </article>
</section>

<div class="grid-2 profile-grid">
    <section class="card">
        <div class="section-head">
            <div>
                <span class="eyebrow">Rendimiento</span>
                <h2>Resumen competitivo</h2>
            </div>
        </div>
        <dl class="datalist profile-metrics">
            <dt><?= icon('medal', 'medal-icon medal-icon--gold') ?> Campeonatos</dt>
            <dd class="num"><?= e((string) $stats['campeonatos']) ?></dd>
            <dt><?= icon('medal', 'medal-icon medal-icon--silver') ?> Segundos lugares</dt>
            <dd class="num"><?= e((string) $stats['segundos_lugares']) ?></dd>
            <dt><?= icon('medal', 'medal-icon medal-icon--bronze') ?> Terceros lugares</dt>
            <dd class="num"><?= e((string) $stats['terceros_lugares']) ?></dd>
            <dt>Mejor posición</dt>
            <dd class="num"><?= e($bestFinish) ?></dd>
            <dt>Victorias en liga</dt>
            <dd class="num"><?= e((string) $stats['victorias_liga']) ?></dd>
            <dt>Victorias en eliminatoria</dt>
            <dd class="num"><?= e((string) $stats['victorias_bracket']) ?></dd>
            <dt>Sets (ganados–perdidos)</dt>
            <dd class="num"><?= e((string) $stats['sets_ganados']) ?>–<?= e((string) $stats['sets_perdidos']) ?></dd>
            <dt>Diferencia de sets</dt>
            <dd class="num"><?= e($fmtDiff((int) $stats['diferencial_sets'])) ?></dd>
        </dl>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <span class="eyebrow">Modalidades</span>
                <h2>Individual y dobles</h2>
            </div>
        </div>
        <div class="split-cards">
            <div class="split-card">
                <span class="split-card__label"><?= icon('user') ?> Individual</span>
                <strong class="num"><?= e((string) $stats['individual']['partidos']) ?> <abbr title="partidos jugados">PJ</abbr></strong>
                <small><?= e((string) $stats['individual']['ganados']) ?> victoria<?= (int) $stats['individual']['ganados'] === 1 ? '' : 's' ?></small>
            </div>
            <div class="split-card split-card--doubles">
                <span class="split-card__label"><?= icon('users') ?> Dobles</span>
                <strong class="num"><?= e((string) $stats['dobles']['partidos']) ?> <abbr title="partidos jugados">PJ</abbr></strong>
                <small><?= e((string) $stats['dobles']['ganados']) ?> victoria<?= (int) $stats['dobles']['ganados'] === 1 ? '' : 's' ?></small>
            </div>
        </div>

        <h3 class="profile-subtitle">Compañeros frecuentes</h3>
        <?php if ($companeros !== []): ?>
            <ul class="list">
                <?php foreach ($companeros as $c): ?>
                    <li class="list__item">
                        <span class="avatar" aria-hidden="true"><?= e(initials($c['nombre'])) ?></span>
                        <div class="list__body">
                            <a class="list__title link" href="/personas/<?= e((string) $c['persona_id']) ?>"><?= e($c['nombre']) ?></a>
                            <span class="list__meta"><?= e((string) $c['torneos']) ?> torneo<?= (int) $c['torneos'] === 1 ? '' : 's' ?> juntos</span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted small">Sin historial de parejas en dobles.</p>
        <?php endif; ?>
    </section>
</div>

<section class="card" aria-labelledby="profile-torneos">
    <div class="section-head">
        <div>
            <span class="eyebrow">Historial</span>
            <h2 id="profile-torneos">Torneos disputados</h2>
        </div>
        <span class="badge badge--gray"><?= e((string) count($torneos)) ?> torneo<?= count($torneos) === 1 ? '' : 's' ?></span>
    </div>
    <?php if ($torneos === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('trophy') ?></span>
            <p class="empty-state__title">Sin torneos finalizados</p>
            <p>Este jugador todavía no registra torneos finalizados.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr>
                        <th scope="col">Torneo</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Modalidad</th>
                        <th scope="col">Equipo</th>
                        <th scope="col" class="text-right">Posición</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($torneos as $t): ?>
                    <?php $pos = $t['posicion'] === null ? null : (int) $t['posicion']; ?>
                    <tr>
                        <td class="cell--primary"><a href="/torneos/<?= e((string) $t['torneo_id']) ?>"><?= e($t['nombre']) ?></a></td>
                        <td data-label="Fecha" class="nowrap"><?= e($t['fecha']) ?></td>
                        <td data-label="Modalidad"><span class="badge <?= $t['modalidad'] === 'DOBLES' ? 'badge--purple' : 'badge--blue' ?>"><?= icon($t['modalidad'] === 'DOBLES' ? 'users' : 'user') ?> <?= $t['modalidad'] === 'DOBLES' ? 'Dobles' : 'Individual' ?></span></td>
                        <td data-label="Equipo"><?= e($t['equipo']) ?></td>
                        <td data-label="Posición" class="num">
                            <?php if ($pos === null): ?>
                                <span class="muted" aria-hidden="true">—</span><span class="sr-only">Sin posición final</span>
                            <?php elseif ($medalla($pos) !== ''): ?>
                                <span class="medal-badge medal-badge--<?= $medalla($pos) ?>"><?= icon('medal') ?> #<?= e((string) $pos) ?></span>
                            <?php else: ?>
                                #<?= e((string) $pos) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card" aria-labelledby="profile-partidos">
    <div class="section-head">
        <div>
            <span class="eyebrow">Forma reciente</span>
            <h2 id="profile-partidos">Partidos recientes</h2>
            <p>Los BYE no cuentan como partido jugado.</p>
        </div>
    </div>
    <?php if ($partidos === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('paddle') ?></span>
            <p class="empty-state__title">Sin partidos finalizados</p>
            <p>Cuando juegue partidos en torneos finalizados aparecerán aquí.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr>
                        <th scope="col">Torneo</th>
                        <th scope="col">Fase</th>
                        <th scope="col">Cruce</th>
                        <th scope="col" class="text-right">Sets</th>
                        <th scope="col" class="text-right">Puntos</th>
                        <th scope="col">Ganador</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($partidos as $m): ?>
                    <tr>
                        <td class="cell--primary">
                            <a href="/torneos/<?= e((string) $m['torneo_id']) ?>"><?= e($m['torneo_nombre']) ?></a>
                            <span class="table__sub"><?= e($m['fecha']) ?></span>
                        </td>
                        <td data-label="Fase"><span class="badge badge--gray"><?= e($m['fase']) ?></span></td>
                        <td data-label="Cruce"><?= e($m['a_label']) ?> <span class="muted">vs</span> <?= e($m['b_label']) ?></td>
                        <td data-label="Sets" class="num"><strong><?= e($m['score']) ?></strong></td>
                        <td data-label="Puntos" class="num"><?= e($m['puntos']) ?></td>
                        <td data-label="Ganador"><?= icon('trophy', 'winner-icon') ?> <?= e($m['ganador_label']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
