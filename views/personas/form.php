<?php
/** @var array<string,mixed>|null $persona */
/** @var string $action */
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
/** @var list<array<string,mixed>> $areas */
/** @var list<array<string,mixed>> $niveles */
$errors = $errors ?? [];
$old = $old ?? [];
$areas = $areas ?? [];
$niveles = $niveles ?? [];
$val = static fn (string $k): string => isset($old[$k])
    ? e((string) $old[$k])
    : e((string) ($persona[$k] ?? ''));
// Valor seleccionado (sin escapar) para comparar en los <select>.
$sel = static fn (string $k): string => isset($old[$k])
    ? (string) $old[$k]
    : (string) ($persona[$k] ?? '');
?>
<section class="page-head">
    <h1><?= $persona ? 'Editar jugador' : 'Nuevo jugador' ?></h1>
    <a class="btn btn--ghost btn--sm" href="/personas">Volver</a>
</section>

<section class="card">
    <form method="post" action="<?= e($action) ?>" novalidate class="form-grid">
        <?= csrf_field() ?>

        <label class="field">
            <span>Nombre *</span>
            <input type="text" name="nombre" value="<?= $val('nombre') ?>" required>
            <?php if (isset($errors['nombre'])): ?><em class="field__error"><?= e($errors['nombre']) ?></em><?php endif; ?>
        </label>

        <label class="field">
            <span>Apellido *</span>
            <input type="text" name="apellido" value="<?= $val('apellido') ?>" required>
            <?php if (isset($errors['apellido'])): ?><em class="field__error"><?= e($errors['apellido']) ?></em><?php endif; ?>
        </label>

        <label class="field">
            <span>Identificador / legajo</span>
            <input type="text" name="identificador_interno" value="<?= $val('identificador_interno') ?>">
            <?php if (isset($errors['identificador_interno'])): ?><em class="field__error"><?= e($errors['identificador_interno']) ?></em><?php endif; ?>
        </label>

        <label class="field">
            <span>Área / departamento</span>
            <select name="area_id">
                <option value="">— Sin asignar —</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= e((string) $a['id']) ?>" <?= $sel('area_id') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['area_id'])): ?><em class="field__error"><?= e($errors['area_id']) ?></em><?php endif; ?>
            <small class="muted">¿Falta un área? Agregala en <a href="/areas">Áreas</a>.</small>
        </label>

        <label class="field">
            <span>Correo</span>
            <input type="email" name="email" value="<?= $val('email') ?>">
            <?php if (isset($errors['email'])): ?><em class="field__error"><?= e($errors['email']) ?></em><?php endif; ?>
        </label>

        <label class="field">
            <span>Teléfono</span>
            <input type="text" name="telefono" value="<?= $val('telefono') ?>">
        </label>

        <label class="field">
            <span>Nivel / categoría</span>
            <select name="nivel_id">
                <option value="">— Sin asignar —</option>
                <?php foreach ($niveles as $n): ?>
                    <option value="<?= e((string) $n['id']) ?>" <?= $sel('nivel_id') === (string) $n['id'] ? 'selected' : '' ?>><?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['nivel_id'])): ?><em class="field__error"><?= e($errors['nivel_id']) ?></em><?php endif; ?>
            <small class="muted">¿Falta un nivel? Agregalo en <a href="/niveles">Niveles</a>.</small>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $persona ? 'Guardar cambios' : 'Crear jugador' ?></button>
            <a class="btn btn--ghost" href="/personas">Cancelar</a>
        </div>
    </form>
</section>
