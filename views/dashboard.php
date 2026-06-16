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
        <span class="eyebrow">Tournament command center</span>
        <h1>Panel de control competitivo</h1>
        <p>Dominá toda la operación del torneo desde una interfaz más clara, rápida y con presencia de producto serio.</p>
    </div>
    <?php if ($puedeGestionar): ?>
        <div class="actions">
            <a class="btn btn--primary" href="/torneos/nuevo">Nuevo torneo</a>
            <a class="btn" href="/personas/nueva">Nuevo jugador</a>
        </div>
    <?php endif; ?>
</section>

<div class="stats">
    <div class="stat"><span class="stat__label">Torneos</span><span class="stat__num"><?= e((string) $totalTorneos) ?></span><span class="stat__hint">Calendario total registrado</span></div>
    <div class="stat"><span class="stat__label">Activos</span><span class="stat__num"><?= e((string) $torneosActivos) ?></span><span class="stat__hint">Competencia en curso</span></div>
    <div class="stat"><span class="stat__label">Jugadores</span><span class="stat__num"><?= e((string) $totalPersonas) ?></span><span class="stat__hint">Base total disponible</span></div>
</div>

<section class="card">
    <div class="section-head">
        <div>
            <span class="eyebrow">Actividad reciente</span>
            <h2>Últimos torneos</h2>
        </div>
    </div>
    <?php if ($ultimos === []): ?>
        <p class="empty-state">Todavía no hay torneos. <?= $puedeGestionar ? 'Creá el primero y arrancá la competencia.' : '' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Estado</th><th>Inscritos</th><th></th></tr></thead>
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
                        <td><?= e((string) ($t['inscritos'] ?? 0)) ?></td>
                        <td class="text-right"><a class="link" href="/torneos/<?= e((string) $t['id']) ?>">Ver →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
