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

// Tournament sub-navigation (same tabs as the hub).
$estado = (string) $torneo['estado'];
$conLiga = !in_array($estado, [EstadoTorneo::BORRADOR->value, EstadoTorneo::INSCRIPCION_ABIERTA->value, EstadoTorneo::INSCRIPCION_CERRADA->value], true);
$conBracket = $conLiga && $estado !== EstadoTorneo::FASE_LIGA->value;
$tabs = [
    ['href' => "/torneos/{$tid}", 'label' => 'Resumen', 'icon' => 'home', 'ok' => true, 'actual' => false],
    ['href' => "/torneos/{$tid}/partidos", 'label' => 'Partidos', 'icon' => 'paddle', 'ok' => $conLiga, 'actual' => false],
    ['href' => "/torneos/{$tid}/tabla", 'label' => 'Tabla', 'icon' => 'table', 'ok' => $conLiga, 'actual' => false],
    ['href' => "/torneos/{$tid}/bracket", 'label' => 'Llaves', 'icon' => 'bracket', 'ok' => $conBracket, 'actual' => true],
    ['href' => "/torneos/{$tid}/podio", 'label' => 'Podio', 'icon' => 'podium', 'ok' => $conBracket, 'actual' => false],
];
?>
<nav aria-label="Ruta" class="no-print">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><a href="/torneos/<?= e($tid) ?>"><?= e($torneo['nombre']) ?></a></li>
        <li><span aria-current="page">Llaves</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= icon('bracket') ?> Fase eliminatoria</span>
        <h1>Llaves</h1>
        <p>
            <?= e($torneo['nombre']) ?> ·
            <?php if ($totalRondas > 0): ?>
                eliminación directa en <?= e((string) $totalRondas) ?> ronda<?= $totalRondas === 1 ? '' : 's' ?>.
                <?= $campeon !== null ? ($finalizado ? 'Campeón oficial: ' . e($campeon) . '.' : 'La final ya tiene ganador.') : 'El trono sigue en disputa.' ?>
            <?php else: ?>
                el cuadro todavía no fue generado.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($totalRondas > 0): ?>
        <div class="actions no-print">
            <button class="btn btn--ghost" type="button" data-print><?= icon('printer') ?> Imprimir</button>
        </div>
    <?php endif; ?>
</header>

