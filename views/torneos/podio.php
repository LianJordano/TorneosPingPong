<?php
/** @var array<string,mixed> $torneo */
/** @var list<array<string,mixed>> $podio */
/** @var float $pozo */
/** @var bool $finalizado */
$tid = (string) $torneo['id'];
$medalla = ['', '🥇', '🥈', '🥉'];
$top3 = array_slice($podio, 0, 3);
?>
<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Podio de campeones</span>
            <h1>La gloria · <?= e($torneo['nombre']) ?></h1>
            <p class="muted">El cierre grande del cuadro: campeón, escoltas y premios con jerarquía de final.</p>
            <div class="meta-row">
                <span class="meta-pill">💰 Pozo $<?= e(number_format($pozo, 2)) ?></span>
                <span class="meta-pill"><?= $finalizado ? '✅ Resultado oficial' : '⏳ Resultado provisional' ?></span>
            </div>
        </div>
        <div class="hero-side">
            <div class="actions no-print">
                <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/bracket">🎯 Bracket</a>
                <button class="btn btn--sm" onclick="window.print()">🖨️ Imprimir / PDF</button>
                <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a>
            </div>
        </div>
    </div>
</section>

<?php if (!$finalizado): ?>
    <div class="flash flash--info no-print">El torneo no está finalizado: este podio es <strong>provisional</strong>.</div>
<?php endif; ?>

<?php if ($podio === []): ?>
    <section class="card"><p class="muted">El bracket todavía no tiene resultados.</p></section>
<?php else: ?>
    <div class="prize-grid">
        <div class="prize-tile"><span class="prize-tile__label">Premiados</span><span class="prize-tile__value"><?= e((string) count(array_filter($podio, fn($p) => $p['premiado']))) ?></span><span class="prize-tile__meta">Posiciones con premio configurado</span></div>
        <div class="prize-tile"><span class="prize-tile__label">Top 1</span><span class="prize-tile__value"><?= e($top3[0]['nombre'] ?? '—') ?></span><span class="prize-tile__meta">Campeón actual del cuadro</span></div>
    </div>
    <?php if (isset($top3[0])): ?>
        <section class="winner-spotlight">
            <div class="winner-spotlight__label">Campeón épico</div>
            <div class="winner-spotlight__name"><?= e($top3[0]['nombre']) ?></div>
            <p><?= $finalizado ? 'Consagrado oficialmente como maestro de la mesa.' : 'Lidera el cuadro de forma provisional hasta el cierre oficial.' ?></p>
        </section>
    <?php endif; ?>
    <div class="podio">
        <?php foreach ($top3 as $p): ?>
            <div class="podio__card podio__card--<?= e((string) $p['posicion']) ?>">
                <div class="podio__medal"><?= $medalla[$p['posicion']] ?? '' ?></div>
                <div class="podio__pos"><?= e((string) $p['posicion']) ?>° puesto</div>
                <?php if ((int) $p['posicion'] === 1): ?><div class="podio__rank-name">Campeón</div><?php endif; ?>
                <?php if ((int) $p['posicion'] === 2): ?><div class="podio__rank-name">Subcampeón</div><?php endif; ?>
                <?php if ((int) $p['posicion'] === 3): ?><div class="podio__rank-name">Tercer maestro</div><?php endif; ?>
                <div class="podio__name"><?= e($p['nombre']) ?></div>
                <?php if ($p['monto'] !== null): ?>
                    <div class="podio__prize">$<?= e(number_format((float) $p['monto'], 2)) ?>
                        <?php if ($p['porcentaje'] !== null): ?><small>(<?= e(rtrim(rtrim(number_format((float) $p['porcentaje'], 2), '0'), '.')) ?>%)</small><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="card">
        <h2>Clasificación final del bracket</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Jugador</th><th>Premio</th></tr></thead>
                <tbody>
                <?php foreach ($podio as $p): ?>
                    <tr class="<?= $p['premiado'] ? 'row--clasifica' : '' ?>">
                        <td><strong><?= e((string) $p['posicion']) ?></strong></td>
                        <td><?= e($p['nombre']) ?></td>
                        <td><?= $p['monto'] !== null ? '$' . e(number_format((float) $p['monto'], 2)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
