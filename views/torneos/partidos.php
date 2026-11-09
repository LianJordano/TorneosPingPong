<?php
/** @var array<string,mixed> $torneo */
/** @var array<int,list<array<string,mixed>>> $porRonda */
/** @var array{total:int,finalizados:int} $progreso */

use PingPong\Domain\EstadoTorneo;
use PingPong\Domain\TipoResultado;

$puedeAnotar = $rol?->puedeAnotar() ?? false;
$tid = (string) $torneo['id'];
$hayPartidos = false;
$proximoPendiente = null;

foreach ($porRonda as $ronda => $partidos) {
    foreach ($partidos as $p) {
        $hayPartidos = true;
        if ($proximoPendiente === null && $p['estado'] !== 'FINALIZADO') {
            $proximoPendiente = ['ronda' => $ronda, 'partido' => $p];
        }
    }
}
$proximoPendienteId = $proximoPendiente !== null ? (int) $proximoPendiente['partido']['id'] : null;
$pct = $progreso['total'] > 0 ? (int) round(($progreso['finalizados'] / $progreso['total']) * 100) : 0;

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

// Tournament sub-navigation (same tabs as the hub).
$estado = (string) $torneo['estado'];
$conLiga = !in_array($estado, [EstadoTorneo::BORRADOR->value, EstadoTorneo::INSCRIPCION_ABIERTA->value, EstadoTorneo::INSCRIPCION_CERRADA->value], true);
$conBracket = $conLiga && $estado !== EstadoTorneo::FASE_LIGA->value;
$tabs = [
    ['href' => "/torneos/{$tid}", 'label' => 'Resumen', 'icon' => 'home', 'ok' => true, 'actual' => false],
    ['href' => "/torneos/{$tid}/partidos", 'label' => 'Partidos', 'icon' => 'paddle', 'ok' => $conLiga, 'actual' => true],
    ['href' => "/torneos/{$tid}/tabla", 'label' => 'Tabla', 'icon' => 'table', 'ok' => $conLiga, 'actual' => false],
    ['href' => "/torneos/{$tid}/bracket", 'label' => 'Llaves', 'icon' => 'bracket', 'ok' => $conBracket, 'actual' => false],
    ['href' => "/torneos/{$tid}/podio", 'label' => 'Podio', 'icon' => 'podium', 'ok' => $conBracket, 'actual' => false],
];
?>
<nav aria-label="Ruta" class="no-print">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><a href="/torneos/<?= e($tid) ?>"><?= e($torneo['nombre']) ?></a></li>
        <li><span aria-current="page">Partidos</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= icon('paddle') ?> Fase de liga · mesa única</span>
        <h1>Partidos</h1>
        <p>Orden de juego de <?= e($torneo['nombre']) ?>. Cargá cada resultado al terminar y pasá al siguiente.</p>
    </div>
    <div class="actions no-print">
        <?php if ($puedeAnotar && $proximoPendiente !== null): ?>
            <a class="btn btn--primary" href="/torneos/<?= e($tid) ?>/anotador"><?= icon('scoreboard') ?> Modo anotador</a>
        <?php endif; ?>
        <a class="btn btn--secondary" href="/torneos/<?= e($tid) ?>/partidos.csv"><?= icon('spreadsheet') ?> CSV</a>
        <button class="btn btn--ghost" type="button" data-print><?= icon('printer') ?> Imprimir</button>
    </div>
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

<?php if (!$hayPartidos): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('calendar') ?></span>
        <p class="empty-state__title">Todavía no hay partidos</p>
        <p>El fixture de la liga se genera desde el resumen del torneo, una vez cerrada la inscripción.</p>
        <div class="actions no-print">
            <a class="btn btn--secondary" href="/torneos/<?= e($tid) ?>"><?= icon('arrow-left') ?> Ir al resumen</a>
        </div>
    </div>
