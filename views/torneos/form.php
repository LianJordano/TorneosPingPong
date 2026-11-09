<?php
/** @var array<string,mixed>|null $torneo */
/** @var string $action */
/** @var list<int> $formatos */
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
$errors = $errors ?? [];
$old = $old ?? [];
$val = static fn (string $k, string $def = ''): string => isset($old[$k])
    ? e((string) $old[$k])
    : e((string) ($torneo[$k] ?? $def));
$formatoLabel = static fn (int $n): string => $n === 1 ? '1 set' : "Mejor de {$n}";
$volver = $torneo ? '/torneos/' . (string) $torneo['id'] : '/torneos';

// aria-describedby / aria-invalid for a field with an optional hint.
$aria = static function (string $k, bool $hint = true) use ($errors): string {
    $ids = [];
    if ($hint) {
        $ids[] = 'h-' . $k;
    }
    if (isset($errors[$k])) {
        $ids[] = 'e-' . $k;
    }
    $out = $ids !== [] ? ' aria-describedby="' . e(implode(' ', $ids)) . '"' : '';
    return $out . (isset($errors[$k]) ? ' aria-invalid="true"' : '');
};
$error = static fn (string $k): string => isset($errors[$k])
    ? '<p class="field__error" id="e-' . e($k) . '">' . icon('alert') . ' ' . e($errors[$k]) . '</p>'
    : '';
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <?php if ($torneo): ?>
            <li><a href="<?= e($volver) ?>"><?= e((string) $torneo['nombre']) ?></a></li>
        <?php endif; ?>
        <li><span aria-current="page"><?= $torneo ? 'Editar' : 'Nuevo torneo' ?></span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow">Torneos</span>
        <h1><?= $torneo ? 'Editar torneo' : 'Nuevo torneo' ?></h1>
        <p><?= $torneo
            ? 'Ajustá la configuración mientras el torneo esté en borrador o con la inscripción abierta.'
            : 'Definí los datos básicos, la modalidad y las reglas de juego. Podés editarlos hasta que se cierre la inscripción.' ?></p>
    </div>
</header>

