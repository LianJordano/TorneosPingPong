<?php
/** @var array<string,mixed> $torneo */
/** @var list<array<string,mixed>> $tabla */
/** @var array{total:int,finalizados:int} $progreso */
/** @var int $clasificados */
$tid = (string) $torneo['id'];
$ligaCompleta = $progreso['total'] > 0 && $progreso['finalizados'] === $progreso['total'];
$fmtRatio = static fn (?float $r): string => $r === null ? '—' : number_format($r, 2);
?>
<?php $pct = $progreso['total'] > 0 ? (int) round(($progreso['finalizados'] / $progreso['total']) * 100) : 0; ?>
<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Standings intelligence</span>
            <h1><?= e($torneo['nombre']) ?> · Tabla de posiciones</h1>
            <p class="muted">Jerarquía competitiva con desempates visibles y foco en los clasificados.</p>
            <div class="meta-row">
                <span class="meta-pill">📊 <?= e((string) $progreso['finalizados']) ?>/<?= e((string) $progreso['total']) ?> jugados</span>
                <span class="meta-pill">🏁 Top <?= e((string) $clasificados) ?> clasifican</span>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-card">
                <span class="hero-card__label">Avance de la liga</span>
                <strong><?= e((string) $pct) ?>%</strong>
                <div class="progress-bar"><span style="width: <?= e((string) $pct) ?>%"></span></div>
            </div>
            <div class="actions no-print">
                <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/partidos">📋 Partidos</a>
                <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>/tabla.csv">⬇️ CSV</a>
                <button class="btn btn--ghost btn--sm" onclick="window.print()">🖨️ PDF</button>
                <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a>
            </div>
        </div>
    </div>
</section>

<?php
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$enLiga = $torneo['estado'] === \PingPong\Domain\EstadoTorneo::FASE_LIGA->value;
?>
<?php if ($ligaCompleta && $enLiga): ?>
    <div class="flash flash--success">
        ✅ Liga completa. Los primeros <?= e((string) $clasificados) ?> pasan al bracket.
        <?php if ($puedeGestionar): ?>
            <form method="post" action="/torneos/<?= e($tid) ?>/bracket" class="inline"
                  onsubmit="return confirm('¿Promover a los <?= e((string) $clasificados) ?> mejores y generar el bracket de eliminación?');">
                <?= csrf_field() ?>
                <button class="btn btn--primary btn--sm" type="submit">🎯 Promover y generar bracket</button>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($ligaCompleta): ?>
    <div class="flash flash--info">El bracket ya fue generado. <a href="/torneos/<?= e($tid) ?>/bracket">Ver el cuadro →</a></div>
<?php endif; ?>

<section class="card">
    <?php if ($tabla === []): ?>
        <p class="muted">No hay jugadores en la tabla.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="table table--standings">
            <thead>
                <tr>
                    <th>#</th><th>Jugador</th>
                    <th title="Partidos jugados">PJ</th>
                    <th title="Ganados">PG</th>
                    <th title="Perdidos">PP</th>
                    <th title="Puntos de clasificación">Pts</th>
                    <th title="Sets favor-contra">Sets</th>
                    <th title="Ratio de sets">R.Sets</th>
                    <th title="Puntos favor-contra">Puntos</th>
                    <th title="Ratio de puntos">R.Pts</th>
                    <th>Desempate</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tabla as $fila): ?>
                <tr class="<?= $fila['posicion'] <= $clasificados ? 'row--clasifica' : '' ?>">
                    <td><strong><?= e((string) $fila['posicion']) ?></strong></td>
                    <td><?= e($fila['nombre']) ?></td>
                    <td><?= e((string) $fila['pj']) ?></td>
                    <td><?= e((string) $fila['pg']) ?></td>
                    <td><?= e((string) $fila['pp']) ?></td>
                    <td><strong><?= e((string) $fila['puntos']) ?></strong></td>
                    <td><?= e((string) $fila['sf']) ?>-<?= e((string) $fila['sc']) ?></td>
                    <td><?= e($fmtRatio($fila['set_ratio'])) ?></td>
                    <td><?= e((string) $fila['pf']) ?>-<?= e((string) $fila['pc']) ?></td>
                    <td><?= e($fmtRatio($fila['punto_ratio'])) ?></td>
                    <td><?php if (!empty($fila['desempate'])): ?><small class="badge badge--blue"><?= e($fila['desempate']) ?></small><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="muted small">La fila <span class="swatch swatch--clasifica"></span> indica los clasificados al bracket. El "Desempate" muestra el criterio que definió la posición frente al jugador anterior. <a href="/glosario">¿Qué significan los desempates?</a></p>
    <?php endif; ?>
</section>
