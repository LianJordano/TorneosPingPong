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
<section class="page-head">
    <div>
        <h1>Cargar marcador</h1>
        <p class="muted">
            <strong><?= e($partido['a_label'] ?? 'BYE') ?></strong> vs <strong><?= e($partido['b_label'] ?? 'BYE') ?></strong>
        </p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= e($partido['fase'] === 'ELIMINATORIA' ? "/torneos/{$tid}/bracket" : "/torneos/{$tid}/partidos") ?>">Volver</a>
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

        <div id="picker-ganador" class="field" style="<?= $tipoActual === 'NORMAL' ? 'display:none' : '' ?>">
            <span>Ganador del partido</span>
            <label class="radio"><input type="radio" name="ganador" value="A" <?= $ganadorActual === 'A' ? 'checked' : '' ?>> <?= e($partido['a_label'] ?? 'A') ?></label>
            <label class="radio"><input type="radio" name="ganador" value="B" <?= $ganadorActual === 'B' ? 'checked' : '' ?>> <?= e($partido['b_label'] ?? 'B') ?></label>
        </div>

        <div id="tabla-sets">
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
