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
<section class="page-head">
    <div>
        <h1><?= e($torneo['nombre']) ?> · Partidos</h1>
        <p class="muted">Liga: <?= e((string) $progreso['finalizados']) ?> de <?= e((string) $progreso['total']) ?> partidos jugados.</p>
    </div>
    <div class="actions no-print">
        <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/tabla">🏆 Tabla</a>
        <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>/partidos.csv">⬇️ CSV</a>
        <button class="btn btn--ghost btn--sm" onclick="window.print()">🖨️ PDF</button>
        <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a>
    </div>
</section>

<?php if ($porRonda === []): ?>
    <section class="card"><p class="muted">No hay partidos generados.</p></section>
<?php else: ?>
    <?php foreach ($porRonda as $ronda => $partidos): ?>
        <section class="card">
            <h2>Jornada <?= e((string) $ronda) ?></h2>
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
                                <strong><?= e($r['sets']) ?></strong>
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
                                <a class="link" href="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $p['id']) ?>">
                                    <?= $fin ? 'Editar' : 'Cargar' ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
