<?php
/** @var int $totalAreas */
/** @var int $totalNiveles */
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
</div>
