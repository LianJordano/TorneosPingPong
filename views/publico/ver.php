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
$rondasBracket = $totalRondas > 0 ? $totalRondas : 0;

$fasePartido = static function (array $p): string {
    return ($p['fase'] ?? '') === 'ELIMINATORIA' ? 'Bracket' : 'Jornada ' . ($p['ronda'] ?? '');
};
?>
<section class="page-head page-head--hero page-head--public public-board-hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Pantalla pública · mesa única · en vivo</span>
            <h1><?= e($torneo['nombre']) ?> <span class="badge <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></h1>
            <p class="muted">Marcador de proyección para seguir el torneo sin entrar al panel. Se actualiza automáticamente cada 60 segundos.</p>
            <div class="meta-row">
                <span class="meta-pill">Estado: <strong><?= e($estado->label()) ?></strong></span>
                <span class="meta-pill">Pendientes: <strong><?= e((string) $totalPendientes) ?></strong></span>
                <span class="meta-pill">Tabla: <strong><?= e((string) $cantidadTabla) ?></strong></span>
                <span class="meta-pill">Rondas bracket: <strong><?= e((string) $rondasBracket) ?></strong></span>
            </div>
        </div>
        <div class="hero-side public-board-hero__side">
            <div class="hero-card bracket-hero-card">
                <span class="hero-card__label">Vivo</span>
                <strong>60 s</strong>
                <p>Auto-refresh activo para pantalla grande o TV.</p>
            </div>
            <div class="hero-card">
                <span class="hero-card__label">Modo lectura</span>
                <strong>Mesa única</strong>
                <p>Sin login, sin edición, sólo estado del torneo.</p>
            </div>
        </div>
    </div>
</section>

<?php if ($campeon !== null): ?>
    <section class="winner-spotlight public-winner" aria-label="Campeón del torneo">
        <div class="winner-spotlight__label">Consagración oficial</div>
        <div class="winner-spotlight__name">🏆 <?= e($campeon) ?></div>
        <p>Campeón del torneo. Resultado final confirmado en el bracket.</p>
    </section>
<?php endif; ?>

