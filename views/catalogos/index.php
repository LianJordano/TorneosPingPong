<?php
/** @var string $titulo */
/** @var string $singular */
/** @var string $ruta */
/** @var list<array<string,mixed>> $items */
/** @var string $error */
/** @var string $oldNombre */
$error = $error ?? '';
$oldNombre = $oldNombre ?? '';
$total = count($items);
?>
<nav aria-label="Ruta"><ol class="breadcrumbs">
    <li><a href="/configuracion">Configuración</a></li>
    <li><span aria-current="page"><?= e($titulo) ?></span></li>
</ol></nav>
<header class="page-head">
    <div>
        <span class="eyebrow">Catálogo</span>
        <h1><?= e($titulo) ?></h1>
        <p>Opciones disponibles para clasificar a los jugadores. Eliminar una deja sin asignar a quienes la tengan.</p>
    </div>
</header>

<div class="catalog-layout">
    <section class="card" aria-labelledby="catalogo-nuevo">
        <h2 class="card__title" id="catalogo-nuevo">Agregar <?= e($singular) ?></h2>
        <form method="post" action="<?= e($ruta) ?>" class="mt-4" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="catalogo-nombre">Nombre <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="catalogo-nombre" name="nombre" value="<?= e($oldNombre) ?>" maxlength="100" required
                       <?= $error !== '' ? 'aria-invalid="true" aria-describedby="catalogo-nombre-error"' : '' ?>>
                <?php if ($error !== ''): ?>
                    <em class="field__error" id="catalogo-nombre-error"><?= icon('alert', 'icon--sm') ?> <?= e($error) ?></em>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn--primary btn--block"><?= icon('plus') ?> Agregar</button>
        </form>
    </section>

    <section class="card card--flush" aria-labelledby="catalogo-listado">
        <div class="card__header">
            <h2 class="card__title" id="catalogo-listado">Listado</h2>
            <span class="badge badge--gray"><?= e((string) $total) ?> <?= $total === 1 ? 'registro' : 'registros' ?></span>
        </div>
        <?php if ($items === []): ?>
            <div class="card-pad">
                <div class="empty-state">
                    <span class="empty-state__icon"><?= icon('tag') ?></span>
                    <p class="empty-state__title">Todavía no hay <?= e(mb_strtolower($titulo)) ?></p>
                    <p>Usá el formulario para crear el primer registro.</p>
                </div>
            </div>
        <?php else: ?>
            <ul class="list catalog-list">
            <?php foreach ($items as $item): ?>
                <?php
                $id = (string) $item['id'];
                $uso = (int) $item['uso'];
                $inputId = 'catalogo-item-' . $id;
                ?>
                <li class="list__item catalog-item">
                    <form method="post" action="<?= e($ruta) ?>/<?= e($id) ?>" class="catalog-item__edit">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="<?= e($inputId) ?>">Nombre de <?= e($item['nombre']) ?></label>
                        <input type="text" class="input" id="<?= e($inputId) ?>" name="nombre" value="<?= e($item['nombre']) ?>" maxlength="100" required>
                        <button type="submit" class="btn btn--secondary">Guardar<span class="sr-only"> <?= e($item['nombre']) ?></span></button>
                    </form>
                    <div class="catalog-item__side">
                        <span class="badge badge--gray"><?= icon('users', 'icon--sm') ?> <?= e((string) $uso) ?> <?= $uso === 1 ? 'jugador' : 'jugadores' ?></span>
                        <form method="post" action="<?= e($ruta) ?>/<?= e($id) ?>/eliminar"
                              data-confirm="¿Eliminar «<?= e($item['nombre']) ?>»? <?= $uso > 0 ? e((string) $uso) . ($uso === 1 ? ' jugador quedará sin asignar.' : ' jugadores quedarán sin asignar.') : 'No hay jugadores asignados.' ?>"
                              data-confirm-title="Eliminar <?= e($singular) ?>"
                              data-confirm-ok="Eliminar">
                            <?= csrf_field() ?>
                            <button class="btn btn--danger-soft btn--icon" type="submit" aria-label="Eliminar <?= e($item['nombre']) ?>" title="Eliminar"><?= icon('trash') ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
