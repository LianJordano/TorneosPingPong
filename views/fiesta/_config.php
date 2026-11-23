<?php
/**
 * Shared config fields (create form + hub settings).
 * @var array<string,mixed> $cfg current values
 * @var array<string,string> $errors
 */
use PingPong\Service\FiestaService;

$errors = $errors ?? [];
$mejorDeCfg = (string) ($cfg['mejor_de'] ?? '1');
$overridesCfg = FiestaService::parsearSetsRondas(
    is_string($cfg['sets_rondas_json'] ?? null) ? $cfg['sets_rondas_json'] : null
);
$valElim = array_key_exists('mejor_de_elim', $cfg)
    ? (string) $cfg['mejor_de_elim']
    : (isset($overridesCfg['eliminatoria']) ? (string) $overridesCfg['eliminatoria'] : '');
$valSemis = array_key_exists('mejor_de_semis', $cfg)
    ? (string) $cfg['mejor_de_semis']
    : (isset($overridesCfg['semifinal']) ? (string) $overridesCfg['semifinal'] : '');
$valFinal = array_key_exists('mejor_de_final', $cfg)
    ? (string) $cfg['mejor_de_final']
    : (isset($overridesCfg['final']) ? (string) $overridesCfg['final'] : '');
$hayOverrides = $valElim !== '' || $valSemis !== '' || $valFinal !== ''
    || isset($errors['mejor_de_elim']) || isset($errors['mejor_de_semis']) || isset($errors['mejor_de_final']);
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
    <div class="segmented-choice segmented-choice--3" role="radiogroup" aria-labelledby="l-formato">
        <label class="choice-card">
            <input type="radio" name="formato" value="ELIMINACION"<?= $sel('formato', 'ELIMINACION') ?> data-formato>
            <?= icon('bracket') ?>
            <span><span class="choice-card__title">Eliminación directa</span><span class="choice-card__meta">El que pierde, afuera</span></span>
        </label>
        <label class="choice-card">
            <input type="radio" name="formato" value="GRUPOS"<?= $sel('formato', 'GRUPOS') ?> data-formato>
            <?= icon('layers') ?>
            <span><span class="choice-card__title">Grupos + eliminatoria</span><span class="choice-card__meta">Varios grupos y después cruces</span></span>
        </label>
        <label class="choice-card">
            <input type="radio" name="formato" value="LIGA"<?= $sel('formato', 'LIGA') ?> data-formato>
            <?= icon('table') ?>
            <span><span class="choice-card__title">Liga todos contra todos</span><span class="choice-card__meta">Todos contra todos con puntos + eliminatoria final</span></span>
        </label>
    </div>
    <?= $err('formato') ?>
</div>

<div class="form-grid fiesta-grupos-cfg" data-solo-grupos>
    <div class="field" data-solo-multi-grupo>
        <label for="f-grupos">Cantidad de grupos</label>
        <input type="number" id="f-grupos" name="num_grupos" min="1" max="16" inputmode="numeric" value="<?= e((string) ($cfg['num_grupos'] ?? 2)) ?>">
        <?= $err('num_grupos') ?>
    </div>
    <div class="field">
        <label for="f-clasificados" data-clasificados-label>Clasifican por grupo</label>
        <input type="number" id="f-clasificados" name="clasificados_por_grupo" min="1" max="8" inputmode="numeric" value="<?= e((string) ($cfg['clasificados_por_grupo'] ?? 2)) ?>" data-clasificados-input>
        <p class="field__hint" data-liga-hint hidden>Los mejores de la tabla general pasan al cuadro eliminatorio.</p>
        <?= $err('clasificados_por_grupo') ?>
    </div>
</div>