<?php if ($proximos !== []): ?>
    <section class="public-section public-next">
        <div class="section-head">
            <div>
                <span class="eyebrow">Ahora / cola sugerida</span>
                <h2>Orden visible de mesa</h2>
            </div>
            <span class="badge badge--blue"><?= e((string) $totalPendientes) ?> pendientes</span>
        </div>

        <?php if ($proximoMesa !== null): ?>
            <article class="next-match-card next-match-card--pending public-next__featured">
                <div class="next-match-card__body">
                    <span class="next-match-card__kicker">Próximo sugerido en mesa</span>
                    <div class="public-duel public-duel--featured" aria-label="Próximo enfrentamiento">
                        <strong><?= e($proximoMesa['a_label'] ?? '—') ?></strong>
                        <span class="public-duel__vs">VS</span>
                        <strong><?= e($proximoMesa['b_label'] ?? '—') ?></strong>
                    </div>
                    <div class="next-match-card__meta">
                        <span class="badge"><?= e($fasePartido($proximoMesa)) ?></span>
                        <span class="badge">Mesa única</span>
                    </div>
                </div>
                <div class="next-match-card__action">En vivo</div>
            </article>
        <?php endif; ?>

        <?php if ($proximosLuego !== []): ?>
            <div class="public-next-list" aria-label="Partidos siguientes">
                <?php foreach ($proximosLuego as $p): ?>
                    <article class="public-next-item">
                        <div class="public-next-item__main">
                            <span class="public-next-item__label"><?= e($fasePartido($p)) ?></span>
                            <div class="public-duel public-duel--compact" aria-label="Enfrentamiento siguiente">
                                <strong><?= e($p['a_label'] ?? '—') ?></strong>
                                <span class="public-duel__vs">VS</span>
                                <strong><?= e($p['b_label'] ?? '—') ?></strong>
                            </div>
                        </div>
                        <span class="badge badge--gray">siguiente</span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pendientesOcultos > 0): ?>
            <p class="public-next-more">
                +<?= e((string) $pendientesOcultos) ?> partido<?= $pendientesOcultos === 1 ? '' : 's' ?> pendiente<?= $pendientesOcultos === 1 ? '' : 's' ?> más. La cola se actualiza cuando se cargan resultados, aunque se completen fuera de orden.
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($totalRondas > 0): ?>
    <section class="card public-bracket-card">
        <div class="section-head">
            <div>
                <span class="eyebrow">Eliminación directa</span>
                <h2>Bracket en vivo</h2>
            </div>
            <span class="badge badge--gold"><?= e((string) $totalRondas) ?> rondas</span>
        </div>
        <div class="bracket-shell public-bracket-shell">
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
                        <div class="bracket__match <?= $esFinal ? 'bracket__match--final' : '' ?> <?= $esFinal && $fin ? 'bracket__match--crowned' : '' ?>">
                            <?php if ($esFinal): ?><div class="bracket__final-tag">Final</div><?php endif; ?>
                            <div class="bm-row <?= $ganaA ? 'bm-win' : '' ?>"><span><?= e($p['a_label'] ?? ($bye ? '—' : '(por definir)')) ?></span></div>
                            <div class="bm-row <?= $ganaB ? 'bm-win' : '' ?>"><span><?= e($p['b_label'] ?? ($bye ? '—' : '(por definir)')) ?></span></div>
                            <div class="bm-foot">
                                <?php if ($bye): ?><span class="badge badge--gray">bye</span>
                                <?php elseif ($fin): ?><span class="badge badge--green"><?= e($resumen($p)) ?></span>
                                <?php else: ?><span class="badge badge--gray">pendiente</span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($tabla !== []): ?>
    <section class="card public-standings-card">
        <div class="section-head">
            <div>
                <span class="eyebrow">Leaderboard</span>
                <h2>Tabla de posiciones</h2>
            </div>
            <?php if ($clasificados > 0): ?><span class="badge badge--green">Top <?= e((string) $clasificados) ?> clasifica</span><?php endif; ?>
        </div>
        <p class="muted public-section-copy">Puntos, partidos y sets ordenados para lectura rápida desde la pantalla pública.</p>
        <div class="table-wrap">
            <table class="table table--standings public-standings">
                <thead><tr><th>#</th><th>Jugador</th><th>PJ</th><th>PG</th><th>PP</th><th>Pts</th><th>Sets</th></tr></thead>
                <tbody>
                <?php foreach ($tabla as $f): ?>
                    <tr class="<?= $f['posicion'] <= $clasificados ? 'row--clasifica' : '' ?> <?= $f['posicion'] <= 3 ? 'public-standings__top' : '' ?>">
                        <td><strong><?= e((string) $f['posicion']) ?></strong></td>
                        <td><?= e($f['nombre']) ?></td>
                        <td><?= e((string) $f['pj']) ?></td>
                        <td><?= e((string) $f['pg']) ?></td>
                        <td><?= e((string) $f['pp']) ?></td>
                        <td><strong><?= e((string) $f['puntos']) ?></strong></td>
                        <td><?= e((string) $f['sf']) ?>-<?= e((string) $f['sc']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($tabla === [] && $totalRondas === 0): ?>
    <section class="card public-empty">
        <span class="eyebrow">Pantalla lista</span>
        <h2>Todavía no hay partidos para mostrar</h2>
        <p class="muted">Cuando se carguen partidos, esta vista va a mostrar próximos cruces, tabla y bracket automáticamente. Podés dejarla proyectada: se refresca cada 60 segundos.</p>
    </section>
<?php endif; ?>

<script>setTimeout(function () { location.reload(); }, 60000);</script>
