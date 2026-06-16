<?php
/** @var string $titulo */
/** @var string $singular */
/** @var string $ruta */
/** @var list<array<string,mixed>> $items */
/** @var string $error */
/** @var string $oldNombre */
$error = $error ?? '';
$oldNombre = $oldNombre ?? '';
?>
<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Catalog management</span>
            <h1><?= e($titulo) ?> <span class="badge badge--gray"><?= e((string) count($items)) ?></span></h1>
            <p class="muted">Ordená mejor la taxonomía del torneo con un CRUD mucho más prolijo, claro y potente.</p>
            <div class="meta-row">
                <span class="meta-pill">📚 <?= e((string) count($items)) ?> registros</span>
                <span class="meta-pill">👥 Gestión aplicada a jugadores</span>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-card">
                <span class="hero-card__label">Tipo de catálogo</span>
                <strong><?= e($singular) ?></strong>
                <small>Alta, edición y limpieza del catálogo sin salir del flujo operativo.</small>
            </div>
            <div class="actions"><a class="btn btn--ghost btn--sm" href="/configuracion">Volver a configuración</a></div>
        </div>
    </div>
</section>

<div class="catalog-grid">
    <section class="card">
        <h2>Agregar <?= e($singular) ?></h2>
        <p class="muted">Creá nuevas opciones para mantener prolijo el catálogo operativo.</p>
        <?php if ($error !== ''): ?>
            <div class="flash flash--error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e($ruta) ?>" class="inline-form catalog-form">
            <?= csrf_field() ?>
            <input type="text" name="nombre" value="<?= e($oldNombre) ?>" placeholder="Nombre del <?= e($singular) ?>" required>
            <button type="submit" class="btn btn--primary btn--sm">Agregar</button>
        </form>
    </section>

    <section class="card">
        <h2>Listado editable</h2>
        <?php if ($items === []): ?>
            <p class="empty-state">Todavía no hay <?= e(mb_strtolower($titulo)) ?>.</p>
        <?php else: ?>
            <div class="catalog-list">
            <?php foreach ($items as $item): ?>
                <article class="catalog-item">
                    <div>
                        <div class="catalog-item__meta">
                            <span class="catalog-item__title"><?= e($item['nombre']) ?></span>
                            <span class="badge badge--gray"><?= e((string) $item['uso']) ?> jugador<?= (int) $item['uso'] === 1 ? '' : 'es' ?></span>
                        </div>
                        <form method="post" action="<?= e($ruta) ?>/<?= e((string) $item['id']) ?>" class="inline-form catalog-form">
                            <?= csrf_field() ?>
                            <input type="text" name="nombre" value="<?= e($item['nombre']) ?>" required>
                            <button type="submit" class="btn btn--ghost btn--sm">Guardar</button>
                        </form>
                    </div>
                    <div class="text-right">
                        <form method="post" action="<?= e($ruta) ?>/<?= e((string) $item['id']) ?>/eliminar" class="inline"
                              onsubmit="return confirm('¿Eliminar este <?= e($singular) ?>? Los jugadores que lo tengan quedarán sin asignar.');">
                            <?= csrf_field() ?>
                            <button class="link link--danger" type="submit">Eliminar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
