<?php
/** @var array<string,mixed> $torneo */
/** @var list<array<string,mixed>> $podio */
/** @var float $pozo */
/** @var bool $finalizado */

use PingPong\Domain\EstadoTorneo;

$tid = (string) $torneo['id'];
$top3 = array_slice($podio, 0, 3);
$premiados = count(array_filter($podio, static fn (array $p): bool => (bool) $p['premiado']));
$puesto = [1 => 'Campeón', 2 => 'Subcampeón', 3 => 'Tercer puesto'];
$medalla = [1 => 'Medalla de oro', 2 => 'Medalla de plata', 3 => 'Medalla de bronce'];
$fmtMonto = static fn ($m): string => '$' . number_format((float) $m, 2);

$estado = (string) $torneo['estado'];
$conLiga = !in_array($estado, [EstadoTorneo::BORRADOR->value, EstadoTorneo::INSCRIPCION_ABIERTA->value, EstadoTorneo::INSCRIPCION_CERRADA->value], true);
$conBracket = $conLiga && $estado !== EstadoTorneo::FASE_LIGA->value;
?>
<nav aria-label="Ruta" class="no-print">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><a href="/torneos/<?= e($tid) ?>"><?= e($torneo['nombre']) ?></a></li>
        <li><span aria-current="page">Podio</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= icon('podium') ?> Podio</span>
        <h1>Podio de <?= e($torneo['nombre']) ?></h1>
        <div class="meta-row">
            <span class="meta-pill"><?= icon('dollar') ?> Pozo <?= e($fmtMonto($pozo)) ?></span>
            <?php if ($finalizado): ?>
                <span class="badge badge--green badge--dot">Resultado oficial</span>
            <?php else: ?>
                <span class="badge badge--amber badge--dot">Resultado provisional</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($podio !== []): ?>
        <div class="actions no-print">
            <button class="btn btn--secondary" type="button" data-print><?= icon('printer') ?> Imprimir / PDF</button>
        </div>
    <?php endif; ?>
</header>

<?php $tabActual = 'podio'; require __DIR__ . '/../partials/torneo-tabs.php'; ?>

<?php if (!$finalizado && $podio !== []): ?>
    <div class="flash flash--warning no-print">
        <?= icon('clock') ?>
        <div class="flash__body">El torneo no está finalizado: este podio es <strong>provisional</strong> hasta que se confirme la final.</div>
    </div>
<?php endif; ?>

<?php if ($podio === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('podium') ?></span>
        <p class="empty-state__title">El podio todavía está vacío</p>
        <p>Las posiciones aparecen cuando el cuadro eliminatorio tenga resultados cargados.</p>
        <?php if ($conBracket): ?>
            <div class="actions no-print">
                <a class="btn btn--secondary" href="/torneos/<?= e($tid) ?>/bracket"><?= icon('bracket') ?> Ver las llaves</a>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if (isset($top3[0])): ?>
        <section class="winner-spotlight" aria-labelledby="campeon-title">
            <div class="winner-spotlight__icon"><?= icon('trophy') ?></div>
            <div class="winner-spotlight__label"><?= $finalizado ? 'Campeón del torneo' : 'Campeón provisional' ?></div>
            <h2 class="winner-spotlight__name" id="campeon-title"><?= e($top3[0]['nombre']) ?></h2>
            <p><?= $finalizado ? 'Consagrado oficialmente como campeón de la mesa.' : 'Lidera el cuadro de forma provisional hasta el cierre oficial.' ?></p>
        </section>
    <?php endif; ?>

    <ol class="podio" aria-label="Podio">
        <?php foreach ($top3 as $p): ?>
            <?php $pos = (int) $p['posicion']; ?>
            <li class="podio__card podio__card--<?= e((string) $pos) ?>">
                <div class="podio__medal"><?= icon($pos === 1 ? 'trophy' : 'medal', '', $medalla[$pos] ?? null) ?></div>
                <div class="podio__pos"><?= e((string) $pos) ?>.º puesto</div>
                <?php if (isset($puesto[$pos])): ?><div class="podio__rank-name"><?= e($puesto[$pos]) ?></div><?php endif; ?>
                <div class="podio__name"><?= e($p['nombre']) ?></div>
                <?php if ($p['monto'] !== null): ?>
                    <div class="podio__prize"><?= e($fmtMonto($p['monto'])) ?>
                        <?php if ($p['porcentaje'] !== null): ?><small>(<?= e(rtrim(rtrim(number_format((float) $p['porcentaje'], 2), '0'), '.')) ?>%)</small><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="podio__step" aria-hidden="true"><?= e((string) $pos) ?></div>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="stats stats--compact podio-stats">
        <div class="stat">
            <span class="stat__label"><?= icon('gift') ?> Premiados</span>
            <span class="stat__num"><?= e((string) $premiados) ?></span>
            <span class="stat__hint">Posiciones con premio configurado</span>
        </div>
        <div class="stat">
            <span class="stat__label"><?= icon('dollar') ?> Pozo</span>
            <span class="stat__num"><?= e($fmtMonto($pozo)) ?></span>
            <span class="stat__hint">Total a distribuir</span>
        </div>
    </div>

    <section class="section" aria-labelledby="clasificacion-title">
        <div class="section-head">
            <h2 id="clasificacion-title">Clasificación final del cuadro</h2>
        </div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr>
                        <th scope="col">Jugador</th>
                        <th scope="col" class="num">Premio</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($podio as $p): ?>
                    <tr<?= $p['premiado'] ? ' class="row--clasifica"' : '' ?>>
                        <td class="cell--primary">
                            <span class="standings__player">
                                <span class="standings__pos"><span class="sr-only">Posición </span><?= e((string) $p['posicion']) ?></span>
                                <span class="standings__name"><?= e($p['nombre']) ?></span>
                                <?php if ($p['premiado']): ?><span class="badge badge--green standings__tag"><?= icon('gift', 'icon--sm') ?> Premiado</span><?php endif; ?>
                            </span>
                        </td>
                        <td class="num" data-label="Premio"><?= $p['monto'] !== null ? e($fmtMonto($p['monto'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
