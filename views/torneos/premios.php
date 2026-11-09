<?php
/** @var array<string,mixed> $torneo */
/** @var array<int,array{posicion:int,monto:?string,porcentaje:?string}> $premios */
/** @var float $pozo */
/** @var array<int,string> $errores */
/** @var array<int,mixed> $old */

use PingPong\Domain\EstadoTorneo;

$errores = $errores ?? [];
$old = $old ?? [];
$tid = (string) $torneo['id'];
$base = '/torneos/' . $tid;
$posiciones = (int) $torneo['posiciones_premiadas'];
$valor = static function (int $pos) use ($old, $premios): string {
    if (isset($old[$pos])) {
        return e((string) $old[$pos]);
    }
    return isset($premios[$pos]['porcentaje']) ? e(rtrim(rtrim((string) $premios[$pos]['porcentaje'], '0'), '.')) : '';
};
$ordinal = static fn (int $pos): string => $pos . '°';
$dinero = static fn (float $n): string => '$' . number_format($n, 2);
$pozoFijo = !empty($torneo['pozo_total']);

$estado = EstadoTorneo::from($torneo['estado']);
$enLigaOmas = in_array($estado, [EstadoTorneo::FASE_LIGA, EstadoTorneo::FASE_ELIMINATORIA, EstadoTorneo::FINALIZADO], true);
$enBracket = in_array($estado, [EstadoTorneo::FASE_ELIMINATORIA, EstadoTorneo::FINALIZADO], true);
$tabs = [['href' => $base, 'label' => 'Resumen', 'icon' => 'home']];
if ($enLigaOmas) {
    $tabs[] = ['href' => $base . '/partidos', 'label' => 'Partidos', 'icon' => 'scoreboard'];
    $tabs[] = ['href' => $base . '/tabla', 'label' => 'Tabla', 'icon' => 'table'];
}
if ($enBracket) {
    $tabs[] = ['href' => $base . '/bracket', 'label' => 'Bracket', 'icon' => 'bracket'];
    $tabs[] = ['href' => $base . '/podio', 'label' => 'Podio', 'icon' => 'podium'];
}
$tabs[] = ['href' => $base . '/premios', 'label' => 'Premios', 'icon' => 'gift', 'current' => true];
$tabs[] = ['href' => $base . '/auditoria', 'label' => 'Auditoría', 'icon' => 'history'];
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><a href="<?= e($base) ?>"><?= e($torneo['nombre']) ?></a></li>
        <li><span aria-current="page">Premios</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= e($torneo['nombre']) ?></span>
        <h1>Distribución de premios</h1>
        <p>Definí qué porcentaje del pozo recibe cada posición. La suma tiene que dar exactamente 100%.</p>
    </div>
</header>

<nav class="tabs" aria-label="Secciones del torneo">
    <?php foreach ($tabs as $tab): ?>
        <a class="tabs__link" href="<?= e($tab['href']) ?>"<?= !empty($tab['current']) ? ' aria-current="page"' : '' ?>><?= icon($tab['icon']) ?> <?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>

<div class="stats stats--compact">
    <div class="stat">
        <span class="stat__label"><?= icon('dollar') ?> Pozo a repartir</span>
        <span class="stat__num" data-pozo="<?= e((string) $pozo) ?>"><?= e($dinero($pozo)) ?></span>
        <span class="stat__hint"><?= $pozoFijo ? 'Monto fijo definido en el torneo.' : 'Se calcula como cuota × inscritos.' ?></span>
    </div>
    <div class="stat">
        <span class="stat__label"><?= icon('medal') ?> Posiciones premiadas</span>
        <span class="stat__num"><?= e((string) $posiciones) ?></span>
        <span class="stat__hint">Se cambia desde la configuración del torneo.</span>
    </div>
    <div class="stat">
        <span class="stat__label"><?= icon('target') ?> Suma actual</span>
        <span class="stat__num" data-premio-total>—</span>
        <span class="stat__hint" data-premio-estado>Tiene que cerrar en 100%.</span>
    </div>
