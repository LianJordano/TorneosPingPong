<?php
/** @var array<string,mixed> $torneo */
/** @var array<int,list<array<string,mixed>>> $porRonda */
/** @var array{total:int,finalizados:int} $progreso */
$puedeAnotar = $rol?->puedeAnotar() ?? false;
$tid = (string) $torneo['id'];

/** Resumen "2-1" + detalle de sets de un partido finalizado. */
$resumen = static function (array $sets): array {
    $sa = $sb = 0;
    $detalle = [];
    foreach ($sets as $s) {
        $a = (int) $s['puntos_a'];
        $b = (int) $s['puntos_b'];
        if ($a > $b) { $sa++; } elseif ($b > $a) { $sb++; }
        $detalle[] = $a . '-' . $b;
    }
    return ['sets' => $sa . '-' . $sb, 'detalle' => implode(', ', $detalle)];
};
?>
<?php $pct = $progreso['total'] > 0 ? (int) round(($progreso['finalizados'] / $progreso['total']) * 100) : 0; ?>
<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Mesa única · orden de juego</span>
            <h1><?= e($torneo['nombre']) ?> · Partidos</h1>
            <p class="muted">Vista pensada para el momento del torneo: mirás la jornada, cargás resultado y pasa el siguiente.</p>
            <div class="meta-row">
                <span class="meta-pill">🏓 1 mesa disponible</span>
                <span class="meta-pill">📋 <?= e((string) $progreso['finalizados']) ?> / <?= e((string) $progreso['total']) ?> jugados</span>
                <span class="meta-pill">⚡ <?= e((string) $pct) ?>% completado</span>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-card">
                <span class="hero-card__label">Ritmo competitivo</span>
                <strong><?= e((string) $pct) ?>%</strong>
                <div class="progress-bar"><span style="width: <?= e((string) $pct) ?>%"></span></div>
            </div>
            <div class="actions no-print">
                <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/tabla">🏆 Tabla</a>
                <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>/partidos.csv">⬇️ CSV</a>
                <button class="btn btn--ghost btn--sm" onclick="window.print()">🖨️ PDF</button>
                <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a>
            </div>
        </div>
    </div>
</section>

<?php if ($porRonda === []): ?>
    <section class="card"><p class="muted">No hay partidos generados.</p></section>
<?php else: ?>
    <?php foreach ($porRonda as $ronda => $partidos): ?>
        <section class="card">
            <h2>Jornada <?= e((string) $ronda) ?></h2>
            <div class="table-wrap">
            <table class="table table--matches">
                <tbody>
                <?php foreach ($partidos as $p): ?>
                    <?php
                    $fin = $p['estado'] === 'FINALIZADO';
                    $r = $fin ? $resumen($p['sets']) : null;
                    $ganaA = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_a_id'];
                    $ganaB = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_b_id'];
                    ?>
                    <tr>
                        <td class="text-right <?= $ganaA ? 'winner' : '' ?>"><?= e($p['a_label'] ?? 'BYE') ?></td>
                        <td class="score">
                            <?php if ($fin): ?>
                                <span class="score-chip"><?= e($r['sets']) ?></span>
                                <?php if ($p['tipo_resultado'] !== 'NORMAL'): ?>
                                    <small class="badge badge--red"><?= e(\PingPong\Domain\TipoResultado::from($p['tipo_resultado'])->label()) ?></small>
                                <?php elseif ($r['detalle'] !== ''): ?>
                                    <small class="muted"><?= e($r['detalle']) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge--gray">pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $ganaB ? 'winner' : '' ?>"><?= e($p['b_label'] ?? 'BYE') ?></td>
                        <td class="text-right">
                            <?php if ($puedeAnotar): ?>
                                <a class="btn btn--sm <?= $fin ? 'btn--ghost' : 'btn--primary' ?> match-action" href="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $p['id']) ?>">
                                    <?= $fin ? 'Editar' : 'Cargar' ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
