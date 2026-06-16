<?php
/** @var int $totalTorneos */
/** @var int $torneosActivos */
/** @var int $totalPersonas */
/** @var list<array<string,mixed>> $ultimos */
/** @var class-string<\PingPong\Domain\EstadoTorneo> $EstadoTorneo */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
?>
<section class="page-head">
    <h1>Panel</h1>
    <?php if ($puedeGestionar): ?>
        <div class="actions">
            <a class="btn btn--primary" href="/torneos/nuevo">Nuevo torneo</a>
            <a class="btn" href="/personas/nueva">Nuevo jugador</a>
        </div>
    <?php endif; ?>
</section>

<div class="stats">
    <div class="stat"><span class="stat__num"><?= e((string) $totalTorneos) ?></span><span class="stat__label">Torneos</span></div>
    <div class="stat"><span class="stat__num"><?= e((string) $torneosActivos) ?></span><span class="stat__label">Activos</span></div>
    <div class="stat"><span class="stat__num"><?= e((string) $totalPersonas) ?></span><span class="stat__label">Jugadores</span></div>
</div>

<section class="card">
    <h2>Últimos torneos</h2>
    <?php if ($ultimos === []): ?>
        <p class="muted">Todavía no hay torneos. <?= $puedeGestionar ? 'Creá el primero.' : '' ?></p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Nombre</th><th>Estado</th><th>Inscritos</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ultimos as $t): ?>
                <?php $estado = $EstadoTorneo::from($t['estado']); ?>
                <tr>
                    <td><a href="/torneos/<?= e((string) $t['id']) ?>"><?= e($t['nombre']) ?></a></td>
                    <td><span class="badge <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></td>
                    <td><?= e((string) ($t['inscritos'] ?? 0)) ?></td>
                    <td class="text-right"><a class="link" href="/torneos/<?= e((string) $t['id']) ?>">Ver →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
