<?php
/** @var array<string,string> $errors */
$errors = $errors ?? [];
$campos = [
    'password_actual' => [
        'label' => 'Contraseña actual',
        'autocomplete' => 'current-password',
        'hint' => null,
    ],
    'password_nueva' => [
        'label' => 'Nueva contraseña',
        'autocomplete' => 'new-password',
        'hint' => 'Mínimo 8 caracteres.',
    ],
    'password_confirmacion' => [
        'label' => 'Repetir nueva contraseña',
        'autocomplete' => 'new-password',
        'hint' => 'Escribila de nuevo para confirmar.',
    ],
];
$primero = true;
?>
<header class="page-head">
    <div>
        <span class="eyebrow">Mi cuenta</span>
        <h1>Cambiar contraseña</h1>
        <p>Necesitás tu contraseña actual para elegir una nueva.</p>
    </div>
</header>

<section class="card maxw-sm" aria-label="Formulario de cambio de contraseña">
    <form method="post" action="/cuenta/password" novalidate>
        <?= csrf_field() ?>

        <?php foreach ($campos as $name => $campo): ?>
            <?php
            $id = 'cuenta-' . str_replace('_', '-', $name);
            $tieneError = isset($errors[$name]);
            $describedBy = [];
            if ($campo['hint'] !== null) {
                $describedBy[] = $id . '-hint';
            }
            if ($tieneError) {
                $describedBy[] = $id . '-error';
            }
            ?>
            <div class="field">
                <label for="<?= e($id) ?>"><?= e($campo['label']) ?> <span class="req" aria-hidden="true">*</span></label>
                <input type="password" id="<?= e($id) ?>" name="<?= e($name) ?>" autocomplete="<?= e($campo['autocomplete']) ?>" required
                       <?= $name === 'password_nueva' || $name === 'password_confirmacion' ? 'minlength="8"' : '' ?>
                       <?= $primero ? 'autofocus' : '' ?>
                       <?= $tieneError ? 'aria-invalid="true"' : '' ?>
                       <?= $describedBy !== [] ? 'aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '' ?>>
                <?php if ($campo['hint'] !== null): ?>
                    <span class="field__hint" id="<?= e($id) ?>-hint"><?= e($campo['hint']) ?></span>
                <?php endif; ?>
                <?php if ($tieneError): ?>
                    <em class="field__error" id="<?= e($id) ?>-error"><?= icon('alert', 'icon--sm') ?> <?= e($errors[$name]) ?></em>
                <?php endif; ?>
            </div>
            <?php $primero = false; ?>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= icon('lock') ?> Guardar contraseña</button>
            <a class="btn btn--ghost" href="/">Cancelar</a>
        </div>
    </form>
</section>
