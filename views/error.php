<?php
$tituloError = (string) ($title ?? 'Algo salió mal');
$esNoEncontrado = stripos($tituloError, 'encontrad') !== false;
?>
<section class="error-page" aria-labelledby="error-titulo">
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon($esNoEncontrado ? 'search' : 'alert') ?></span>
        <h1 class="empty-state__title" id="error-titulo"><?= e($tituloError) ?></h1>
        <p><?= e($mensaje ?? 'Ocurrió un error inesperado.') ?></p>
        <div class="actions">
            <a class="btn btn--primary" href="/"><?= icon('home') ?> Ir al inicio</a>
            <?php if ($esNoEncontrado): ?>
                <a class="btn btn--secondary" href="/torneos"><?= icon('trophy') ?> Ver torneos</a>
            <?php endif; ?>
        </div>
    </div>
</section>
