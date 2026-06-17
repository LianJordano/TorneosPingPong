<?php
/** @var int $totalTorneos */
/** @var int $torneosActivos */
/** @var int $totalPersonas */
/** @var list<array<string,mixed>> $ultimos */
/** @var class-string<\PingPong\Domain\EstadoTorneo> $EstadoTorneo */
/** @var class-string<\PingPong\Domain\Modalidad> $Modalidad */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
?>
<section class="hero hero--dashboard">
    <div>
        <span class="eyebrow">Mesa única · operación simple</span>
        <h1>El torneo, claro y rápido</h1>
        <p>Una sola mesa, todos mirando lo mismo: quién juega ahora, qué falta cargar y cómo viene la competencia.</p>
    </div>
    <?php if ($puedeGestionar): ?>
        <div class="actions">
            <a class="btn btn--primary" href="/torneos/nuevo">Nuevo torneo</a>
            <a class="btn" href="/personas/nueva">Nuevo jugador</a>
        </div>
    <?php endif; ?>
</section>

<div class="stats">
    <div class="stat"><span class="stat__label">Torneos</span><span class="stat__num"><?= e((string) $totalTorneos) ?></span><span class="stat__hint">Historial interno registrado</span></div>
    <div class="stat"><span class="stat__label">En marcha</span><span class="stat__num"><?= e((string) $torneosActivos) ?></span><span class="stat__hint">Para seguir desde la mesa</span></div>
    <div class="stat"><span class="stat__label">Jugadores</span><span class="stat__num"><?= e((string) $totalPersonas) ?></span><span class="stat__hint">Compañeros listos para competir</span></div>
</div>

<section class="ops-grid" aria-label="Accesos rápidos para operar el torneo">
    <a class="ops-card" href="/torneos">
        <span class="ops-card__icon">🏓</span>
        <strong>Seguir torneo</strong>
        <small>Entrá al torneo activo y mirá partidos, tabla o bracket.</small>
    </a>
    <a class="ops-card" href="/personas">
        <span class="ops-card__icon">👥</span>
        <strong>Jugadores</strong>
        <small>Alta rápida para sumar a alguien antes de arrancar.</small>
    </a>
    <a class="ops-card" href="/glosario">
        <span class="ops-card__icon">📘</span>
        <strong>Reglas claras</strong>
        <small>Estados, desempates y conceptos sin fricción.</small>
    </a>
</section>

<section class="card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Acceso directo</span>
            <h2>Últimos torneos</h2>
        </div>
    </div>
    <?php if ($ultimos === []): ?>
        <p class="empty-state">Todavía no hay torneos. <?= $puedeGestionar ? 'Creá el primero y arrancá la competencia.' : '' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Estado</th><th class="hide-mobile">Inscritos</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($ultimos as $t): ?>
                    <?php
                    $estado = $EstadoTorneo::from($t['estado']);
                    $esDobles = $Modalidad::from($t['modalidad'])->esDobles();
                    ?>
                    <tr>
                        <td>
                            <a href="/torneos/<?= e((string) $t['id']) ?>"><?= e($t['nombre']) ?></a>
                            <span class="badge <?= $esDobles ? 'badge--purple' : 'badge--blue' ?>"><?= $esDobles ? '👥 Dobles' : '👤 Individual' ?></span>
                        </td>
                        <td><span class="badge <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></td>
                        <td class="hide-mobile"><?= e((string) ($t['inscritos'] ?? 0)) ?></td>
                        <td class="text-right"><a class="link" href="/torneos/<?= e((string) $t['id']) ?>">Ver →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
