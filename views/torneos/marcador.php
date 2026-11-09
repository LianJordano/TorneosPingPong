<?php
/** @var array<string,mixed> $torneo */
/** @var array<string,mixed> $partido */
/** @var \PingPong\Domain\ReglasMarcador $reglas */
/** @var list<array{numero_set:int,puntos_a:int,puntos_b:int}> $sets */
/** @var list<string> $errores */
/** @var list<\PingPong\Domain\TipoResultado> $tipos */
/** @var string $tipoActual */
/** @var ?array{actual:int,total:int,pendientes:int} $progreso */
$tid = (string) $torneo['id'];
$maxSets = $reglas->formatoSets;
$filas = max($maxSets, count($sets));
$tipoActual = $tipoActual ?? 'NORMAL';
$progreso = $progreso ?? null;
$ganadorActual = isset($partido['ganador_inscripcion_id']) && $partido['ganador_inscripcion_id'] !== null
    ? ((int) $partido['ganador_inscripcion_id'] === (int) $partido['inscripcion_a_id'] ? 'A' : 'B')
    : '';
$esEliminatoria = $partido['fase'] === 'ELIMINATORIA';
$volver = $esEliminatoria ? "/torneos/{$tid}/bracket" : "/torneos/{$tid}/partidos";
$volverLabel = $esEliminatoria ? 'Llaves' : 'Partidos';
$nombreA = trim((string) ($partido['a_label'] ?? 'Jugador A'));
$nombreB = trim((string) ($partido['b_label'] ?? 'Jugador B'));
$setsParaGanar = $reglas->setsParaGanar();
$hayErrores = $errores !== [];
$mostrarPicker = $tipoActual !== 'NORMAL';
$mostrarSets = $tipoActual === 'NORMAL' || $tipoActual === 'RETIRO';
?>
<div class="scorekeeper">
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><a href="/torneos/<?= e($tid) ?>"><?= e($torneo['nombre']) ?></a></li>
        <li><a href="<?= e($volver) ?>"><?= e($volverLabel) ?></a></li>
        <li><span aria-current="page">Marcador</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= icon('scoreboard') ?> <?= $esEliminatoria ? 'Fase eliminatoria' : 'Fase de liga' ?> · modo anotador</span>
        <h1>Cargar marcador</h1>
        <?php if ($progreso !== null): ?>
            <p>Partido <strong><?= e((string) $progreso['actual']) ?></strong> de <strong><?= e((string) $progreso['total']) ?></strong> · quedan <?= e((string) $progreso['pendientes']) ?> pendiente<?= $progreso['pendientes'] === 1 ? '' : 's' ?>.</p>
        <?php endif; ?>
    </div>
</header>

<section class="scorekeeper-match" aria-label="Partido actual">
    <div class="scorekeeper-match__side">
        <span class="scorekeeper-match__tag">Jugador A</span>
        <strong class="scorekeeper-match__name"><?= e($nombreA) ?></strong>
    </div>
    <span class="scorekeeper-match__vs" aria-hidden="true">vs</span>
    <span class="sr-only">contra</span>
    <div class="scorekeeper-match__side scorekeeper-match__side--b">
        <span class="scorekeeper-match__tag">Jugador B</span>
        <strong class="scorekeeper-match__name"><?= e($nombreB) ?></strong>
    </div>
</section>

