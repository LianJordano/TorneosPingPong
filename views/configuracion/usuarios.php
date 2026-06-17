<?php

use PingPong\Domain\Rol;

/** @var list<array<string,mixed>> $usuarios */
/** @var list<Rol> $roles */
/** @var int $usuarioActualId */
?>
<section class="page-head">
    <div>
        <span class="eyebrow">Administración</span>
        <h1>Usuarios y roles</h1>
        <p class="muted">Asigná a cada usuario el rol que define qué puede hacer en la plataforma.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="/configuracion">Volver a Configuración</a>
</section>

<section class="card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Asignación</span>
            <h2>Usuarios registrados</h2>
        </div>
        <span class="badge badge--blue"><?= e((string) count($usuarios)) ?> usuarios</span>
    </div>

    <?php if ($usuarios === []): ?>
        <p class="empty-state">No hay usuarios registrados. Generá el administrador con el script <code>crear_admin.php</code>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Rol</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $u['nombre']) ?></strong>
                            <?php if ((int) $u['id'] === $usuarioActualId): ?>
                                <span class="badge badge--gray">Tú</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) $u['email']) ?></td>
                        <td>
                            <?php if ((int) $u['activo'] === 1): ?>
                                <span class="badge badge--green">Activo</span>
                            <?php else: ?>
                                <span class="badge badge--gray">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="/configuracion/usuarios/<?= e((string) $u['id']) ?>/rol" class="rol-form">
                                <?= csrf_field() ?>
                                <select name="rol">
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?= e($rol->value) ?>" <?= $u['rol'] === $rol->value ? 'selected' : '' ?>>
                                            <?= e($rol->label()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn--primary btn--sm">Guardar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Glosario</span>
            <h2>¿Qué hace cada rol?</h2>
        </div>
    </div>

    <div class="rol-glosario">
        <?php foreach ($roles as $rol): ?>
            <article class="rol-glosario__item">
                <h3><?= e($rol->label()) ?> <span class="badge badge--blue"><?= e($rol->value) ?></span></h3>
                <p class="muted"><?= e($rol->descripcion()) ?></p>
                <div class="meta-row">
                    <?php if ($rol->puedeGestionar()): ?>
                        <span class="meta-pill meta-pill--light">⚙️ Gestiona torneos y usuarios</span>
                    <?php endif; ?>
                    <?php if ($rol->puedeAnotar()): ?>
                        <span class="meta-pill meta-pill--light">📝 Carga marcadores</span>
                    <?php endif; ?>
                    <?php if (!$rol->puedeAnotar() && !$rol->puedeGestionar()): ?>
                        <span class="meta-pill meta-pill--light">👁️ Solo lectura</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
