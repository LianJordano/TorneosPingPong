<?php
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
$errors = $errors ?? [];
$old = $old ?? [];
$emailError = isset($errors['email']);
$passwordError = isset($errors['password']);
?>
<section class="auth">
    <div class="card auth__card">
        <h1 class="auth__title">Iniciar sesión</h1>
        <p class="muted">Ingresá con tu correo y contraseña.</p>

        <form method="post" action="/login" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="login-email">Correo electrónico</label>
                <input type="email" id="login-email" name="email" value="<?= old($old, 'email') ?>"
                       autocomplete="email" inputmode="email" autofocus required
                       <?= $emailError ? 'aria-invalid="true" aria-describedby="login-email-error"' : '' ?>>
                <?php if ($emailError): ?>
                    <em class="field__error" id="login-email-error"><?= icon('alert', 'icon--sm') ?> <?= e($errors['email']) ?></em>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="login-password">Contraseña</label>
                <input type="password" id="login-password" name="password" autocomplete="current-password" required
                       <?= $passwordError ? 'aria-invalid="true" aria-describedby="login-password-error"' : '' ?>>
                <?php if ($passwordError): ?>
                    <em class="field__error" id="login-password-error"><?= icon('alert', 'icon--sm') ?> <?= e($errors['password']) ?></em>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg"><?= icon('login') ?> Entrar</button>
        </form>
    </div>

    <aside class="auth__brand" aria-label="Sobre la aplicación">
        <span class="auth__brand-mark"><?= icon('paddle', 'icon--xl') ?></span>
        <h2>Torneos de ping pong, en orden</h2>
        <ul class="auth__features">
            <li><?= icon('trophy') ?> Liga y eliminatoria en un mismo lugar</li>
            <li><?= icon('scoreboard') ?> Marcadores cargados desde la mesa</li>
            <li><?= icon('tv') ?> Tablero público para proyectar</li>
        </ul>
    </aside>
</section>