<?php if ($errors !== []): ?>
    <div class="alert alert--error" role="alert">
        <?= icon('alert') ?>
        <div class="flash__body">Revisá los campos marcados antes de guardar.</div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" novalidate class="card torneo-form">
    <?= csrf_field() ?>

    <fieldset class="fieldset">
        <legend>Datos generales</legend>
        <div class="form-grid">
            <div class="field field--wide">
                <label for="f-nombre">Nombre <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="f-nombre" name="nombre" value="<?= $val('nombre') ?>" required<?= $aria('nombre', false) ?>>
                <?= $error('nombre') ?>
            </div>
            <div class="field field--wide">
                <label for="f-descripcion">Descripción</label>
                <textarea id="f-descripcion" name="descripcion" rows="2"<?= $aria('descripcion') ?>><?= $val('descripcion') ?></textarea>
                <p class="field__hint" id="h-descripcion">Opcional. Se muestra debajo del nombre en el centro del torneo.</p>
            </div>
            <div class="field">
                <label for="f-lugar">Lugar</label>
                <input type="text" id="f-lugar" name="lugar" value="<?= $val('lugar') ?>"<?= $aria('lugar', false) ?>>
            </div>
            <div class="field">
                <label for="f-fecha-inicio">Fecha de inicio</label>
                <input type="date" id="f-fecha-inicio" name="fecha_inicio" value="<?= $val('fecha_inicio') ?>"<?= $aria('fecha_inicio', false) ?>>
                <?= $error('fecha_inicio') ?>
            </div>
            <div class="field">
                <label for="f-fecha-fin">Fecha de fin</label>
                <input type="date" id="f-fecha-fin" name="fecha_fin" value="<?= $val('fecha_fin') ?>"<?= $aria('fecha_fin') ?>>
                <p class="field__hint" id="h-fecha_fin">No puede ser anterior a la fecha de inicio.</p>
                <?= $error('fecha_fin') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Modalidad</legend>
        <div class="form-grid">
            <div class="field">
                <label for="modalidad-select">Modalidad de juego</label>
                <select name="modalidad" id="modalidad-select"<?= $aria('modalidad') ?>>
                    <option value="INDIVIDUAL" <?= $val('modalidad', 'INDIVIDUAL') === 'INDIVIDUAL' ? 'selected' : '' ?>>Individual</option>
                    <option value="DOBLES" <?= $val('modalidad', 'INDIVIDUAL') === 'DOBLES' ? 'selected' : '' ?>>Dobles (parejas)</option>
                </select>
                <p class="field__hint" id="h-modalidad">En dobles, cada participante de la liga es una pareja.</p>
                <?= $error('modalidad') ?>
            </div>
            <div class="field">
                <label for="emparejamiento-select">Formación de parejas</label>
                <select name="emparejamiento" id="emparejamiento-select"<?= $aria('emparejamiento') ?>>
                    <option value="MANUAL" <?= $val('emparejamiento', 'MANUAL') === 'MANUAL' ? 'selected' : '' ?>>Manual: se eligen al inscribir</option>
                    <option value="ALEATORIO" <?= $val('emparejamiento', 'MANUAL') === 'ALEATORIO' ? 'selected' : '' ?>>Aleatorio: las sortea el sistema</option>
                </select>
                <p class="field__hint" id="h-emparejamiento">Solo aplica a dobles. En aleatorio se inscribe a cada jugador por separado y las parejas se sortean con la inscripción cerrada.</p>
                <?= $error('emparejamiento') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Formato de juego</legend>
        <div class="form-grid">
            <div class="field">
                <label for="f-sets-liga">Sets en la liga</label>
                <select id="f-sets-liga" name="formato_sets_liga"<?= $aria('formato_sets_liga') ?>>
                    <?php foreach ($formatos as $f): ?>
                        <option value="<?= e((string) $f) ?>" <?= $val('formato_sets_liga', '3') === (string) $f ? 'selected' : '' ?>><?= e($formatoLabel($f)) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint" id="h-formato_sets_liga">Gana el partido quien se lleva la mayoría de los sets (al mejor de 3, gana con 2).</p>
                <?= $error('formato_sets_liga') ?>
            </div>
            <div class="field">
                <label for="f-sets-bracket">Sets en el bracket</label>
                <select id="f-sets-bracket" name="formato_sets_bracket"<?= $aria('formato_sets_bracket') ?>>
                    <?php foreach ($formatos as $f): ?>
                        <option value="<?= e((string) $f) ?>" <?= $val('formato_sets_bracket', '5') === (string) $f ? 'selected' : '' ?>><?= e($formatoLabel($f)) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint" id="h-formato_sets_bracket">Formato de los partidos de la fase eliminatoria.</p>
                <?= $error('formato_sets_bracket') ?>
            </div>
            <div class="field">
                <label for="f-puntos-set">Puntos por set</label>
                <input type="number" id="f-puntos-set" name="puntos_por_set" min="1" inputmode="numeric" value="<?= $val('puntos_por_set', '11') ?>"<?= $aria('puntos_por_set') ?>>
                <p class="field__hint" id="h-puntos_por_set">Puntos necesarios para ganar un set. Lo habitual es 11.</p>
                <?= $error('puntos_por_set') ?>
            </div>
            <div class="field">
                <label for="f-diferencia">Diferencia mínima</label>
                <input type="number" id="f-diferencia" name="diferencia_minima" min="1" inputmode="numeric" value="<?= $val('diferencia_minima', '2') ?>"<?= $aria('diferencia_minima') ?>>
                <p class="field__hint" id="h-diferencia_minima">Ventaja con la que se cierra un set. Con 11 puntos y diferencia 2, un 10-10 sigue hasta 12-10, 13-11, etc.</p>
                <?= $error('diferencia_minima') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Clasificación</legend>
        <div class="form-grid">
            <div class="field">
                <label for="f-puntos-victoria">Puntos por victoria</label>
                <input type="number" id="f-puntos-victoria" name="puntos_por_victoria" min="1" inputmode="numeric" value="<?= $val('puntos_por_victoria', '1') ?>"<?= $aria('puntos_por_victoria') ?>>
                <p class="field__hint" id="h-puntos_por_victoria">Lo que suma cada partido ganado en la tabla de posiciones de la liga.</p>
                <?= $error('puntos_por_victoria') ?>
            </div>
            <div class="field">
                <label for="f-clasificados">Clasificados al bracket</label>
                <input type="number" id="f-clasificados" name="num_clasificados" min="2" inputmode="numeric" value="<?= $val('num_clasificados', '8') ?>"<?= $aria('num_clasificados') ?>>
                <p class="field__hint" id="h-num_clasificados">Cuántos de los primeros de la tabla pasan a la eliminación directa (mínimo 2). Si no es potencia de 2, los mejores ubicados avanzan sin jugar la primera ronda; si hay menos participantes, clasifican todos.</p>
                <?= $error('num_clasificados') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Inscripción y premios</legend>
        <div class="form-grid">
            <div class="field">
                <label for="f-premiadas">Posiciones premiadas</label>
                <input type="number" id="f-premiadas" name="posiciones_premiadas" min="1" inputmode="numeric" value="<?= $val('posiciones_premiadas', '3') ?>"<?= $aria('posiciones_premiadas') ?>>
                <p class="field__hint" id="h-posiciones_premiadas">Puestos finales que reciben premio. El porcentaje de cada uno se define en Premios.</p>
                <?= $error('posiciones_premiadas') ?>
            </div>
            <div class="field">
                <label for="f-cuota">Cuota de inscripción</label>
                <input type="number" step="0.01" min="0" inputmode="decimal" id="f-cuota" name="cuota_inscripcion" value="<?= $val('cuota_inscripcion') ?>"<?= $aria('cuota_inscripcion') ?>>
                <p class="field__hint" id="h-cuota_inscripcion">Opcional. Monto que paga cada inscrito.</p>
                <?= $error('cuota_inscripcion') ?>
            </div>
            <div class="field">
                <label for="f-pozo">Pozo total</label>
                <input type="number" step="0.01" min="0" inputmode="decimal" id="f-pozo" name="pozo_total" value="<?= $val('pozo_total') ?>"<?= $aria('pozo_total') ?>>
                <p class="field__hint" id="h-pozo_total">Opcional. Si lo dejás vacío, el pozo se calcula como cuota × inscritos.</p>
                <?= $error('pozo_total') ?>
            </div>
        </div>
    </fieldset>

    <div class="form-actions form-actions--sticky">
        <button type="submit" class="btn btn--primary"><?= icon('check') ?> <?= $torneo ? 'Guardar cambios' : 'Crear torneo' ?></button>
        <a class="btn btn--ghost" href="<?= e($volver) ?>">Cancelar</a>
    </div>
</form>

<script>
    // Pair formation only applies to doubles: disable it otherwise.
    (function () {
        var modalidad = document.getElementById('modalidad-select');
        var emparejamiento = document.getElementById('emparejamiento-select');
        if (!modalidad || !emparejamiento) { return; }
        function sync() { emparejamiento.disabled = modalidad.value !== 'DOBLES'; }
        modalidad.addEventListener('change', sync);
        sync();
    })();
</script>