<section class="card scorekeeper-card">
    <p class="hint">
        <?= icon('info') ?>
        <span>
            <?= $maxSets === 1 ? 'Partido a <strong>1 set</strong>.' : 'Al <strong>mejor de ' . e((string) $maxSets) . '</strong> sets: gana quien gane ' . e((string) $setsParaGanar) . ' set' . ($setsParaGanar > 1 ? 's' : '') . '.' ?>
            Cada set se gana a los <strong><?= e((string) $reglas->puntosPorSet) ?></strong> puntos con diferencia de <strong><?= e((string) $reglas->diferenciaMinima) ?></strong>.
        </span>
    </p>

    <?php if ($hayErrores): ?>
        <div class="flash flash--error scorekeeper-errors" id="marcador-errores" role="alert" tabindex="-1">
            <?= icon('alert') ?>
            <div class="flash__body">
                <strong>No se pudo guardar el marcador.</strong>
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="/torneos/<?= e($tid) ?>/partidos/<?= e((string) $partido['id']) ?>" id="form-marcador"<?= $hayErrores ? ' aria-describedby="marcador-errores"' : '' ?>>
        <?= csrf_field() ?>

        <div class="field">
            <label for="tipo-resultado">Tipo de resultado</label>
            <select name="tipo_resultado" id="tipo-resultado" aria-describedby="tipo-resultado-hint">
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= e($t->value) ?>" <?= $tipoActual === $t->value ? 'selected' : '' ?>><?= e($t->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="field__hint" id="tipo-resultado-hint">En un partido normal el ganador se calcula solo con los sets. En un retiro podés dejar los sets ya jugados.</span>
        </div>

        <fieldset id="picker-ganador" class="fieldset scorekeeper-panel"<?= $mostrarPicker ? '' : ' hidden' ?>>
            <legend>Ganador del partido</legend>
            <div class="segmented-choice">
                <label class="choice-card">
                    <input type="radio" name="ganador" value="A" <?= $ganadorActual === 'A' ? 'checked' : '' ?>>
                    <span><span class="choice-card__title"><?= e($nombreA) ?></span><span class="choice-card__meta">Jugador A</span></span>
                </label>
                <label class="choice-card">
                    <input type="radio" name="ganador" value="B" <?= $ganadorActual === 'B' ? 'checked' : '' ?>>
                    <span><span class="choice-card__title"><?= e($nombreB) ?></span><span class="choice-card__meta">Jugador B</span></span>
                </label>
            </div>
        </fieldset>

        <div id="tabla-sets" class="scorekeeper-panel"<?= $mostrarSets ? '' : ' hidden' ?>>
            <table class="table table--score">
                <caption class="sr-only">Puntos por set</caption>
                <thead>
                    <tr>
                        <th scope="col">Set</th>
                        <th scope="col"><?= e($nombreA) ?></th>
                        <th scope="col"><?= e($nombreB) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php for ($i = 0; $i < $filas; $i++): ?>
                    <?php $n = $i + 1; ?>
                    <tr>
                        <th scope="row"><?= e((string) $n) ?></th>
                        <td><input type="number" name="puntos_a[]" min="0" inputmode="numeric" autocomplete="off" enterkeyhint="next"
                                   aria-label="Set <?= e((string) $n) ?>, puntos de <?= e($nombreA) ?>"
                                   value="<?= isset($sets[$i]) ? e((string) $sets[$i]['puntos_a']) : '' ?>"></td>
                        <td><input type="number" name="puntos_b[]" min="0" inputmode="numeric" autocomplete="off" enterkeyhint="next"
                                   aria-label="Set <?= e((string) $n) ?>, puntos de <?= e($nombreB) ?>"
                                   value="<?= isset($sets[$i]) ? e((string) $sets[$i]['puntos_b']) : '' ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions form-actions--sticky scorekeeper-actions">
            <button type="submit" class="btn btn--primary btn--lg scorekeeper-save"><?= icon('check') ?> Guardar marcador</button>
            <?php if ($partido['fase'] === 'LIGA'): ?>
                <button type="submit" name="accion" value="guardar_y_seguir" class="btn btn--secondary btn--lg"><?= icon('arrow-right') ?> Guardar y seguir</button>
            <?php endif; ?>
            <a class="btn btn--ghost btn--lg" href="<?= e($volver) ?>">Cancelar</a>
        </div>
    </form>
</section>
</div>

<script>
(function () {
    var tipo = document.getElementById('tipo-resultado');
    var picker = document.getElementById('picker-ganador');
    var tablaSets = document.getElementById('tabla-sets');
    function actualizar() {
        var v = tipo.value;
        picker.hidden = v === 'NORMAL';
        // Walkover, disqualification or no-show need no sets.
        tablaSets.hidden = !(v === 'NORMAL' || v === 'RETIRO');
    }
    tipo.addEventListener('change', actualizar);
    actualizar();
    var errores = document.getElementById('marcador-errores');
    if (errores) { errores.focus(); }
})();
</script>
