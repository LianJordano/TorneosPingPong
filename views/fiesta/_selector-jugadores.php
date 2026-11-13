<?php
/**
 * Searchable multi-select of platform players (same Tom Select pattern as the
 * tournament registration). The native <select multiple name="persona_ids[]">
 * is the source of truth; Tom Select only enhances it and the native control
 * keeps working if the CDN is unavailable.
 *
 * @var list<array<string,mixed>> $disponibles
 * @var list<int> $seleccionados
 * @var string|null $submitLabel button label; null when the host form has its own submit
 */
$seleccionados = $seleccionados ?? [];
$submitLabel = $submitLabel ?? null;
$marcados = array_flip($seleccionados);
?>
<div class="field fiesta-picker" data-picker>
    <label for="f-jugadores">Jugadores</label>
    <select id="f-jugadores" name="persona_ids[]" multiple data-ts placeholder="Buscá y elegí jugadores…" autocomplete="off" aria-describedby="h-jugadores">
        <?php foreach ($disponibles as $d): ?>
            <?php $area = !empty($d['area']) ? ' (' . $d['area'] . ')' : ''; ?>
            <option value="<?= e((string) $d['id']) ?>"<?= isset($marcados[(int) $d['id']]) ? ' selected' : '' ?>><?= e($d['apellido'] . ', ' . $d['nombre'] . $area) ?></option>
        <?php endforeach; ?>
    </select>
    <p class="field__hint" id="h-jugadores">Son los mismos jugadores de la plataforma. Elegí uno, varios o todos.</p>
    <div class="actions fiesta-picker__actions">
        <?php if ($submitLabel !== null): ?>
            <button class="btn btn--primary" type="submit" data-ts-submit><?= icon('user-plus') ?> <span data-ts-label><?= e($submitLabel) ?></span></button>
        <?php endif; ?>
        <button class="btn btn--ghost" type="button" data-ts-all>Seleccionar todos</button>
        <button class="btn btn--ghost" type="button" data-ts-none hidden>Limpiar</button>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    var box = document.querySelector('[data-picker]');
    var el = box && box.querySelector('[data-ts]');
    if (!el) { return; }
    var submit = box.querySelector('[data-ts-submit]');
    var label = box.querySelector('[data-ts-label]');
    var btnAll = box.querySelector('[data-ts-all]');
    var btnNone = box.querySelector('[data-ts-none]');
    var base = label ? label.textContent.trim() : '';

    function count() { return ts ? ts.items.length : el.selectedOptions.length; }
    function total() { return ts ? Object.keys(ts.options).length : el.options.length; }
    function refresh() {
        var n = count();
        if (label) { label.textContent = n > 0 ? base + ' (' + n + ')' : base; }
        if (submit) { submit.disabled = n === 0; }
        btnNone.hidden = n === 0;
        btnAll.disabled = n >= total();
    }

    var ts = null;
    if (typeof TomSelect !== 'undefined') {
        ts = new TomSelect(el, {
            plugins: ['remove_button'],
            maxItems: null,
            hideSelected: true,
            closeAfterSelect: false,
            placeholder: el.getAttribute('placeholder'),
            sortField: { field: 'text', direction: 'asc' },
            render: { no_results: function () { return '<div class="no-results">Sin coincidencias.</div>'; } }
        });
        ts.on('change', refresh);
    } else {
        el.addEventListener('change', refresh);
    }

    btnAll.addEventListener('click', function () {
        if (ts) { ts.setValue(Object.keys(ts.options), false); ts.close(); }
        else { Array.prototype.forEach.call(el.options, function (o) { o.selected = true; }); refresh(); }
    });
    btnNone.addEventListener('click', function () {
        if (ts) { ts.clear(); }
        else { Array.prototype.forEach.call(el.options, function (o) { o.selected = false; }); refresh(); }
    });
    refresh();
})();
</script>
