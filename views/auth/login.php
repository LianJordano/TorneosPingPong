<?php
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
$errors = $errors ?? [];
$old = $old ?? [];
?>
<section class="auth">
    <div class="card auth__card">
        <h1>Ingresar</h1>
        <p class="muted">Accedé con tu cuenta para administrar los torneos.</p>

        <form method="post" action="/login" novalidate>
            <?= csrf_field() ?>

            <label class="field">
                <span>Correo</span>
                <input type="email" name="email" value="<?= old($old, 'email') ?>" autofocus required>
                <?php if (isset($errors['email'])): ?>
                    <em class="field__error"><?= e($errors['email']) ?></em>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>Contraseña</span>
                <input type="password" name="password" required>
                <?php if (isset($errors['password'])): ?>
                    <em class="field__error"><?= e($errors['password']) ?></em>
                <?php endif; ?>
            </label>

            <button type="submit" class="btn btn--primary btn--block">Entrar</button>
        </form>
    </div>
</section>
