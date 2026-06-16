<?php
/** @var list<array<string,mixed>> $torneos */
/** @var bool $incluirArchivados */

use PingPong\Domain\EstadoTorneo;

$puedeGestionar = $rol?->puedeGestionar() ?? false;
?>
<section class="page-head">
    <h1>Torneos</h1>
    <div class="actions">
        <a class="btn btn--ghost btn--sm" href="/torneos<?= $incluirArchivados ? '' : '?archivados=1' ?>">
            <?= $incluirArchivados ? 'Ocultar archivados' : 'Mostrar archivados' ?>
        </a>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="/torneos/nuevo">Nuevo torneo</a>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <?php if ($torneos === []): ?>
        <p class="muted">No hay torneos para mostrar.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Estado</th><th>Inscritos</th><th>Fechas</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($torneos as $t): ?>
                <?php $estado = EstadoTorneo::from($t['estado']); ?>
                <tr class="<?= (int) ($t['archivado'] ?? 0) === 1 ? 'row--muted' : '' ?>">
                    <td>
                        <a href="/torneos/<?= e((string) $t['id']) ?>"><strong><?= e($t['nombre']) ?></strong></a>
                        <?php if ((int) ($t['archivado'] ?? 0) === 1): ?>
                            <small class="badge badge--gray">archivado</small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></td>
                    <td><?= e((string) ($t['inscritos'] ?? 0)) ?></td>
                    <td><?= e($t['fecha_inicio'] ?? '—') ?> <?= !empty($t['fecha_fin']) ? '→ ' . e($t['fecha_fin']) : '' ?></td>
                    <td class="text-right"><a class="link" href="/torneos/<?= e((string) $t['id']) ?>">Ver →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
