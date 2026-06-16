<?php
/** @var array<string,mixed> $torneo */
/** @var \PingPong\Domain\EstadoTorneo $estado */
/** @var list<array<string,mixed>> $tabla */
/** @var array<int,list<array<string,mixed>>> $porRonda */
/** @var int $totalRondas */
/** @var ?string $campeon */
/** @var list<array<string,mixed>> $proximos */
/** @var int $clasificados */

use PingPong\Domain\BracketEliminacion;

$resumen = static function (array $p): string {
    $sa = $sb = 0;
    foreach ($p['sets'] ?? [] as $s) {
        if ((int) $s['puntos_a'] > (int) $s['puntos_b']) { $sa++; } elseif ((int) $s['puntos_b'] > (int) $s['puntos_a']) { $sb++; }
    }
    return $sa . '-' . $sb;
};
?>
<section class="page-head">
    <div>
        <h1><?= e($torneo['nombre']) ?> <span class="badge <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></h1>
        <p class="muted">Vista pública · se actualiza sola cada 60 s</p>
    </div>
</section>

<?php if ($campeon !== null): ?>
    <div class="campeon">🏆 Campeón: <strong><?= e($campeon) ?></strong></div>
<?php endif; ?>

<?php if ($proximos !== []): ?>
    <section class="card">
        <h2>Próximos partidos</h2>
        <table class="table">
            <tbody>
            <?php foreach ($proximos as $p): ?>
                <tr>
                    <td class="text-right"><?= e($p['a_label'] ?? '—') ?></td>
                    <td class="score">vs</td>
                    <td><?= e($p['b_label'] ?? '—') ?></td>
                    <td class="muted"><?= e($p['fase'] === 'ELIMINATORIA' ? 'Bracket' : 'Jornada ' . $p['ronda']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if ($totalRondas > 0): ?>
    <section class="card">
        <h2>Bracket</h2>
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
                        ?>
                        <div class="bracket__match">
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
    </section>
<?php endif; ?>

<?php if ($tabla !== []): ?>
    <section class="card">
        <h2>Tabla de posiciones</h2>
        <table class="table table--standings">
            <thead><tr><th>#</th><th>Jugador</th><th>PJ</th><th>PG</th><th>PP</th><th>Pts</th><th>Sets</th></tr></thead>
            <tbody>
            <?php foreach ($tabla as $f): ?>
                <tr class="<?= $f['posicion'] <= $clasificados ? 'row--clasifica' : '' ?>">
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
    </section>
<?php endif; ?>

<?php if ($tabla === [] && $totalRondas === 0): ?>
    <section class="card"><p class="muted">El torneo todavía no tiene partidos para mostrar.</p></section>
<?php endif; ?>

<script>setTimeout(function () { location.reload(); }, 60000);</script>
