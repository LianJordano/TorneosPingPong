<?php
/** @var array<string,mixed> $torneo */
/** @var \PingPong\Domain\EstadoTorneo $estado */
/** @var list<array<string,mixed>> $tabla */
/** @var array<int,list<array<string,mixed>>> $porRonda */
/** @var int $totalRondas */
/** @var ?string $campeon */
/** @var list<array<string,mixed>> $proximos */
/** @var int $clasificados */
/** @var int $totalPendientes */

use PingPong\Domain\BracketEliminacion;

$resumen = static function (array $p): string {
    $sa = $sb = 0;
    foreach ($p['sets'] ?? [] as $s) {
        if ((int) $s['puntos_a'] > (int) $s['puntos_b']) { $sa++; } elseif ((int) $s['puntos_b'] > (int) $s['puntos_a']) { $sb++; }
    }
    return $sa . '-' . $sb;
};

$proximoMesa = $proximos[0] ?? null;
$proximosLuego = array_slice($proximos, 1);
$cantidadProximos = count($proximos);
$totalPendientes = $totalPendientes ?? $cantidadProximos;
$pendientesOcultos = max(0, $totalPendientes - $cantidadProximos);
$cantidadTabla = count($tabla);

$fasePartido = static function (array $p) use ($totalRondas): string {
    if (($p['fase'] ?? '') === 'ELIMINATORIA') {
        return $totalRondas > 0 ? BracketEliminacion::nombreRonda((int) ($p['ronda'] ?? 0), $totalRondas) : 'Eliminatoria';
    }
    return 'Jornada ' . ($p['ronda'] ?? '');
};

