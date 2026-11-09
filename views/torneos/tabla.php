<?php
/** @var array<string,mixed> $torneo */
/** @var list<array<string,mixed>> $tabla */
/** @var array{total:int,finalizados:int} $progreso */
/** @var int $clasificados */

use PingPong\Domain\EstadoTorneo;

$tid = (string) $torneo['id'];
$ligaCompleta = $progreso['total'] > 0 && $progreso['finalizados'] === $progreso['total'];
$fmtRatio = static fn (?float $r): string => $r === null ? '—' : number_format($r, 2);
$pct = $progreso['total'] > 0 ? (int) round(($progreso['finalizados'] / $progreso['total']) * 100) : 0;
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$enLiga = $torneo['estado'] === EstadoTorneo::FASE_LIGA->value;

// Tournament sub-navigation (same tabs as the hub).
$estado = (string) $torneo['estado'];
$conLiga = !in_array($estado, [EstadoTorneo::BORRADOR->value, EstadoTorneo::INSCRIPCION_ABIERTA->value, EstadoTorneo::INSCRIPCION_CERRADA->value], true);
$conBracket = $conLiga && $estado !== EstadoTorneo::FASE_LIGA->value;
$tabs = [
    ['href' => "/torneos/{$tid}", 'label' => 'Resumen', 'icon' => 'home', 'ok' => true, 'actual' => false],
    ['href' => "/torneos/{$tid}/partidos", 'label' => 'Partidos', 'icon' => 'paddle', 'ok' => $conLiga, 'actual' => false],
    ['href' => "/torneos/{$tid}/tabla", 'label' => 'Tabla', 'icon' => 'table', 'ok' => $conLiga, 'actual' => true],
    ['href' => "/torneos/{$tid}/bracket", 'label' => 'Llaves', 'icon' => 'bracket', 'ok' => $conBracket, 'actual' => false],
    ['href' => "/torneos/{$tid}/podio", 'label' => 'Podio', 'icon' => 'podium', 'ok' => $conBracket, 'actual' => false],
];
?>
<nav aria-label="Ruta" class="no-print">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><a href="/torneos/<?= e($tid) ?>"><?= e($torneo['nombre']) ?></a></li>
        <li><span aria-current="page">Tabla</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= icon('table') ?> Fase de liga</span>
        <h1>Tabla de posiciones</h1>
        <p><?= e($torneo['nombre']) ?> · <?= e((string) $progreso['finalizados']) ?> de <?= e((string) $progreso['total']) ?> partidos jugados. Clasifican los primeros <?= e((string) $clasificados) ?> al cuadro eliminatorio.</p>
    </div>
    <div class="actions no-print">
        <a class="btn btn--secondary" href="/torneos/<?= e($tid) ?>/tabla.csv"><?= icon('spreadsheet') ?> CSV</a>
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

<?php if ($ligaCompleta && $enLiga): ?>
    <div class="flash flash--success no-print">
        <?= icon('check-circle') ?>
        <div class="flash__body">
            <strong>Liga completa.</strong> Los primeros <?= e((string) $clasificados) ?> pasan al cuadro eliminatorio.
        </div>
        <?php if ($puedeGestionar): ?>
            <form method="post" action="/torneos/<?= e($tid) ?>/bracket" class="inline"
                  data-confirm="Se promueve a los <?= e((string) $clasificados) ?> mejores de la tabla y se genera el cuadro de eliminación directa."
                  data-confirm-title="¿Generar las llaves?" data-confirm-ok="Generar llaves" data-confirm-tone="info">
                <?= csrf_field() ?>
                <button class="btn btn--primary" type="submit"><?= icon('bracket') ?> Promover y generar llaves</button>
            </form>
        <?php endif; ?>
    </div>
<?php elseif ($ligaCompleta): ?>
    <div class="flash flash--info no-print">
        <?= icon('info') ?>
        <div class="flash__body">El cuadro eliminatorio ya fue generado. <a href="/torneos/<?= e($tid) ?>/bracket">Ver las llaves</a></div>
    </div>
<?php elseif ($progreso['total'] > 0): ?>
    <div class="card standings-progress no-print">
        <div class="spread">
            <span id="avance-liga" class="strong">Avance de la liga</span>
            <span class="muted small"><?= e((string) $pct) ?>%</span>
        </div>
        <div class="progress" role="progressbar" aria-labelledby="avance-liga" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) $pct) ?>"><span style="--value: <?= e((string) $pct) ?>%"></span></div>
    </div>