<nav class="tabs no-print" aria-label="Secciones del torneo">
    <?php foreach ($tabs as $tab): ?>
        <?php if ($tab['ok'] || $tab['actual']): ?>
            <a class="tabs__link" href="<?= e($tab['href']) ?>"<?= $tab['actual'] ? ' aria-current="page"' : '' ?>><?= icon($tab['icon']) ?> <?= e($tab['label']) ?></a>
        <?php else: ?>
            <span class="tabs__link is-disabled" aria-disabled="true" title="Disponible más adelante"><?= icon($tab['icon']) ?> <?= e($tab['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<?php if ($campeon !== null && !$finalizado && $puedeGestionar): ?>
    <div class="flash flash--info no-print">
        <?= icon('flag') ?>
        <div class="flash__body"><strong>La final ya tiene ganador.</strong> Al confirmar, el torneo se cierra y el campeón queda consagrado.</div>
        <form method="post" action="/torneos/<?= e($tid) ?>/finalizar" class="inline"
              data-confirm="El torneo pasa a Finalizado y <?= e($campeon) ?> queda consagrado como campeón."
              data-confirm-title="¿Finalizar el torneo?" data-confirm-ok="Finalizar y consagrar" data-confirm-tone="info">
            <?= csrf_field() ?>
            <button class="btn btn--primary" type="submit"><?= icon('flag') ?> Finalizar y consagrar</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($totalRondas === 0): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('bracket') ?></span>
        <p class="empty-state__title">Todavía no hay llaves</p>
        <p>El cuadro eliminatorio se genera cuando termina la liga, promoviendo a los mejores de la tabla.</p>
        <?php if ($conLiga): ?>
            <div class="actions no-print">
                <a class="btn btn--secondary" href="/torneos/<?= e($tid) ?>/tabla"><?= icon('table') ?> Ver la tabla</a>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($campeon !== null): ?>
        <section class="consagracion <?= $finalizado ? 'consagracion--oficial' : '' ?>" aria-labelledby="campeon-title">
            <div class="consagracion__trofeo"><?= icon('trophy') ?></div>
            <div class="consagracion__label"><?= $finalizado ? 'Campeón del torneo' : 'Campeón (a confirmar)' ?></div>
            <h2 class="consagracion__nombre" id="campeon-title"><?= e($campeon) ?></h2>
            <?php if ($subcampeon !== null): ?>
                <div class="consagracion__sub">Subcampeón: <strong><?= e($subcampeon) ?></strong></div>
            <?php endif; ?>
            <a class="btn no-print" href="/torneos/<?= e($tid) ?>/podio"><?= icon('podium') ?> Ver podio completo</a>
        </section>
    <?php endif; ?>

    <p class="bracket-hint show-mobile no-print"><?= icon('arrow-right', 'icon--sm') ?> Deslizá para ver todas las rondas.</p>
    <section class="bracket-shell" aria-label="Cuadro eliminatorio">
        <div class="bracket" tabindex="0" aria-label="Cuadro eliminatorio, desplazable">
            <?php for ($ronda = 1; $ronda <= $totalRondas; $ronda++): ?>
                <?php $esFinal = $ronda === $totalRondas; ?>
                <div class="bracket__round<?= $esFinal ? ' bracket__round--final' : '' ?>">
                    <h3 class="bracket__title"><?= e(BracketEliminacion::nombreRonda($ronda, $totalRondas)) ?></h3>
                    <?php foreach ($porRonda[$ronda] ?? [] as $p): ?>
                        <?php
                        $fin = $p['estado'] === 'FINALIZADO';
                        $bye = $fin && ($p['inscripcion_a_id'] === null || $p['inscripcion_b_id'] === null);
                        $ganaA = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_a_id'];
                        $ganaB = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_b_id'];
                        $ambos = $p['inscripcion_a_id'] !== null && $p['inscripcion_b_id'] !== null;
                        $r = $fin ? $resumen($p) : null;
                        $lados = [
                            ['nombre' => $p['a_label'] ?? null, 'gana' => $ganaA, 'sets' => $r['sa'] ?? null],
                            ['nombre' => $p['b_label'] ?? null, 'gana' => $ganaB, 'sets' => $r['sb'] ?? null],
                        ];
                        ?>
                        <div class="bracket__match<?= $esFinal ? ' bracket__match--final' : '' ?><?= $esFinal && $fin ? ' bracket__match--crowned' : '' ?>">
                            <?php if ($esFinal): ?><div class="bracket__final-tag"><?= icon('crown', 'icon--sm') ?> <?= $fin ? 'Final jugada' : 'La gran final' ?></div><?php endif; ?>
                            <?php foreach ($lados as $lado): ?>
                                <div class="bm-row<?= $lado['gana'] ? ' bm-win' : '' ?>">
                                    <span class="bm-name">
                                        <?php if ($lado['gana']): ?><?= icon('check-circle', 'icon--sm') ?><span class="sr-only">Ganador:</span><?php endif; ?>
                                        <?php if ($lado['nombre'] !== null): ?>
                                            <?= e($lado['nombre']) ?>
                                        <?php else: ?>
                                            <span class="bm-tbd"><?= $bye ? '—' : 'Por definir' ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($fin && !$bye): ?><b class="bm-sets"><span class="sr-only">sets: </span><?= e((string) $lado['sets']) ?></b><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="bm-foot">
                                <?php if ($bye): ?>
                                    <span class="badge badge--gray">Pasa directo</span>
                                <?php elseif ($fin): ?>
                                    <small class="muted"><?= e($r['detalle']) ?></small>
                                <?php else: ?>
                                    <span class="badge badge--gray">Pendiente</span>
                                <?php endif; ?>
                                <?php if ($puedeAnotar && $ambos): ?>
                                    <a class="btn btn--sm <?= $fin ? 'btn--ghost' : 'btn--secondary' ?> no-print" href="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $p['id']) ?>"
                                       aria-label="<?= $fin ? 'Editar' : 'Cargar' ?> resultado de <?= e((string) $p['a_label']) ?> contra <?= e((string) $p['b_label']) ?>">
                                        <?= icon($fin ? 'edit' : 'plus') ?> <?= $fin ? 'Editar' : 'Cargar' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>
<?php endif; ?>
