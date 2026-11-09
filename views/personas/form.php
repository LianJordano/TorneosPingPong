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
// Selected value (unescaped) to compare inside <select>.
$sel = static fn (string $k): string => isset($old[$k])
    ? (string) $old[$k]
    : (string) ($persona[$k] ?? '');

/** a11y attributes for a field: aria-invalid + aria-describedby (hint and/or error). */
$aria = static function (string $k, ?string $hintId = null) use ($errors): string {
    $ids = [];
    if ($hintId !== null) { $ids[] = $hintId; }
    $attrs = '';
    if (isset($errors[$k])) {
        $ids[] = 'err-' . $k;
        $attrs .= ' aria-invalid="true"';
    }
    if ($ids !== []) {
        $attrs .= ' aria-describedby="' . e(implode(' ', $ids)) . '"';
    }
    return $attrs;
};
$error = static fn (string $k): string => isset($errors[$k])
    ? '<p class="field__error" id="err-' . e($k) . '">' . icon('alert') . ' ' . e($errors[$k]) . '</p>'
    : '';

$esEdicion = $persona !== null;
$nombreActual = $esEdicion ? trim(($persona['apellido'] ?? '') . ', ' . ($persona['nombre'] ?? ''), ', ') : '';
$volver = $esEdicion && isset($persona['id']) ? '/personas/' . (string) $persona['id'] : '/personas';
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/personas">Jugadores</a></li>
        <?php if ($esEdicion && isset($persona['id'])): ?>
            <li><a href="/personas/<?= e((string) $persona['id']) ?>"><?= e($nombreActual) ?></a></li>
        <?php endif; ?>
        <li><span aria-current="page"><?= $esEdicion ? 'Editar' : 'Nuevo jugador' ?></span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= icon($esEdicion ? 'edit' : 'user-plus') ?> Jugadores</span>
        <h1><?= $esEdicion ? 'Editar jugador' : 'Nuevo jugador' ?></h1>
        <p><?= $esEdicion
            ? 'Actualiza los datos de ' . e($nombreActual) . '. Los cambios se reflejan en torneos y ranking.'
            : 'Carga los datos básicos. Solo el nombre y el apellido son obligatorios.' ?></p>
    </div>
    <div class="actions">
        <a class="btn btn--ghost" href="<?= e($volver) ?>"><?= icon('arrow-left') ?> Volver</a>
    </div>
</header>

<section class="card form-panel players-form">
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert">
            <?= icon('alert') ?>
            <div class="flash__body">Revisa los campos marcados antes de guardar.</div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" novalidate>
        <?= csrf_field() ?>

        <fieldset class="fieldset">
            <legend>Datos personales</legend>
            <div class="form-grid">
                <div class="field<?= isset($errors['nombre']) ? ' field--error' : '' ?>">
                    <label for="persona-nombre">Nombre <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="persona-nombre" name="nombre" value="<?= $val('nombre') ?>" required autocomplete="given-name"<?= $aria('nombre') ?>>
                    <?= $error('nombre') ?>
                </div>

                <div class="field<?= isset($errors['apellido']) ? ' field--error' : '' ?>">
                    <label for="persona-apellido">Apellido <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="persona-apellido" name="apellido" value="<?= $val('apellido') ?>" required autocomplete="family-name"<?= $aria('apellido') ?>>
                    <?= $error('apellido') ?>
                </div>

                <div class="field<?= isset($errors['identificador_interno']) ? ' field--error' : '' ?>">
                    <label for="persona-identificador">Identificador / legajo</label>
                    <input type="text" id="persona-identificador" name="identificador_interno" value="<?= $val('identificador_interno') ?>"<?= $aria('identificador_interno', 'hint-identificador') ?>>
                    <p class="field__hint" id="hint-identificador">Opcional. Código interno para distinguir jugadores con el mismo nombre.</p>
                    <?= $error('identificador_interno') ?>
                </div>
            </div>
        </fieldset>

        <fieldset class="fieldset">
            <legend>Contacto</legend>
            <div class="form-grid">
                <div class="field<?= isset($errors['email']) ? ' field--error' : '' ?>">
                    <label for="persona-email">Correo</label>
                    <input type="email" id="persona-email" name="email" value="<?= $val('email') ?>" autocomplete="email" inputmode="email"<?= $aria('email') ?>>
                    <?= $error('email') ?>
                </div>

                <div class="field">
                    <label for="persona-telefono">Teléfono</label>
                    <input type="tel" id="persona-telefono" name="telefono" value="<?= $val('telefono') ?>" autocomplete="tel">
                </div>
            </div>
        </fieldset>

        <fieldset class="fieldset">
            <legend>Clasificación</legend>
            <div class="form-grid">
                <div class="field<?= isset($errors['area_id']) ? ' field--error' : '' ?>">
                    <label for="persona-area">Área / departamento</label>
                    <select id="persona-area" name="area_id"<?= $aria('area_id', 'hint-area') ?>>
                        <option value="">Sin asignar</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= e((string) $a['id']) ?>" <?= $sel('area_id') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__hint" id="hint-area">¿Falta un área? Agrégala en <a class="link" href="/areas">Áreas</a>.</p>
                    <?= $error('area_id') ?>
                </div>

                <div class="field<?= isset($errors['nivel_id']) ? ' field--error' : '' ?>">
                    <label for="persona-nivel">Nivel / categoría</label>
                    <select id="persona-nivel" name="nivel_id"<?= $aria('nivel_id', 'hint-nivel') ?>>
                        <option value="">Sin asignar</option>
                        <?php foreach ($niveles as $n): ?>
                            <option value="<?= e((string) $n['id']) ?>" <?= $sel('nivel_id') === (string) $n['id'] ? 'selected' : '' ?>><?= e($n['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__hint" id="hint-nivel">¿Falta un nivel? Agrégalo en <a class="link" href="/niveles">Niveles</a>.</p>
                    <?= $error('nivel_id') ?>
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= icon('check') ?> <?= $esEdicion ? 'Guardar cambios' : 'Crear jugador' ?></button>
            <a class="btn btn--ghost" href="<?= e($volver) ?>">Cancelar</a>
        </div>
    </form>
</section>