<div class="field field--wide">
    <span class="field__label" id="l-mejor-de">Sets por partido</span>
    <div class="segmented-choice segmented-choice--3" role="radiogroup" aria-labelledby="l-mejor-de">
        <label class="choice-card">
            <input type="radio" name="mejor_de" value="1"<?= $mejorDeCfg === '1' ? ' checked' : '' ?>>
            <?= icon('paddle') ?>
            <span><span class="choice-card__title">1 set</span><span class="choice-card__meta">Partido rápido a un set</span></span>
        </label>
        <label class="choice-card">
            <input type="radio" name="mejor_de" value="3"<?= $mejorDeCfg === '3' ? ' checked' : '' ?>>
            <?= icon('scoreboard') ?>
            <span><span class="choice-card__title">Mejor de 3</span><span class="choice-card__meta">Gana quien llegue a 2 sets</span></span>
        </label>
        <label class="choice-card">
            <input type="radio" name="mejor_de" value="5"<?= $mejorDeCfg === '5' ? ' checked' : '' ?>>
            <?= icon('trophy') ?>
            <span><span class="choice-card__title">Mejor de 5</span><span class="choice-card__meta">Gana quien llegue a 3 sets</span></span>
        </label>
    </div>
    <?= $err('mejor_de') ?>

    <details class="fiesta-sets-overrides"<?= $hayOverrides ? ' open' : '' ?>>
        <summary><?= icon('settings') ?> Personalizar sets por ronda (opcional)</summary>
        <p class="field__hint fiesta-sets-overrides__hint">Por defecto todas las fases heredan la opción de arriba. Ajustá solo las rondas que quieras jugar distinto (ej.: grupos a 1 set y final al mejor de 5).</p>
        <div class="fiesta-sets-overrides__grid">
            <div class="field">
                <label for="f-md-elim">Eliminatoria (rondas previas)</label>
                <select id="f-md-elim" name="mejor_de_elim">
                    <option value=""<?= $valElim === '' ? ' selected' : '' ?>>Igual al base</option>
                    <option value="1"<?= $valElim === '1' ? ' selected' : '' ?>>1 set</option>
                    <option value="3"<?= $valElim === '3' ? ' selected' : '' ?>>Mejor de 3</option>
                    <option value="5"<?= $valElim === '5' ? ' selected' : '' ?>>Mejor de 5</option>
                </select>
                <?= $err('mejor_de_elim') ?>
            </div>
            <div class="field">
                <label for="f-md-semis">Semifinales</label>
                <select id="f-md-semis" name="mejor_de_semis">
                    <option value=""<?= $valSemis === '' ? ' selected' : '' ?>>Igual al base</option>
                    <option value="1"<?= $valSemis === '1' ? ' selected' : '' ?>>1 set</option>
                    <option value="3"<?= $valSemis === '3' ? ' selected' : '' ?>>Mejor de 3</option>
                    <option value="5"<?= $valSemis === '5' ? ' selected' : '' ?>>Mejor de 5</option>
                </select>
                <?= $err('mejor_de_semis') ?>
            </div>
            <div class="field">
                <label for="f-md-final">Gran Final</label>
                <select id="f-md-final" name="mejor_de_final">
                    <option value=""<?= $valFinal === '' ? ' selected' : '' ?>>Igual al base</option>
                    <option value="1"<?= $valFinal === '1' ? ' selected' : '' ?>>1 set</option>
                    <option value="3"<?= $valFinal === '3' ? ' selected' : '' ?>>Mejor de 3</option>
                    <option value="5"<?= $valFinal === '5' ? ' selected' : '' ?>>Mejor de 5</option>
                </select>
                <?= $err('mejor_de_final') ?>
            </div>
        </div>
    </details>
</div>

<script>
    (function () {
        var script = document.currentScript;
        var root = (script && script.closest('form')) || document;
        var radios = root.querySelectorAll('[data-formato]');
        var bloque = root.querySelector('[data-solo-grupos]');
        var multiGrupo = root.querySelector('[data-solo-multi-grupo]');
        var clasifLabel = root.querySelector('[data-clasificados-label]');
        var clasifInput = root.querySelector('[data-clasificados-input]');
        var ligaHint = root.querySelector('[data-liga-hint]');
        if (!bloque) { return; }
        function sync() {
            var checked = root.querySelector('[data-formato]:checked');
            var val = checked ? checked.value : '';
            var esGrupos = val === 'GRUPOS';
            var esLiga = val === 'LIGA';
            bloque.hidden = !esGrupos && !esLiga;
            if (multiGrupo) { multiGrupo.hidden = !esGrupos; }
            if (clasifLabel) {
                clasifLabel.textContent = esLiga ? 'Clasifican a la eliminatoria' : 'Clasifican por grupo';
            }
            if (clasifInput) {
                clasifInput.min = esLiga ? '2' : '1';
                clasifInput.max = '8';
                if (esLiga && Number(clasifInput.value) < 2) {
                    clasifInput.value = '2';
                }
            }
            if (ligaHint) { ligaHint.hidden = !esLiga; }
        }
        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
</script>
