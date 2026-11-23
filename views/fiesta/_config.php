<?php
/**
 * Shared config fields (create form + hub settings).
 * @var array<string,mixed> $cfg current values
 * @var array<string,string> $errors
 */
$errors = $errors ?? [];
$sel = static fn (string $k, string $v): string => (string) ($cfg[$k] ?? '') === $v ? ' checked' : '';
$err = static fn (string $k): string => isset($errors[$k])
    ? '<p class="field__error">' . icon('alert') . ' ' . e($errors[$k]) . '</p>'
    : '';
?>
<div class="field field--wide">
    <span class="field__label" id="l-modalidad">Modalidad</span>
    <div class="segmented-choice" role="radiogroup" aria-labelledby="l-modalidad">
        <label class="choice-card">
            <input type="radio" name="modalidad" value="DOBLES"<?= $sel('modalidad', 'DOBLES') ?>>
            <?= icon('users') ?>
            <span><span class="choice-card__title">Dobles</span><span class="choice-card__meta">Parejas sorteadas por la ruleta</span></span>
        </label>
        <label class="choice-card">
            <input type="radio" name="modalidad" value="INDIVIDUAL"<?= $sel('modalidad', 'INDIVIDUAL') ?>>
            <?= icon('user') ?>
            <span><span class="choice-card__title">Individual</span><span class="choice-card__meta">Cada uno juega por su cuenta</span></span>
        </label>
    </div>
    <?= $err('modalidad') ?>
</div>

<div class="field field--wide">
    <span class="field__label" id="l-formato">Formato</span>
    <div class="segmented-choice" role="radiogroup" aria-labelledby="l-formato">
        <label class="choice-card">
            <input type="radio" name="formato" value="ELIMINACION"<?= $sel('formato', 'ELIMINACION') ?> data-formato>
            <?= icon('bracket') ?>
            <span><span class="choice-card__title">Eliminación directa</span><span class="choice-card__meta">El que pierde, afuera</span></span>
        </label>
        <label class="choice-card">
            <input type="radio" name="formato" value="GRUPOS"<?= $sel('formato', 'GRUPOS') ?> data-formato>
            <?= icon('layers') ?>
            <span><span class="choice-card__title">Grupos + eliminatoria</span><span class="choice-card__meta">Todos contra todos y después cruces</span></span>
        </label>
    </div>
    <?= $err('formato') ?>
</div>

<div class="form-grid fiesta-grupos-cfg" data-solo-grupos>
    <div class="field">
        <label for="f-grupos">Cantidad de grupos</label>
        <input type="number" id="f-grupos" name="num_grupos" min="1" max="16" inputmode="numeric" value="<?= e((string) ($cfg['num_grupos'] ?? 2)) ?>">
        <?= $err('num_grupos') ?>
    </div>
    <div class="field">
        <label for="f-clasificados">Clasifican por grupo</label>
        <input type="number" id="f-clasificados" name="clasificados_por_grupo" min="1" max="8" inputmode="numeric" value="<?= e((string) ($cfg['clasificados_por_grupo'] ?? 2)) ?>">
        <?= $err('clasificados_por_grupo') ?>
    </div>
</div>

<script>
    (function () {
        var script = document.currentScript;
        var root = (script && script.closest('form')) || document;
        var radios = root.querySelectorAll('[data-formato]');
        var bloque = root.querySelector('[data-solo-grupos]');
        if (!bloque) { return; }
        function sync() {
            var grupos = root.querySelector('[data-formato]:checked');
            bloque.hidden = !grupos || grupos.value !== 'GRUPOS';
        }
        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
</script>
