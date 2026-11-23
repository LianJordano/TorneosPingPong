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
        <h1>Armá tu Torneo Fiesta</h1>
        <p>Elegí una plantilla o combiná modalidad, estructura y sets por ronda a medida. Todo se puede ajustar hasta girar la ruleta.</p>
    </div>
</header>

<?php if ($errors !== []): ?>
    <div class="alert alert--error" role="alert">
        <?= icon('alert') ?>
        <div class="flash__body">Revisá los campos marcados.</div>
    </div>
<?php endif; ?>

<form method="post" action="/fiesta" class="fiesta-builder" novalidate>
    <?= csrf_field() ?>

    <div class="fiesta-builder__main">
        <section class="card fiesta-builder__card">
            <div class="field field--wide fiesta-name-field">
                <label for="f-nombre">Nombre del torneo <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="f-nombre" name="nombre" maxlength="150" required autofocus
                       placeholder="Ej.: Copa Viernes de Oficina · Dobles Locos" value="<?= e((string) ($old['nombre'] ?? '')) ?>"
                       <?= isset($errors['nombre']) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errors['nombre'])): ?>
                    <p class="field__error"><?= icon('alert') ?> <?= e($errors['nombre']) ?></p>
                <?php endif; ?>
            </div>

            <?php $modoCompacto = false; require __DIR__ . '/_config.php'; ?>
        </section>

        <section class="card fiesta-builder__card">
            <div class="fiesta-cfg-block__head">
                <span class="fiesta-cfg-step">4</span>
                <div>
                    <span class="fiesta-cfg-block__title">Participantes <span class="muted small">(opcional)</span></span>
                    <p class="fiesta-cfg-block__sub">Podés marcarlos ahora o ir sumándolos antes de girar la ruleta.</p>
                </div>
            </div>
            <?php if ($disponibles === []): ?>
                <p class="muted small"><?= icon('info') ?> No hay jugadores activos. Cargalos en <a class="link" href="/personas">Jugadores</a>.</p>
            <?php else: ?>
                <?php $seleccionados = $old['persona_ids'] ?? []; $submitLabel = null; require __DIR__ . '/_selector-jugadores.php'; ?>
            <?php endif; ?>
        </section>

        <div class="form-actions fiesta-builder__actions">
            <a class="btn btn--ghost" href="/fiesta">Cancelar</a>
            <button type="submit" class="btn btn--primary btn--lg"><?= icon('check') ?> Crear Torneo Fiesta</button>
        </div>
    </div>

    <aside class="fiesta-builder__side" aria-label="Vista previa del torneo">
        <div class="card fiesta-preview" data-fiesta-preview>
            <div class="fiesta-preview__head">
                <span class="eyebrow">Vista previa en vivo</span>
                <h2 class="fiesta-preview__title" data-pv-title>Tu próximo Torneo Fiesta</h2>
            </div>

            <ol class="fiesta-pv__flow" data-pv-flow></ol>

            <div class="fiesta-preview__stats">
                <div class="fiesta-preview__stat">
                    <span>Equipos</span>
                    <strong data-pv-teams>Sin anotados aún</strong>
                </div>
                <div class="fiesta-preview__stat">
                    <span>Partidos est.</span>
                    <strong class="num" data-pv-matches>—</strong>
                </div>
            </div>

            <p class="fiesta-preview__note" data-pv-note>Podés crear la fiesta ahora y sumar jugadores después.</p>
        </div>
    </aside>
</form>