<?php endif; ?>

<?php if ($tabla === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('users') ?></span>
        <p class="empty-state__title">La tabla está vacía</p>
        <p>Cuando haya jugadores inscriptos y partidos de liga generados, las posiciones aparecen acá.</p>
        <div class="actions no-print">
            <a class="btn btn--secondary" href="/torneos/<?= e($tid) ?>"><?= icon('arrow-left') ?> Ir al resumen</a>
        </div>
    </div>
<?php else: ?>
    <section class="standings-card" aria-labelledby="tabla-title">
        <h2 id="tabla-title" class="sr-only">Posiciones</h2>
        <div class="table-wrap">
            <table class="table table--stack table--standings">
                <thead>
                    <tr>
                        <th scope="col">Jugador</th>
                        <th scope="col" class="num"><abbr title="Partidos jugados">PJ</abbr></th>
                        <th scope="col" class="num"><abbr title="Partidos ganados">PG</abbr></th>
                        <th scope="col" class="num"><abbr title="Partidos perdidos">PP</abbr></th>
                        <th scope="col" class="num"><abbr title="Puntos de clasificación">Pts</abbr></th>
                        <th scope="col" class="num"><abbr title="Sets a favor - en contra">Sets</abbr></th>
                        <th scope="col" class="num"><abbr title="Ratio de sets">R. sets</abbr></th>
                        <th scope="col" class="num"><abbr title="Puntos a favor - en contra">Puntos</abbr></th>
                        <th scope="col" class="num"><abbr title="Ratio de puntos">R. pts</abbr></th>
                        <th scope="col">Desempate</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tabla as $fila): ?>
                    <?php
                    $pos = (int) $fila['posicion'];
                    $clasifica = $pos <= $clasificados;
                    $clases = trim(($clasifica ? 'row--clasifica' : '') . ($pos === $clasificados ? ' row--cutoff' : ''));
                    ?>
                    <tr<?= $clases !== '' ? ' class="' . e($clases) . '"' : '' ?>>
                        <td class="cell--primary">
                            <span class="standings__player">
                                <span class="standings__pos"><span class="sr-only">Posición </span><?= e((string) $pos) ?></span>
                                <span class="standings__name"><?= e($fila['nombre']) ?></span>
                                <?php if ($clasifica): ?>
                                    <span class="badge badge--green standings__tag"><?= icon('check', 'icon--sm') ?> Clasifica</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="num" data-label="Jugados"><?= e((string) $fila['pj']) ?></td>
                        <td class="num" data-label="Ganados"><?= e((string) $fila['pg']) ?></td>
                        <td class="num" data-label="Perdidos"><?= e((string) $fila['pp']) ?></td>
                        <td class="num standings__pts" data-label="Puntos de clasificación"><strong><?= e((string) $fila['puntos']) ?></strong></td>
                        <td class="num" data-label="Sets (favor-contra)"><?= e((string) $fila['sf']) ?>-<?= e((string) $fila['sc']) ?></td>
                        <td class="num" data-label="Ratio de sets"><?= e($fmtRatio($fila['set_ratio'])) ?></td>
                        <td class="num" data-label="Puntos (favor-contra)"><?= e((string) $fila['pf']) ?>-<?= e((string) $fila['pc']) ?></td>
                        <td class="num" data-label="Ratio de puntos"><?= e($fmtRatio($fila['punto_ratio'])) ?></td>
                        <td data-label="Desempate"><?php if (!empty($fila['desempate'])): ?><span class="badge badge--blue"><?= e($fila['desempate']) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <div class="standings-legend small muted">
        <p><span class="swatch swatch--clasifica" aria-hidden="true"></span> <span class="badge badge--green"><?= icon('check', 'icon--sm') ?> Clasifica</span> marca a los <?= e((string) $clasificados) ?> jugadores que pasan al cuadro eliminatorio; la línea punteada es el corte.</p>
        <p>«Desempate» indica el criterio que definió la posición frente al jugador anterior. <a href="/glosario">¿Qué significan los desempates?</a></p>
    </div>
<?php endif; ?>
