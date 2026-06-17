<?php
/** @var int $totalAreas */
/** @var int $totalNiveles */
/** @var array{creados:int,actualizados:int,areasCreadas:list<string>,nivelesCreados:list<string>,errores:list<array{fila:int,motivo:string}>,procesadas:int}|null $resultadoCarga */
$resultadoCarga = $resultadoCarga ?? null;
?>
<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">System settings</span>
            <h1>Configuraciones del sistema</h1>
            <p class="muted">Centralizá los catálogos base para que la organización sea más simétrica y fácil de mantener.</p>
            <div class="meta-row">
                <span class="meta-pill">🧩 Catálogos maestros</span>
                <span class="meta-pill">⚙️ Administración interna</span>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-card">
                <span class="hero-card__label">Estado de configuración</span>
                <strong><?= e((string) ($totalAreas + $totalNiveles)) ?> registros</strong>
                <small>Áreas y niveles disponibles para organizar la base de jugadores.</small>
            </div>
        </div>
    </div>
</section>

<div class="settings-grid">
    <section class="card settings-card">
        <span class="settings-card__eyebrow">Catálogo</span>
        <h2>Áreas</h2>
        <p class="muted">Definí sectores, equipos o departamentos para agrupar jugadores con consistencia.</p>
        <div class="meta-row">
            <span class="meta-pill meta-pill--light">📚 <?= e((string) $totalAreas) ?> áreas</span>
        </div>
        <div class="form-actions">
            <a class="btn btn--primary" href="/areas">Administrar áreas</a>
        </div>
    </section>

    <section class="card settings-card">
        <span class="settings-card__eyebrow">Catálogo</span>
        <h2>Niveles</h2>
        <p class="muted">Mantené una escala clara para clasificar nivel competitivo o experiencia.</p>
        <div class="meta-row">
            <span class="meta-pill meta-pill--light">🎯 <?= e((string) $totalNiveles) ?> niveles</span>
        </div>
        <div class="form-actions">
            <a class="btn btn--primary" href="/niveles">Administrar niveles</a>
        </div>
    </section>

    <section class="card settings-card">
        <span class="settings-card__eyebrow">Seguridad</span>
        <h2>Usuarios y roles</h2>
        <p class="muted">Asigná roles (Administrador, Anotador, Espectador) y revisá qué puede hacer cada uno.</p>
        <div class="meta-row">
            <span class="meta-pill meta-pill--light">🔐 Control de acceso</span>
        </div>
        <div class="form-actions">
            <a class="btn btn--primary" href="/configuracion/usuarios">Gestionar usuarios</a>
        </div>
    </section>
</div>

<section class="card settings-card settings-card--wide">
    <span class="settings-card__eyebrow">Carga masiva</span>
    <h2>Importar jugadores desde Excel</h2>
    <p class="muted">
        Subí un archivo <strong>.xlsx</strong> para crear o actualizar jugadores en lote.
        Las columnas <strong>nombre</strong> y <strong>apellido</strong> son obligatorias; el resto son opcionales.
        Si la <strong>identificación</strong> ya existe, el jugador se <strong>actualiza</strong>; si no, se <strong>crea</strong>.
        Las áreas y niveles que no existan se crean automáticamente y se asignan.
    </p>

    <div class="form-actions">
        <a class="btn btn--ghost" href="/configuracion/jugadores/plantilla">⬇️ Descargar plantilla</a>
    </div>

    <form method="post" action="/configuracion/jugadores/importar" enctype="multipart/form-data" class="form-grid form-grid--inline">
        <?= csrf_field() ?>
        <label class="field">
            <span>Archivo Excel (.xlsx)</span>
            <input type="file" name="archivo" accept=".xlsx,.xls" required>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Procesar archivo</button>
        </div>
    </form>

    <?php if ($resultadoCarga !== null): ?>
        <div class="import-result">
            <h3>Resultado de la última carga</h3>
            <div class="meta-row">
                <span class="meta-pill meta-pill--light">🟢 <?= e((string) $resultadoCarga['creados']) ?> creados</span>
                <span class="meta-pill meta-pill--light">🔄 <?= e((string) $resultadoCarga['actualizados']) ?> actualizados</span>
                <span class="meta-pill meta-pill--light">📋 <?= e((string) $resultadoCarga['procesadas']) ?> filas procesadas</span>
            </div>

            <?php if ($resultadoCarga['areasCreadas'] !== [] || $resultadoCarga['nivelesCreados'] !== []): ?>
                <p class="muted small">
                    <?php if ($resultadoCarga['areasCreadas'] !== []): ?>
                        Áreas nuevas: <?= e(implode(', ', $resultadoCarga['areasCreadas'])) ?>.
                    <?php endif; ?>
                    <?php if ($resultadoCarga['nivelesCreados'] !== []): ?>
                        Niveles nuevos: <?= e(implode(', ', $resultadoCarga['nivelesCreados'])) ?>.
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($resultadoCarga['errores'] !== []): ?>
                <table class="table">
                    <thead>
                        <tr><th>Fila</th><th>Problema</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resultadoCarga['errores'] as $error): ?>
                        <tr>
                            <td><?= e((string) $error['fila']) ?></td>
                            <td><?= e($error['motivo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
