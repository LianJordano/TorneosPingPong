<?php
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
/** @var list<array<string,mixed>> $disponibles */
$errors = $errors ?? [];
$old = $old ?? [];
$cfg = $old + ['modalidad' => 'DOBLES', 'formato' => 'ELIMINACION', 'num_grupos' => 2, 'clasificados_por_grupo' => 2, 'mejor_de' => 1];
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
        <p>Elegí cómo se juega y, si querés, ya marcá a los jugadores que participan. Todo se puede ajustar hasta girar la ruleta.</p>
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
        <legend>Participantes <span class="muted small">(opcional, también se pueden sumar después)</span></legend>
        <?php if ($disponibles === []): ?>
            <p class="muted small"><?= icon('info') ?> No hay jugadores activos. Cargalos en <a class="link" href="/personas">Jugadores</a>.</p>
        <?php else: ?>
            <?php $seleccionados = $old['persona_ids'] ?? []; $submitLabel = null; require __DIR__ . '/_selector-jugadores.php'; ?>
        <?php endif; ?>
    </fieldset>

    <div class="form-actions">
        <a class="btn btn--ghost" href="/fiesta">Cancelar</a>
        <button type="submit" class="btn btn--primary"><?= icon('check') ?> Crear fiesta</button>
    </div>
</form>
