<?php
/** @var list<array<string,mixed>> $torneos */
/** @var bool $incluirArchivados */

use PingPong\Domain\Emparejamiento;
use PingPong\Domain\EstadoTorneo;
use PingPong\Domain\Modalidad;

$puedeGestionar = $rol?->puedeGestionar() ?? false;
$fecha = static function (?string $valor): string {
    if ($valor === null || $valor === '') {
        return '';
    }
    $ts = strtotime($valor);
    return $ts === false ? $valor : date('d/m/Y', $ts);
};
$total = count($torneos);
?>
<header class="page-head">
    <div>
        <span class="eyebrow">Competencia</span>
        <h1>Torneos</h1>
        <p>Elegí el torneo que querés operar, revisá su estado y seguí la competencia desde el celular o la pantalla compartida.</p>
    </div>
    <?php if ($puedeGestionar): ?>
        <div class="actions">
            <a class="btn btn--primary" href="/torneos/nuevo"><?= icon('plus') ?> Nuevo torneo</a>
        </div>
    <?php endif; ?>
</header>

<div class="toolbar torneos-toolbar">
    <nav class="tabs torneos-filter" aria-label="Filtrar torneos">
        <a class="tabs__link" href="/torneos"<?= $incluirArchivados ? '' : ' aria-current="page"' ?>>Activos</a>
        <a class="tabs__link" href="/torneos?archivados=1"<?= $incluirArchivados ? ' aria-current="page"' : '' ?>><?= icon('archive') ?> Incluir archivados</a>
    </nav>
    <p class="muted small torneos-count">
        <?= e((string) $total) ?> <?= $total === 1 ? 'torneo' : 'torneos' ?><?= $incluirArchivados ? ', archivados incluidos' : '' ?>
    </p>
</div>

<?php if ($torneos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('trophy') ?></span>
        <p class="empty-state__title">No hay torneos para mostrar</p>
        <p><?= $incluirArchivados ? 'Todavía no se creó ningún torneo.' : 'No hay torneos activos. Probá incluir los archivados o creá uno nuevo.' ?></p>
        <?php if ($puedeGestionar): ?>
            <div class="actions"><a class="btn btn--primary" href="/torneos/nuevo"><?= icon('plus') ?> Nuevo torneo</a></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <section class="card card--flush">
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Listado de torneos</caption>
                <thead>
                    <tr>
                        <th scope="col">Torneo</th>
                        <th scope="col">Modalidad</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="num">Inscritos</th>
                        <th scope="col">Fechas</th>
                        <th scope="col"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($torneos as $t): ?>
                    <?php
                    $estado = EstadoTorneo::from($t['estado']);
                    $esDobles = Modalidad::from($t['modalidad'])->esDobles();
                    $archivado = (int) ($t['archivado'] ?? 0) === 1;
                    $inicio = $fecha($t['fecha_inicio'] ?? null);
                    $fin = $fecha($t['fecha_fin'] ?? null);
                    $url = '/torneos/' . (string) $t['id'];
                    ?>
                    <tr<?= $archivado ? ' class="row--muted"' : '' ?>>
                        <td class="cell--primary">
                            <a href="<?= e($url) ?>"><?= e($t['nombre']) ?></a>
                            <?php if ($archivado): ?>
                                <span class="badge badge--gray"><?= icon('archive') ?> Archivado</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Modalidad">
                            <span class="torneos-modalidad">
                                <?= icon($esDobles ? 'users' : 'user') ?>
                                <span>
                                    <?= $esDobles ? 'Dobles' : 'Individual' ?>
                                    <?php if ($esDobles): ?>
                                        <span class="table__sub"><?= Emparejamiento::from($t['emparejamiento'])->esAleatorio() ? 'Parejas por sorteo' : 'Parejas manuales' ?></span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </td>
                        <td data-label="Estado"><span class="badge badge--dot <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></td>
                        <td data-label="Inscritos" class="num"><?= e((string) ($t['inscritos'] ?? 0)) ?></td>
                        <td data-label="Fechas" class="nowrap">
                            <?php if ($inicio === '' && $fin === ''): ?>
                                <span class="muted">Sin fecha</span>
                            <?php else: ?>
                                <?= e($inicio !== '' ? $inicio : '—') ?><?= $fin !== '' ? ' – ' . e($fin) : '' ?>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a class="btn btn--ghost btn--sm" href="<?= e($url) ?>">
                                Abrir<span class="sr-only"> <?= e($t['nombre']) ?></span> <?= icon('chevron-right') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