</div>

<?php if (isset($errores[0])): ?>
    <div class="alert alert--error" role="alert">
        <?= icon('alert') ?>
        <div class="flash__body"><?= e($errores[0]) ?></div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($base) ?>/premios" class="card card--flush premios-form">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <caption class="sr-only">Porcentaje del pozo por posición</caption>
            <thead>
                <tr>
                    <th scope="col">Posición</th>
                    <th scope="col">Porcentaje del pozo</th>
                    <th scope="col" class="num">Premio estimado</th>
                </tr>
            </thead>
            <tbody>
            <?php for ($pos = 1; $pos <= $posiciones; $pos++): ?>
                <?php
                $pct = $premios[$pos]['porcentaje'] ?? null;
                $tieneError = isset($errores[$pos]);
                ?>
                <tr>
                    <td class="cell--primary"><span class="premio-pos"><?= $pos <= 3 ? icon('medal') : '' ?> <?= e($ordinal($pos)) ?> puesto</span></td>
                    <td data-label="Porcentaje">
                        <div class="premio-input">
                            <input type="number" step="0.01" min="0" max="100" inputmode="decimal"
                                   id="premio-<?= e((string) $pos) ?>"
                                   name="porcentaje[<?= e((string) $pos) ?>]" value="<?= $valor($pos) ?>"
                                   aria-label="Porcentaje para el <?= e($ordinal($pos)) ?> puesto"
                                   data-premio-pct
                                   <?= $tieneError ? 'aria-invalid="true" aria-describedby="premio-error-' . e((string) $pos) . '"' : '' ?>>
                            <span class="premio-input__suffix" aria-hidden="true">%</span>
                        </div>
                        <?php if ($tieneError): ?>
                            <p class="field__error" id="premio-error-<?= e((string) $pos) ?>"><?= icon('alert') ?> <?= e($errores[$pos]) ?></p>
                        <?php endif; ?>
                    </td>
                    <td data-label="Premio estimado" class="num" data-premio-monto>
                        <?= $pct !== null ? e($dinero($pozo * (float) $pct / 100)) : '—' ?>
                    </td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <div class="card__footer premios-form__footer">
        <button type="submit" class="btn btn--primary"><?= icon('check') ?> Guardar distribución</button>
        <a class="btn btn--ghost" href="<?= e($base) ?>/podio"><?= icon('podium') ?> Ver podio</a>
    </div>
</form>

<script>
// Live total and estimated prize while typing. Server-side validation still decides.
(function () {
    var inputs = document.querySelectorAll('[data-premio-pct]');
    var total = document.querySelector('[data-premio-total]');
    var estado = document.querySelector('[data-premio-estado]');
    var pozoEl = document.querySelector('[data-pozo]');
    if (!inputs.length || !total || !pozoEl) { return; }
    var pozo = parseFloat(pozoEl.getAttribute('data-pozo')) || 0;
    var fmt = function (n) { return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    function update() {
        var sum = 0;
        inputs.forEach(function (input) {
            var v = parseFloat(input.value);
            var cell = input.closest('tr').querySelector('[data-premio-monto]');
            if (!isNaN(v)) { sum += v; }
            if (cell) { cell.textContent = isNaN(v) ? '—' : fmt(pozo * v / 100); }
        });
        sum = Math.round(sum * 100) / 100;
        total.textContent = sum + '%';
        var ok = Math.abs(sum - 100) < 0.001;
        estado.textContent = ok ? 'La distribución cierra en 100%.' : (sum < 100 ? 'Faltan ' + (Math.round((100 - sum) * 100) / 100) + ' puntos para llegar a 100%.' : 'Sobran ' + (Math.round((sum - 100) * 100) / 100) + ' puntos.');
        total.classList.toggle('text-success', ok);
        total.classList.toggle('text-danger', !ok);
    }

    inputs.forEach(function (input) { input.addEventListener('input', update); });
    update();
})();
</script>
