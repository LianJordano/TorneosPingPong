<?php

use PingPong\Domain\Rol;

/** @var list<array<string,mixed>> $usuarios */
/** @var list<Rol> $roles */
/** @var int $usuarioActualId */
$rolIconos = ['ADMIN' => 'shield', 'ANOTADOR' => 'scoreboard', 'ESPECTADOR' => 'eye'];
?>
<nav aria-label="Ruta"><ol class="breadcrumbs">
    <li><a href="/configuracion">Configuración</a></li>
    <li><span aria-current="page">Usuarios y roles</span></li>
</ol></nav>
<header class="page-head">
    <div>
        <span class="eyebrow">Administración</span>
        <h1>Usuarios y roles</h1>
        <p>Asigná a cada usuario el rol que define qué puede hacer en la plataforma.</p>
    </div>
</header>

<section class="card card--flush" aria-labelledby="usuarios-titulo">
    <div class="card__header">
        <h2 class="card__title" id="usuarios-titulo">Usuarios registrados</h2>
        <span class="badge badge--blue"><?= e((string) count($usuarios)) ?> <?= count($usuarios) === 1 ? 'usuario' : 'usuarios' ?></span>
    </div>

    <?php if ($usuarios === []): ?>
        <div class="card-pad">
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('users') ?></span>
                <p class="empty-state__title">No hay usuarios registrados</p>
                <p>Generá el administrador con el script <code>crear_admin.php</code>.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Rol</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <?php $selectId = 'rol-usuario-' . (string) $u['id']; ?>
                    <tr>
                        <td class="cell--primary">
                            <span class="user-cell">
                                <span class="avatar" aria-hidden="true"><?= e(initials((string) $u['nombre'])) ?></span>
                                <span class="user-cell__text">
                                    <span class="table__title"><?= e((string) $u['nombre']) ?></span>
                                    <?php if ((int) $u['id'] === $usuarioActualId): ?>
                                        <span class="badge badge--gray">Tú</span>
                                    <?php endif; ?>
                                    <span class="table__sub truncate"><?= e((string) $u['email']) ?></span>
                                </span>
                            </span>
                        </td>
                        <td data-label="Estado">
                            <?php if ((int) $u['activo'] === 1): ?>
                                <span class="badge badge--dot badge--green">Activo</span>
                            <?php else: ?>
                                <span class="badge badge--dot badge--gray">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Rol">
                            <form method="post" action="/configuracion/usuarios/<?= e((string) $u['id']) ?>/rol" class="inline-form rol-form">
                                <?= csrf_field() ?>
                                <label class="sr-only" for="<?= e($selectId) ?>">Rol de <?= e((string) $u['nombre']) ?></label>
                                <select id="<?= e($selectId) ?>" name="rol">
                                    <?php foreach ($roles as $opcion): ?>
                                        <option value="<?= e($opcion->value) ?>" <?= $u['rol'] === $opcion->value ? 'selected' : '' ?>>
                                            <?= e($opcion->label()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn--secondary">Guardar<span class="sr-only"> rol de <?= e((string) $u['nombre']) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="section" aria-labelledby="roles-titulo">
    <div class="section-head">
        <div>
            <h2 id="roles-titulo">¿Qué hace cada rol?</h2>
            <p>Los permisos se aplican apenas guardás el cambio.</p>
        </div>
    </div>

    <div class="grid-3">
        <?php foreach ($roles as $opcion): ?>
            <article class="card role-card">
                <div class="role-card__head">
                    <span class="role-card__icon"><?= icon($rolIconos[$opcion->value] ?? 'user') ?></span>
                    <h3 class="card__title"><?= e($opcion->label()) ?></h3>
                </div>
                <p class="muted small"><?= e($opcion->descripcion()) ?></p>
                <ul class="role-card__perms">
                    <?php if ($opcion->puedeGestionar()): ?>
                        <li><?= icon('settings', 'icon--sm') ?> Gestiona torneos y usuarios</li>
                    <?php endif; ?>
                    <?php if ($opcion->puedeAnotar()): ?>
                        <li><?= icon('edit', 'icon--sm') ?> Carga marcadores</li>
                    <?php endif; ?>
                    <?php if (!$opcion->puedeAnotar() && !$opcion->puedeGestionar()): ?>
                        <li><?= icon('eye', 'icon--sm') ?> Solo lectura</li>
                    <?php endif; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>
</section>
