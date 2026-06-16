<?php
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
$errors = $errors ?? [];
$old = $old ?? [];
?>
<section class="auth auth--split">
    <div class="auth__panel">
        <span class="eyebrow">Ping Pong League Ops</span>
        <h1>Competencia con estética de producto serio</h1>
        <p>Administrá torneos, jugadores, fixtures, premios y la vista pública desde un hub visual más sólido y profesional.</p>
        <ul class="feature-list">
            <li>Liga + bracket en una sola plataforma</li>
            <li>Seguimiento visual del estado competitivo</li>
            <li>Vista pública lista para proyectar</li>
        </ul>
    </div>

    <div class="card auth__card">
        <span class="eyebrow">Acceso seguro</span>
        <h2>Ingresar</h2>
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