<?php else: ?>
    <section class="card match-progress" aria-labelledby="avance-title">
        <div class="spread">
            <h2 id="avance-title" class="match-progress__title">Avance de la liga</h2>
            <span class="match-progress__count"><strong><?= e((string) $progreso['finalizados']) ?></strong> de <?= e((string) $progreso['total']) ?> jugados · <?= e((string) $pct) ?>%</span>
        </div>
        <div class="progress" role="progressbar" aria-labelledby="avance-title" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) $pct) ?>"><span style="--value: <?= e((string) $pct) ?>%"></span></div>
    </section>

    <?php if ($proximoPendiente !== null): ?>
        <?php $p = $proximoPendiente['partido']; ?>
        <section class="next-match-card next-match-card--pending no-print" aria-labelledby="proximo-mesa-title">
            <div class="next-match-card__body">
                <span class="next-match-card__kicker"><?= icon('target') ?> Próximo en mesa</span>
                <h2 id="proximo-mesa-title"><?= e($p['a_label'] ?? 'BYE') ?> <span>vs</span> <?= e($p['b_label'] ?? 'BYE') ?></h2>
                <div class="next-match-card__meta">
                    <span class="badge">Jornada <?= e((string) $proximoPendiente['ronda']) ?></span>
                    <span class="badge badge--dot">Pendiente</span>
                </div>
            </div>
            <?php if ($puedeAnotar): ?>
                <a class="btn btn--lg next-match-card__action" href="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $p['id']) ?>"><?= icon('play') ?> Cargar resultado</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="next-match-card next-match-card--complete no-print" aria-labelledby="partidos-completos-title">
            <div class="next-match-card__body">
                <span class="next-match-card__kicker"><?= icon('check-circle') ?> Liga completa</span>
                <h2 id="partidos-completos-title">Todos los partidos de la liga están cargados.</h2>
                <p>La mesa queda libre: revisá la tabla, exportá los resultados o imprimí el resumen.</p>
            </div>
            <a class="btn btn--primary next-match-card__action" href="/torneos/<?= e($tid) ?>/tabla"><?= icon('table') ?> Ver tabla</a>
        </section>
    <?php endif; ?>

    <?php foreach ($porRonda as $ronda => $partidos): ?>
        <?php $jugados = count(array_filter($partidos, static fn (array $x): bool => $x['estado'] === 'FINALIZADO')); ?>
        <section class="card match-round" aria-labelledby="jornada-<?= e((string) $ronda) ?>">
            <div class="match-round__head">
                <h2 id="jornada-<?= e((string) $ronda) ?>">Jornada <?= e((string) $ronda) ?></h2>
                <?php if ($jugados === count($partidos)): ?>
                    <span class="badge badge--green badge--dot">Completa</span>
                <?php else: ?>
                    <span class="badge badge--gray"><?= e((string) $jugados) ?>/<?= e((string) count($partidos)) ?> jugados</span>
                <?php endif; ?>
            </div>
            <ul class="match-list">
                <?php foreach ($partidos as $p): ?>
                    <?php
                    $fin = $p['estado'] === 'FINALIZADO';
                    $r = $fin ? $resumen($p['sets']) : null;
                    $ganaA = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_a_id'];
                    $ganaB = $fin && (int) $p['ganador_inscripcion_id'] === (int) $p['inscripcion_b_id'];
                    $esProximo = (int) $p['id'] === $proximoPendienteId;
                    $nombreA = $p['a_label'] ?? 'BYE';
                    $nombreB = $p['b_label'] ?? 'BYE';
                    ?>
                    <li class="match<?= $esProximo ? ' match-row--next' : '' ?>">
                        <span class="match__side match__side--a<?= $ganaA ? ' winner' : '' ?>">
                            <?php if ($ganaA): ?><?= icon('check-circle', 'icon--sm') ?><span class="sr-only">Ganador:</span><?php endif; ?>
                            <span class="match__name"><?= e($nombreA) ?></span>
                        </span>
                        <span class="match__score">
                            <?php if ($fin): ?>
                                <span class="score-chip"><span class="sr-only">Sets </span><?= e($r['sets']) ?></span>
                                <?php if ($p['tipo_resultado'] !== 'NORMAL'): ?>
                                    <span class="badge badge--red"><?= e(TipoResultado::from($p['tipo_resultado'])->label()) ?></span>
                                <?php elseif ($r['detalle'] !== ''): ?>
                                    <span class="match__detail"><?= e($r['detalle']) ?></span>
                                <?php endif; ?>
                            <?php elseif ($esProximo): ?>
                                <span class="badge badge--blue badge--dot">Próximo</span>
                            <?php else: ?>
                                <span class="badge badge--gray">Pendiente</span>
                            <?php endif; ?>
                        </span>
                        <span class="match__side match__side--b<?= $ganaB ? ' winner' : '' ?>">
                            <?php if ($ganaB): ?><?= icon('check-circle', 'icon--sm') ?><span class="sr-only">Ganador:</span><?php endif; ?>
                            <span class="match__name"><?= e($nombreB) ?></span>
                        </span>
                        <?php if ($puedeAnotar): ?>
                            <span class="match__action no-print">
                                <a class="btn <?= $fin ? 'btn--ghost' : 'btn--primary' ?>" href="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $p['id']) ?>"
                                   aria-label="<?= $fin ? 'Editar' : 'Cargar' ?> resultado de <?= e($nombreA) ?> contra <?= e($nombreB) ?>">
                                    <?= icon($fin ? 'edit' : 'plus') ?> <?= $fin ? 'Editar' : 'Cargar' ?>
                                </a>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
