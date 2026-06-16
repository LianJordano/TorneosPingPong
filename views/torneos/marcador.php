<?php
/** @var array<string,mixed> $torneo */
/** @var array<string,mixed> $partido */
/** @var \PingPong\Domain\ReglasMarcador $reglas */
/** @var list<array{numero_set:int,puntos_a:int,puntos_b:int}> $sets */
/** @var list<string> $errores */
/** @var list<\PingPong\Domain\TipoResultado> $tipos */
/** @var string $tipoActual */
$tid = (string) $torneo['id'];
$maxSets = $reglas->formatoSets;
$filas = max($maxSets, count($sets));
$tipoActual = $tipoActual ?? 'NORMAL';
$ganadorActual = isset($partido['ganador_inscripcion_id']) && $partido['ganador_inscripcion_id'] !== null
    ? ((int) $partido['ganador_inscripcion_id'] === (int) $partido['inscripcion_a_id'] ? 'A' : 'B')
    : '';
?>
<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Score control</span>
            <h1>Cargar marcador</h1>
            <p class="muted"><strong><?= e($partido['a_label'] ?? 'BYE') ?></strong> vs <strong><?= e($partido['b_label'] ?? 'BYE') ?></strong></p>
            <div class="meta-row">
                <span class="meta-pill">🎮 <?= e($partido['fase']) ?></span>
                <span class="meta-pill">🏓 <?= $maxSets === 1 ? '1 set' : 'Mejor de ' . e((string) $maxSets) ?></span>
            </div>
        </div>
        <div class="hero-side">
            <div class="actions"><a class="btn btn--ghost btn--sm" href="<?= e($partido['fase'] === 'ELIMINATORIA' ? "/torneos/{$tid}/bracket" : "/torneos/{$tid}/partidos") ?>">Volver</a></div>
        </div>
    </div>
</section>

<section class="card">
    <p class="hint">
        Formato: <strong><?= $maxSets === 1 ? '1 set' : 'mejor de ' . $maxSets ?></strong>
        (gana quien gane <?= e((string) $reglas->setsParaGanar()) ?> set<?= $reglas->setsParaGanar() > 1 ? 's' : '' ?>).
        Cada set se gana a los <strong><?= e((string) $reglas->puntosPorSet) ?></strong> puntos con diferencia de
        <strong><?= e((string) $reglas->diferenciaMinima) ?></strong>.
    </p>

    <?php if ($errores !== []): ?>
        <div class="flash flash--error">
            <ul class="errores"><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $partido['id']) ?>" id="form-marcador">
        <?= csrf_field() ?>

        <div class="form-panel">
        <div class="form-grid">
            <label class="field">
                <span>Tipo de resultado</span>
                <select name="tipo_resultado" id="tipo-resultado">
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= e($t->value) ?>" <?= $tipoActual === $t->value ? 'selected' : '' ?>><?= e($t->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        </div>

        <div id="picker-ganador" class="form-panel" style="<?= $tipoActual === 'NORMAL' ? 'display:none' : '' ?>">
            <span class="field"><span>Ganador del partido</span></span>
            <div class="segmented-choice">
                <label class="choice-card"><input type="radio" name="ganador" value="A" <?= $ganadorActual === 'A' ? 'checked' : '' ?>><span><span class="choice-card__title"><?= e($partido['a_label'] ?? 'A') ?></span><span class="choice-card__meta">Lado A</span></span></label>
                <label class="choice-card"><input type="radio" name="ganador" value="B" <?= $ganadorActual === 'B' ? 'checked' : '' ?>><span><span class="choice-card__title"><?= e($partido['b_label'] ?? 'B') ?></span><span class="choice-card__meta">Lado B</span></span></label>
            </div>
        </div>

        <div id="tabla-sets" class="form-panel">
            <table class="table table--score">
                <thead>
                    <tr><th>Set</th><th><?= e($partido['a_label'] ?? 'A') ?></th><th><?= e($partido['b_label'] ?? 'B') ?></th></tr>
                </thead>
                <tbody>
                <?php for ($i = 0; $i < $filas; $i++): ?>
                    <tr>
                        <td><?= e((string) ($i + 1)) ?></td>
                        <td><input type="number" name="puntos_a[]" min="0" value="<?= isset($sets[$i]) ? e((string) $sets[$i]['puntos_a']) : '' ?>"></td>
                        <td><input type="number" name="puntos_b[]" min="0" value="<?= isset($sets[$i]) ? e((string) $sets[$i]['puntos_b']) : '' ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <p class="muted small">Para un partido normal, cargá los sets y el ganador se calcula solo. En un retiro podés dejar los sets ya jugados.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Guardar marcador</button>
            <a class="btn btn--ghost" href="<?= e($partido['fase'] === 'ELIMINATORIA' ? "/torneos/{$tid}/bracket" : "/torneos/{$tid}/partidos") ?>">Cancelar</a>
        </div>
    </form>
</section>

<script>
(function () {
    var tipo = document.getElementById('tipo-resultado');
    var picker = document.getElementById('picker-ganador');
    var tablaSets = document.getElementById('tabla-sets');
    function actualizar() {
        var v = tipo.value;
        picker.style.display = (v === 'NORMAL') ? 'none' : '';
        // En walkover / descalificación / no presentado no hacen falta sets.
        tablaSets.style.display = (v === 'NORMAL' || v === 'RETIRO') ? '' : 'none';
    }
    tipo.addEventListener('change', actualizar);
    actualizar();
})();
</script>