$hayProximos = $proximos !== [];
$hayTabla = $tabla !== [];
$split = $hayProximos && $hayTabla;
?>
<div class="public-board" id="public-board" data-live-region>
    <header class="public-title">
        <div>
            <span class="eyebrow"><?= icon('tv') ?> Tablero en vivo</span>
            <h1><?= e($torneo['nombre']) ?></h1>
        </div>
        <ul class="public-stats" aria-label="Resumen del torneo">
            <li class="public-stat">
                <span class="public-stat__num num"><?= e((string) $totalPendientes) ?></span>
                <span class="public-stat__label">Pendiente<?= $totalPendientes === 1 ? '' : 's' ?></span>
            </li>
            <?php if ($cantidadTabla > 0): ?>
                <li class="public-stat">
                    <span class="public-stat__num num"><?= e((string) $cantidadTabla) ?></span>
                    <span class="public-stat__label">Jugadores</span>
                </li>
            <?php endif; ?>
            <?php if ($totalRondas > 0): ?>
                <li class="public-stat">
                    <span class="public-stat__num num"><?= e((string) $totalRondas) ?></span>
                    <span class="public-stat__label">Ronda<?= $totalRondas === 1 ? '' : 's' ?></span>
                </li>
            <?php endif; ?>
        </ul>
    </header>

    <?php if ($campeon !== null): ?>
        <section class="winner-spotlight public-winner" aria-label="Campeón del torneo">
            <span class="public-winner__icon"><?= icon('trophy') ?></span>
            <div>
                <div class="winner-spotlight__label">Campeón del torneo</div>
                <div class="winner-spotlight__name"><?= e($campeon) ?></div>
                <p>Resultado final confirmado en el bracket.</p>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hayProximos || $hayTabla): ?>
        <div class="public-grid<?= $split ? ' public-grid--split' : '' ?>">
            <?php if ($hayProximos): ?>
                <section class="public-panel public-next" aria-labelledby="public-next-title">
                    <div class="public-panel__head">
                        <h2 id="public-next-title"><?= icon('play') ?> A continuación</h2>
                        <span class="badge badge--blue"><?= e((string) $totalPendientes) ?> pendiente<?= $totalPendientes === 1 ? '' : 's' ?></span>
                    </div>

                    <?php if ($proximoMesa !== null): ?>
                        <article class="next-match-card next-match-card--pending public-next__featured">
                            <div class="next-match-card__body">
                                <span class="next-match-card__kicker">Próximo en mesa</span>
                                <div class="public-duel public-duel--featured">
                                    <strong><?= e($proximoMesa['a_label'] ?? '—') ?></strong>
                                    <span class="public-duel__vs">vs</span>
                                    <strong><?= e($proximoMesa['b_label'] ?? '—') ?></strong>
                                </div>
                                <div class="next-match-card__meta">
                                    <span class="badge badge--gray"><?= icon('calendar') ?> <?= e($fasePartido($proximoMesa)) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <?php if ($proximosLuego !== []): ?>
                        <ol class="public-queue" aria-label="Partidos siguientes" start="2">
                            <?php foreach ($proximosLuego as $p): ?>
                                <li class="public-queue__item">
                                    <div class="public-duel public-duel--compact">
                                        <strong><?= e($p['a_label'] ?? '—') ?></strong>
                                        <span class="public-duel__vs">vs</span>
                                        <strong><?= e($p['b_label'] ?? '—') ?></strong>
                                    </div>
                                    <span class="public-queue__phase"><?= e($fasePartido($p)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php if ($pendientesOcultos > 0): ?>
                        <p class="public-note">
                            <?= icon('info') ?>
                            <span>+<?= e((string) $pendientesOcultos) ?> partido<?= $pendientesOcultos === 1 ? '' : 's' ?> pendiente<?= $pendientesOcultos === 1 ? '' : 's' ?> más. La cola se reordena al cargar resultados.</span>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($hayTabla): ?>
                <section class="public-panel public-standings-card" aria-labelledby="public-standings-title">
                    <div class="public-panel__head">
                        <h2 id="public-standings-title"><?= icon('table') ?> Tabla de posiciones</h2>
                        <?php if ($clasificados > 0): ?>
                            <span class="badge badge--green badge--dot">Top <?= e((string) $clasificados) ?> clasifica<?= $clasificados === 1 ? '' : 'n' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="table-wrap">
                        <table class="table table--stack public-standings">
                            <caption class="sr-only">Tabla de posiciones de <?= e($torneo['nombre']) ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="num">#</th>
                                    <th scope="col">Jugador</th>
                                    <th scope="col" class="num"><abbr title="Partidos jugados">PJ</abbr></th>
                                    <th scope="col" class="num"><abbr title="Partidos ganados">PG</abbr></th>
                                    <th scope="col" class="num"><abbr title="Partidos perdidos">PP</abbr></th>
                                    <th scope="col" class="num">Pts</th>
                                    <th scope="col" class="num">Sets</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tabla as $f): ?>
                                <?php
                                $pos = (int) $f['posicion'];
                                $clasifica = $clasificados > 0 && $pos <= $clasificados;
                                $medalla = match ($pos) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
                                ?>
                                <tr class="<?= $clasifica ? 'row--clasifica' : '' ?>">
                                    <td class="num" data-label="Posición">
                                        <span class="public-pos<?= $medalla !== '' ? ' public-pos--' . $medalla : '' ?>"><?= e((string) $pos) ?></span>
                                    </td>
                                    <td class="cell--primary" data-label="Jugador">
                                        <span class="public-player">
                                            <span><?= e($f['nombre']) ?></span>
                                            <?php if ($clasifica): ?>
                                                <span class="public-qualifies"><?= icon('check', 'icon--sm') ?> Clasifica</span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="num" data-label="PJ"><?= e((string) $f['pj']) ?></td>
                                    <td class="num" data-label="PG"><?= e((string) $f['pg']) ?></td>
                                    <td class="num" data-label="PP"><?= e((string) $f['pp']) ?></td>
                                    <td class="num public-standings__pts" data-label="Pts"><strong><?= e((string) $f['puntos']) ?></strong></td>
                                    <td class="num" data-label="Sets"><?= e((string) $f['sf']) ?>-<?= e((string) $f['sc']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalRondas > 0): ?>
        <section class="public-panel public-bracket-card" aria-labelledby="public-bracket-title">
            <div class="public-panel__head">
                <h2 id="public-bracket-title"><?= icon('bracket') ?> Bracket</h2>
                <span class="badge badge--gold"><?= e((string) $totalRondas) ?> ronda<?= $totalRondas === 1 ? '' : 's' ?></span>
            </div>
            <div class="bracket-shell public-bracket-shell" tabindex="0" role="region" aria-label="Bracket de eliminación directa (desplazable)">
                <div class="bracket">
                    <?php for ($ronda = 1; $ronda <= $totalRondas; $ronda++): ?>
                        <div class="bracket__round">
                            <h3 class="bracket__title"><?= e(BracketEliminacion::nombreRonda($ronda, $totalRondas)) ?></h3>
                            <?php foreach ($porRonda[$ronda] ?? [] as $p): ?>
                                <?php
                                $fin = $p['estado'] === 'FINALIZADO';
                                $bye = $fin && ($p['inscripcion_a_id'] === null || $p['inscripcion_b_id'] === null);
                                $ganaA = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_a_id'];
                                $ganaB = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_b_id'];
                                $esFinal = $ronda === $totalRondas;
                                ?>
                                <div class="bracket__match<?= $esFinal ? ' bracket__match--final' : '' ?><?= $esFinal && $fin ? ' bracket__match--crowned' : '' ?>">
                                    <?php if ($esFinal): ?><div class="bracket__final-tag"><?= icon('crown', 'icon--sm') ?> Final</div><?php endif; ?>
                                    <div class="bm-row<?= $ganaA ? ' bm-win' : '' ?>">
                                        <span><?= e($p['a_label'] ?? ($bye ? '—' : 'Por definir')) ?></span>
                                        <?php if ($ganaA): ?><?= icon('check', 'icon--sm', 'Ganador') ?><?php endif; ?>
                                    </div>
                                    <div class="bm-row<?= $ganaB ? ' bm-win' : '' ?>">
                                        <span><?= e($p['b_label'] ?? ($bye ? '—' : 'Por definir')) ?></span>
                                        <?php if ($ganaB): ?><?= icon('check', 'icon--sm', 'Ganador') ?><?php endif; ?>
                                    </div>
                                    <div class="bm-foot">
                                        <?php if ($bye): ?><span class="badge badge--gray">Pase libre</span>
                                        <?php elseif ($fin): ?><span class="badge badge--green"><?= icon('check', 'icon--sm') ?> <?= e($resumen($p)) ?></span>
                                        <?php else: ?><span class="badge badge--gray"><?= icon('clock', 'icon--sm') ?> Pendiente</span><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!$hayTabla && $totalRondas === 0): ?>
        <section class="empty-state public-empty">
            <span class="empty-state__icon"><?= icon('tv') ?></span>
            <h2 class="empty-state__title">Todavía no hay partidos para mostrar</h2>
            <p>Cuando arranque el torneo, acá vas a ver el próximo partido, la tabla de posiciones y el bracket. Podés dejar esta pantalla proyectada: se actualiza sola cada minuto.</p>
        </section>
    <?php endif; ?>
</div>
