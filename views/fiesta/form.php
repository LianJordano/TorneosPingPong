<?php
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
$errors = $errors ?? [];
$old = $old ?? [];
$cfg = $old + ['modalidad' => 'DOBLES', 'formato' => 'ELIMINACION', 'num_grupos' => 2, 'clasificados_por_grupo' => 2];
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/fiesta">Torneo fiesta</a></li>
        <li><span aria-current="page">Nueva fiesta</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow">Torneo fiesta</span>
        <h1>Nueva fiesta</h1>
        <p>Elegí cómo se juega y, si querés, pegá ya la lista de participantes. Todo se puede ajustar hasta girar la ruleta.</p>
    </div>
</header>

<?php if ($errors !== []): ?>
    <div class="alert alert--error" role="alert">
        <?= icon('alert') ?>
        <div class="flash__body">Revisá los campos marcados.</div>
    </div>
<?php endif; ?>

<form method="post" action="/fiesta" class="card fiesta-form" novalidate>
    <?= csrf_field() ?>
    <fieldset class="fieldset">
        <legend>La fiesta</legend>
        <div class="form-grid">
            <div class="field field--wide">
                <label for="f-nombre">Nombre <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="f-nombre" name="nombre" maxlength="150" required autofocus
                       placeholder="Ej.: Dobles locos de fin de año" value="<?= e((string) ($old['nombre'] ?? '')) ?>"
                       <?= isset($errors['nombre']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['nombre'])): ?>
                    <p class="field__error"><?= icon('alert') ?> <?= e($errors['nombre']) ?></p>
                <?php endif; ?>
            </div>
            <?php require __DIR__ . '/_config.php'; ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Participantes</legend>
        <div class="field">
            <label for="f-participantes">Nombres (opcional)</label>
            <textarea id="f-participantes" name="participantes" rows="6" aria-describedby="h-participantes"
                      placeholder="Ana&#10;Bruno&#10;Carla&#10;Diego"><?= e((string) ($old['participantes'] ?? '')) ?></textarea>
            <p class="field__hint" id="h-participantes">Uno por línea o separados por coma. Los repetidos se ignoran.</p>
        </div>
    </fieldset>

    <div class="form-actions">
        <a class="btn btn--ghost" href="/fiesta">Cancelar</a>
        <button type="submit" class="btn btn--primary"><?= icon('check') ?> Crear fiesta</button>
    </div>
</form>
