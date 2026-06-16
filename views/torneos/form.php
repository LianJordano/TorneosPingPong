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
?>
<section class="page-head">
    <h1><?= $torneo ? 'Editar torneo' : 'Nuevo torneo' ?></h1>
    <a class="btn btn--ghost btn--sm" href="<?= $torneo ? '/torneos/' . e((string) $torneo['id']) : '/torneos' ?>">Volver</a>
</section>

<section class="card">
    <form method="post" action="<?= e($action) ?>" novalidate>
        <?= csrf_field() ?>

        <h2>Datos generales</h2>
        <div class="form-grid">
            <label class="field field--wide">
                <span>Nombre *</span>
                <input type="text" name="nombre" value="<?= $val('nombre') ?>" required>
                <?php if (isset($errors['nombre'])): ?><em class="field__error"><?= e($errors['nombre']) ?></em><?php endif; ?>
            </label>
            <label class="field field--wide">
                <span>Descripción</span>
                <textarea name="descripcion" rows="2"><?= $val('descripcion') ?></textarea>
            </label>
            <label class="field">
                <span>Lugar</span>
                <input type="text" name="lugar" value="<?= $val('lugar') ?>">
            </label>
            <label class="field">
                <span>Fecha de inicio</span>
                <input type="date" name="fecha_inicio" value="<?= $val('fecha_inicio') ?>">
                <?php if (isset($errors['fecha_inicio'])): ?><em class="field__error"><?= e($errors['fecha_inicio']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Fecha de fin</span>
                <input type="date" name="fecha_fin" value="<?= $val('fecha_fin') ?>">
                <?php if (isset($errors['fecha_fin'])): ?><em class="field__error"><?= e($errors['fecha_fin']) ?></em><?php endif; ?>
            </label>
        </div>

        <h2>Modalidad</h2>
        <div class="form-grid">
            <label class="field">
                <span>Modalidad de juego</span>
                <select name="modalidad" id="modalidad-select">
                    <option value="INDIVIDUAL" <?= $val('modalidad', 'INDIVIDUAL') === 'INDIVIDUAL' ? 'selected' : '' ?>>Individual</option>
                    <option value="DOBLES" <?= $val('modalidad', 'INDIVIDUAL') === 'DOBLES' ? 'selected' : '' ?>>Dobles (parejas)</option>
                </select>
                <?php if (isset($errors['modalidad'])): ?><em class="field__error"><?= e($errors['modalidad']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Formación de parejas <small class="muted">(solo dobles)</small></span>
                <select name="emparejamiento" id="emparejamiento-select">
                    <option value="MANUAL" <?= $val('emparejamiento', 'MANUAL') === 'MANUAL' ? 'selected' : '' ?>>Manual — se eligen al inscribir</option>
                    <option value="ALEATORIO" <?= $val('emparejamiento', 'MANUAL') === 'ALEATORIO' ? 'selected' : '' ?>>Aleatorio — las sortea el sistema</option>
                </select>
                <?php if (isset($errors['emparejamiento'])): ?><em class="field__error"><?= e($errors['emparejamiento']) ?></em><?php endif; ?>
            </label>
        </div>
        <script>
            (function () {
                var modalidad = document.getElementById('modalidad-select');
                var emparejamiento = document.getElementById('emparejamiento-select');
                function sync() { emparejamiento.disabled = modalidad.value !== 'DOBLES'; }
                modalidad.addEventListener('change', sync);
                sync();
            })();
        </script>

        <h2>Formato de juego</h2>
        <div class="form-grid">
            <label class="field">
                <span>Sets en la liga</span>
                <select name="formato_sets_liga">
                    <?php foreach ($formatos as $f): ?>
                        <option value="<?= e((string) $f) ?>" <?= $val('formato_sets_liga', '3') === (string) $f ? 'selected' : '' ?>><?= e($formatoLabel($f)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['formato_sets_liga'])): ?><em class="field__error"><?= e($errors['formato_sets_liga']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Sets en el bracket</span>
                <select name="formato_sets_bracket">
                    <?php foreach ($formatos as $f): ?>
                        <option value="<?= e((string) $f) ?>" <?= $val('formato_sets_bracket', '5') === (string) $f ? 'selected' : '' ?>><?= e($formatoLabel($f)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['formato_sets_bracket'])): ?><em class="field__error"><?= e($errors['formato_sets_bracket']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Puntos por set</span>
                <input type="number" name="puntos_por_set" min="1" value="<?= $val('puntos_por_set', '11') ?>">
                <?php if (isset($errors['puntos_por_set'])): ?><em class="field__error"><?= e($errors['puntos_por_set']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Diferencia mínima</span>
                <input type="number" name="diferencia_minima" min="1" value="<?= $val('diferencia_minima', '2') ?>">
                <?php if (isset($errors['diferencia_minima'])): ?><em class="field__error"><?= e($errors['diferencia_minima']) ?></em><?php endif; ?>
            </label>
        </div>

        <h2>Clasificación y premios</h2>
        <div class="form-grid">
            <label class="field">
                <span>Puntos por victoria</span>
                <input type="number" name="puntos_por_victoria" min="1" value="<?= $val('puntos_por_victoria', '1') ?>">
                <?php if (isset($errors['puntos_por_victoria'])): ?><em class="field__error"><?= e($errors['puntos_por_victoria']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Clasificados al bracket</span>
                <input type="number" name="num_clasificados" min="2" value="<?= $val('num_clasificados', '8') ?>">
                <?php if (isset($errors['num_clasificados'])): ?><em class="field__error"><?= e($errors['num_clasificados']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Posiciones premiadas</span>
                <input type="number" name="posiciones_premiadas" min="1" value="<?= $val('posiciones_premiadas', '3') ?>">
                <?php if (isset($errors['posiciones_premiadas'])): ?><em class="field__error"><?= e($errors['posiciones_premiadas']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Cuota de inscripción</span>
                <input type="number" step="0.01" name="cuota_inscripcion" value="<?= $val('cuota_inscripcion') ?>">
                <?php if (isset($errors['cuota_inscripcion'])): ?><em class="field__error"><?= e($errors['cuota_inscripcion']) ?></em><?php endif; ?>
            </label>
            <label class="field">
                <span>Pozo total</span>
                <input type="number" step="0.01" name="pozo_total" value="<?= $val('pozo_total') ?>">
                <?php if (isset($errors['pozo_total'])): ?><em class="field__error"><?= e($errors['pozo_total']) ?></em><?php endif; ?>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $torneo ? 'Guardar cambios' : 'Crear torneo' ?></button>
            <a class="btn btn--ghost" href="<?= $torneo ? '/torneos/' . e((string) $torneo['id']) : '/torneos' ?>">Cancelar</a>
        </div>
    </form>
</section>
