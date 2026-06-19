<?php
/** @var array<string,string> $errors */
$errors = $errors ?? [];
?>
<section class="page-head">
    <div>
        <span class="eyebrow">Mi cuenta</span>
        <h1>Cambiar contraseña</h1>
        <p class="muted">Actualizá tu clave de acceso. Necesitás conocer la contraseña actual.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="/">Volver al inicio</a>
</section>

<section class="card" style="max-width: 540px;">
    <form method="post" action="/cuenta/password" novalidate>
        <?= csrf_field() ?>

        <label class="field">
            <span>Contraseña actual</span>
            <input type="password" name="password_actual" autofocus required>
            <?php if (isset($errors['password_actual'])): ?>
                <em class="field__error"><?= e($errors['password_actual']) ?></em>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Nueva contraseña</span>
            <input type="password" name="password_nueva" required>
            <small class="muted">Mínimo 8 caracteres.</small>
            <?php if (isset($errors['password_nueva'])): ?>
                <em class="field__error"><?= e($errors['password_nueva']) ?></em>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Repetir nueva contraseña</span>
            <input type="password" name="password_confirmacion" required>
            <?php if (isset($errors['password_confirmacion'])): ?>
                <em class="field__error"><?= e($errors['password_confirmacion']) ?></em>
            <?php endif; ?>
        </label>

        <button type="submit" class="btn btn--primary btn--block">Guardar contraseña</button>
    </form>
</section>
