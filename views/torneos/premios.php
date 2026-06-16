<?php
/** @var array<string,mixed> $torneo */
/** @var array<int,array{posicion:int,monto:?string,porcentaje:?string}> $premios */
/** @var float $pozo */
/** @var array<int,string> $errores */
/** @var array<int,mixed> $old */
$errores = $errores ?? [];
$old = $old ?? [];
$tid = (string) $torneo['id'];
$posiciones = (int) $torneo['posiciones_premiadas'];
$valor = static function (int $pos) use ($old, $premios): string {
    if (isset($old[$pos])) {
        return e((string) $old[$pos]);
    }
    return isset($premios[$pos]['porcentaje']) ? e(rtrim(rtrim((string) $premios[$pos]['porcentaje'], '0'), '.')) : '';
};
$ordinal = ['', '1°', '2°', '3°', '4°', '5°', '6°', '7°', '8°'];
?>
<section class="page-head">
    <div>
        <h1>Distribución del premio</h1>
        <p class="muted">Pozo estimado: <strong>$<?= e(number_format($pozo, 2)) ?></strong>
            <?php if (empty($torneo['pozo_total'])): ?><small>(cuota × inscritos)</small><?php endif; ?>
        </p>
    </div>
    <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a>
</section>

<section class="card">
    <p class="hint">Definí qué <strong>porcentaje</strong> del pozo recibe cada posición. Deben sumar exactamente <strong>100%</strong>.</p>

    <?php if (isset($errores[0])): ?>
        <div class="flash flash--error"><?= e($errores[0]) ?></div>
    <?php endif; ?>

    <form method="post" action="/torneos/<?= e($tid) ?>/premios">
        <?= csrf_field() ?>
        <table class="table">
            <thead><tr><th>Posición</th><th>Porcentaje</th><th>Premio estimado</th></tr></thead>
            <tbody>
            <?php for ($pos = 1; $pos <= $posiciones; $pos++): ?>
                <tr>
                    <td><strong><?= e($ordinal[$pos] ?? $pos . '°') ?></strong></td>
                    <td>
                        <input type="number" step="0.01" min="0" name="porcentaje[<?= e((string) $pos) ?>]" value="<?= $valor($pos) ?>" style="width:120px">%
                        <?php if (isset($errores[$pos])): ?><em class="field__error"><?= e($errores[$pos]) ?></em><?php endif; ?>
                    </td>
                    <td class="muted">
                        <?php $pct = $premios[$pos]['porcentaje'] ?? null; ?>
                        <?= $pct !== null ? '$' . e(number_format($pozo * (float) $pct / 100, 2)) : '—' ?>
                    </td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Guardar distribución</button>
            <a class="btn btn--ghost" href="/torneos/<?= e($tid) ?>/podio">Ver podio</a>
        </div>
    </form>
</section>
