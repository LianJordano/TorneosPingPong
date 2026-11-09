<?php
/** @var int $totalAreas */
/** @var int $totalNiveles */
/** @var array{creados:int,actualizados:int,areasCreadas:list<string>,nivelesCreados:list<string>,errores:list<array{fila:int,motivo:string}>,procesadas:int}|null $resultadoCarga */
$resultadoCarga = $resultadoCarga ?? null;
$columnasCarga = [
    ['nombre', 'Obligatoria'],
    ['apellido', 'Obligatoria'],
    ['identificacion', 'Opcional · si ya existe, el jugador se actualiza'],
    ['area', 'Opcional · se crea si no existe'],
    ['nivel', 'Opcional · se crea si no existe'],
    ['email', 'Opcional'],
    ['telefono', 'Opcional'],
];
?>
<header class="page-head">
    <div>
        <span class="eyebrow">Administración</span>
        <h1>Configuración</h1>
        <p>Catálogos, usuarios y carga masiva de jugadores.</p>
    </div>
</header>

<section class="section" aria-labelledby="config-general">
    <h2 class="sr-only" id="config-general">Secciones</h2>
    <div class="grid-3">
        <a class="card card--interactive settings-card" href="/areas">
            <span class="settings-card__icon"><?= icon('layers') ?></span>
            <span class="settings-card__body">
                <strong class="settings-card__title">Áreas</strong>
                <small>Sectores, equipos o departamentos para agrupar jugadores.</small>
                <span class="badge badge--gray"><?= e((string) $totalAreas) ?> <?= $totalAreas === 1 ? 'área' : 'áreas' ?></span>
            </span>
            <?= icon('chevron-right', 'settings-card__arrow') ?>
        </a>
        <a class="card card--interactive settings-card" href="/niveles">
            <span class="settings-card__icon"><?= icon('target') ?></span>
            <span class="settings-card__body">
                <strong class="settings-card__title">Niveles</strong>
                <small>Escala para clasificar nivel competitivo o experiencia.</small>
                <span class="badge badge--gray"><?= e((string) $totalNiveles) ?> <?= $totalNiveles === 1 ? 'nivel' : 'niveles' ?></span>
            </span>
            <?= icon('chevron-right', 'settings-card__arrow') ?>
        </a>
        <a class="card card--interactive settings-card" href="/configuracion/usuarios">
            <span class="settings-card__icon"><?= icon('shield') ?></span>
            <span class="settings-card__body">
                <strong class="settings-card__title">Usuarios y roles</strong>
                <small>Asigná Administrador, Anotador o Espectador a cada usuario.</small>
                <span class="badge badge--gray">Control de acceso</span>
            </span>
            <?= icon('chevron-right', 'settings-card__arrow') ?>
        </a>
    </div>
</section>

<section class="card" aria-labelledby="carga-titulo">
    <div class="card__header">
        <div>
            <span class="eyebrow">Carga masiva</span>
            <h2 class="card__title" id="carga-titulo">Importar jugadores desde Excel</h2>
            <p class="muted small mb-0">Crea o actualiza jugadores en lote a partir de un archivo .xlsx.</p>
        </div>
        <a class="btn btn--secondary" href="/configuracion/jugadores/plantilla"><?= icon('download') ?> Descargar plantilla</a>
    </div>

    <div class="grid-2">
        <div>
            <h3 class="import-format__title">Formato esperado</h3>
            <p class="muted small">La primera fila es el encabezado. El orden de las columnas no importa y se aceptan sinónimos (por ejemplo «correo» o «documento»).</p>
            <dl class="import-format">
                <?php foreach ($columnasCarga as [$columna, $detalle]): ?>
                    <div class="import-format__row">
                        <dt><code><?= e($columna) ?></code></dt>
                        <dd><?= e($detalle) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>

        <form method="post" action="/configuracion/jugadores/importar" enctype="multipart/form-data" class="form-panel">
            <?= csrf_field() ?>
            <div class="field">
                <label for="carga-archivo">Archivo Excel <span class="req" aria-hidden="true">*</span></label>
                <input type="file" id="carga-archivo" name="archivo" accept=".xlsx,.xls" required aria-describedby="carga-archivo-hint">
                <span class="field__hint" id="carga-archivo-hint">Formato .xlsx. Si la identificación ya existe, el jugador se actualiza; si no, se crea.</span>
            </div>
            <button type="submit" class="btn btn--primary btn--block"><?= icon('upload') ?> Procesar archivo</button>
        </form>
    </div>

    <?php if ($resultadoCarga !== null): ?>
        <div class="import-result" aria-labelledby="carga-resultado">
            <h3 id="carga-resultado">Resultado de la última carga</h3>
            <div class="stats stats--compact">
                <div class="stat">
                    <span class="stat__label"><?= icon('user-plus') ?> Creados</span>
                    <span class="stat__num"><?= e((string) $resultadoCarga['creados']) ?></span>
                </div>
                <div class="stat">
                    <span class="stat__label"><?= icon('refresh') ?> Actualizados</span>
                    <span class="stat__num"><?= e((string) $resultadoCarga['actualizados']) ?></span>
                </div>
                <div class="stat">
                    <span class="stat__label"><?= icon('list') ?> Filas procesadas</span>
                    <span class="stat__num"><?= e((string) $resultadoCarga['procesadas']) ?></span>
                </div>
                <div class="stat">
                    <span class="stat__label"><?= icon('alert') ?> Con problemas</span>
                    <span class="stat__num<?= $resultadoCarga['errores'] !== [] ? ' text-danger' : '' ?>"><?= e((string) count($resultadoCarga['errores'])) ?></span>
                </div>
            </div>

            <?php if ($resultadoCarga['areasCreadas'] !== [] || $resultadoCarga['nivelesCreados'] !== []): ?>
                <div class="hint mb-4">
                    <?= icon('info') ?>
                    <div>
                        <?php if ($resultadoCarga['areasCreadas'] !== []): ?>
                            <p class="mb-0"><strong>Áreas nuevas:</strong> <?= e(implode(', ', $resultadoCarga['areasCreadas'])) ?>.</p>
                        <?php endif; ?>
                        <?php if ($resultadoCarga['nivelesCreados'] !== []): ?>
                            <p class="mb-0"><strong>Niveles nuevos:</strong> <?= e(implode(', ', $resultadoCarga['nivelesCreados'])) ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($resultadoCarga['errores'] !== []): ?>
                <h4 class="import-result__subtitle">Filas con problemas</h4>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <thead>
                            <tr><th scope="col" class="num">Fila</th><th scope="col">Problema</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resultadoCarga['errores'] as $error): ?>
                            <tr>
                                <td data-label="Fila" class="num"><?= e((string) $error['fila']) ?></td>
                                <td data-label="Problema"><?= e($error['motivo']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-success small mb-0"><?= icon('check-circle', 'icon--sm') ?> Todas las filas se procesaron sin problemas.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
