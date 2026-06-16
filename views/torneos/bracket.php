<?php
/** @var array<string,mixed> $torneo */
/** @var array<int,list<array<string,mixed>>> $porRonda */
/** @var int $totalRondas */
/** @var ?string $campeon */
/** @var ?string $subcampeon */

use PingPong\Domain\BracketEliminacion;
use PingPong\Domain\EstadoTorneo;

$puedeAnotar = $rol?->puedeAnotar() ?? false;
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$tid = (string) $torneo['id'];
$finalizado = $torneo['estado'] === EstadoTorneo::FINALIZADO->value;
$subcampeon = $subcampeon ?? null;

/** Resumen de sets ganados por cada lado + detalle "11-5, 9-11, …". */
$resumen = static function (array $p): array {
    $sa = $sb = 0;
    $detalle = [];
    foreach ($p['sets'] ?? [] as $s) {
        $a = (int) $s['puntos_a'];
        $b = (int) $s['puntos_b'];
        if ($a > $b) { $sa++; } elseif ($b > $a) { $sb++; }
        $detalle[] = $a . '-' . $b;
    }
    return ['sa' => $sa, 'sb' => $sb, 'detalle' => implode(', ', $detalle)];
};
?>
<section class="page-head">
    <div>
        <h1><?= e($torneo['nombre']) ?> · Bracket</h1>
        <p class="muted">Eliminación directa · <?= e((string) $totalRondas) ?> ronda<?= $totalRondas === 1 ? '' : 's' ?>.</p>
    </div>
    <div class="actions no-print">
        <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/tabla">🏆 Tabla</a>
        <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/podio">🥇 Podio</a>
        <button class="btn btn--ghost btn--sm" onclick="window.print()">🖨️ PDF</button>
        <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a>
    </div>
</section>

<?php if ($campeon !== null && !$finalizado && $puedeGestionar): ?>
    <div class="flash flash--info no-print">
        La final ya tiene ganador. Cuando confirmes, el torneo se cierra y el campeón queda consagrado.
        <form method="post" action="/torneos/<?= e($tid) ?>/finalizar" class="inline">
            <?= csrf_field() ?>
            <button class="btn btn--sm btn--primary" type="submit">🏁 Finalizar y consagrar</button>
        </form>
    </div>
<?php endif; ?>

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
                $ambos = $p['inscripcion_a_id'] !== null && $p['inscripcion_b_id'] !== null;
                $r = $fin ? $resumen($p) : null;
                $esFinal = $ronda === $totalRondas;
                ?>
                <div class="bracket__match <?= $esFinal ? 'bracket__match--final' : '' ?>">
                    <?php if ($esFinal): ?><div class="bracket__final-tag">🏆 Final</div><?php endif; ?>
                    <div class="bm-row <?= $ganaA ? 'bm-win' : '' ?>">
                        <span><?= e($p['a_label'] ?? ($bye ? '—' : '(por definir)')) ?></span>
                        <?php if ($fin && !$bye): ?><b class="bm-sets"><?= e((string) $r['sa']) ?></b><?php endif; ?>
                    </div>
                    <div class="bm-row <?= $ganaB ? 'bm-win' : '' ?>">
                        <span><?= e($p['b_label'] ?? ($bye ? '—' : '(por definir)')) ?></span>
                        <?php if ($fin && !$bye): ?><b class="bm-sets"><?= e((string) $r['sb']) ?></b><?php endif; ?>
                    </div>
                    <div class="bm-foot">
                        <?php if ($bye): ?>
                            <span class="badge badge--gray">bye</span>
                        <?php elseif ($fin): ?>
                            <small class="muted"><?= e($r['detalle']) ?></small>
                        <?php else: ?>
                            <span class="badge badge--gray">pendiente</span>
                        <?php endif; ?>
                        <?php if ($puedeAnotar && $ambos): ?>
                            <a class="link no-print" href="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $p['id']) ?>"><?= $fin ? 'Editar' : 'Cargar' ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endfor; ?>
</div>

<?php if ($campeon !== null): ?>
    <section class="consagracion <?= $finalizado ? 'consagracion--oficial' : '' ?>">
        <div class="consagracion__trofeo">🏆</div>
        <div class="consagracion__label"><?= $finalizado ? 'Campeón del torneo' : 'Campeón (a confirmar)' ?></div>
        <div class="consagracion__nombre"><?= e($campeon) ?></div>
        <?php if ($subcampeon !== null): ?>
            <div class="consagracion__sub">🥈 Subcampeón: <strong><?= e($subcampeon) ?></strong></div>
        <?php endif; ?>
        <a class="btn btn--sm no-print" href="/torneos/<?= e($tid) ?>/podio">Ver podio completo →</a>
    </section>
<?php endif; ?>
