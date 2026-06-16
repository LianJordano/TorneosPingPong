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
<section class="page-head">
    <h1><?= e($titulo) ?> <span class="badge badge--gray"><?= e((string) count($items)) ?></span></h1>
    <a class="btn btn--ghost btn--sm" href="/personas">Volver a jugadores</a>
</section>

<section class="card">
    <h2>Agregar <?= e($singular) ?></h2>
    <?php if ($error !== ''): ?>
        <div class="flash flash--error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e($ruta) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="text" name="nombre" value="<?= e($oldNombre) ?>" placeholder="Nombre del <?= e($singular) ?>" required>
        <button type="submit" class="btn btn--primary btn--sm">Agregar</button>
    </form>
</section>

<section class="card">
    <?php if ($items === []): ?>
        <p class="muted">Todavía no hay <?= e(mb_strtolower($titulo)) ?>.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Nombre</th><th>Jugadores</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <form method="post" action="<?= e($ruta) ?>/<?= e((string) $item['id']) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="text" name="nombre" value="<?= e($item['nombre']) ?>" required>
                            <button type="submit" class="btn btn--ghost btn--sm">Guardar</button>
                        </form>
                    </td>
                    <td><span class="badge badge--gray"><?= e((string) $item['uso']) ?></span></td>
                    <td class="text-right">
                        <form method="post" action="<?= e($ruta) ?>/<?= e((string) $item['id']) ?>/eliminar" class="inline"
                              onsubmit="return confirm('¿Eliminar este <?= e($singular) ?>? Los jugadores que lo tengan quedarán sin asignar.');">
                            <?= csrf_field() ?>
                            <button class="link link--danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
