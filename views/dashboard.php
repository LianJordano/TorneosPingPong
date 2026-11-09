<?php
/** @var int $totalTorneos */
/** @var int $torneosActivos */
/** @var int $totalPersonas */
/** @var list<array<string,mixed>> $ultimos */
/** @var class-string<\PingPong\Domain\EstadoTorneo> $EstadoTorneo */
/** @var class-string<\PingPong\Domain\Modalidad> $Modalidad */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$nombreUsuario = trim(explode(' ', trim((string) ($authUser['nombre'] ?? '')))[0]);
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 20 ? 'Buenas tardes' : 'Buenas noches');
?>
<section class="hero dashboard-hero">
    <span class="eyebrow">Panel</span>
    <h1><?= e($saludo) ?><?= $nombreUsuario !== '' ? ', ' . e($nombreUsuario) : '' ?></h1>
    <p>
        <?php if ($torneosActivos > 0): ?>
            Hay <?= e((string) $torneosActivos) ?> <?= $torneosActivos === 1 ? 'torneo en marcha' : 'torneos en marcha' ?>. Entrá para cargar resultados o revisar la tabla.
        <?php else: ?>
            No hay torneos en marcha en este momento.
        <?php endif; ?>
    </p>
    <div class="actions mt-4">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="/torneos/nuevo"><?= icon('plus') ?> Nuevo torneo</a>
        <?php endif; ?>
        <a class="btn" href="/torneos"><?= icon('trophy') ?> Ver torneos</a>
    </div>
</section>

<div class="stats">
    <div class="stat">
        <span class="stat__label"><?= icon('activity') ?> En marcha</span>
        <span class="stat__num"><?= e((string) $torneosActivos) ?></span>
        <span class="stat__hint">Torneos sin finalizar ni cancelar</span>
    </div>
    <div class="stat">
        <span class="stat__label"><?= icon('trophy') ?> Torneos</span>
        <span class="stat__num"><?= e((string) $totalTorneos) ?></span>
        <span class="stat__hint">Total registrado</span>
    </div>
    <div class="stat">
        <span class="stat__label"><?= icon('users') ?> Jugadores</span>
        <span class="stat__num"><?= e((string) $totalPersonas) ?></span>
        <span class="stat__hint">Disponibles para inscribir</span>
    </div>
</div>

<section class="section" aria-labelledby="accesos-titulo">
    <div class="section-head">
        <h2 id="accesos-titulo">Accesos rápidos</h2>
    </div>
    <div class="grid-3">
        <a class="card card--interactive quick-link" href="/torneos">
            <span class="quick-link__icon"><?= icon('trophy') ?></span>
            <span class="quick-link__body">
                <strong>Torneos</strong>
                <small>Partidos, tabla de posiciones y bracket de cada torneo.</small>
            </span>
            <?= icon('chevron-right', 'quick-link__arrow') ?>
        </a>
        <a class="card card--interactive quick-link" href="/personas">
            <span class="quick-link__icon"><?= icon('users') ?></span>
            <span class="quick-link__body">
                <strong>Jugadores</strong>
                <small><?= $puedeGestionar ? 'Alta y edición de jugadores antes de inscribirlos.' : 'Listado y perfil de cada jugador.' ?></small>
            </span>
            <?= icon('chevron-right', 'quick-link__arrow') ?>
        </a>
        <a class="card card--interactive quick-link" href="/ranking">
            <span class="quick-link__icon"><?= icon('ranking') ?></span>
            <span class="quick-link__body">
                <strong>Ranking</strong>
                <small>Clasificación histórica acumulada entre torneos.</small>
            </span>
            <?= icon('chevron-right', 'quick-link__arrow') ?>
        </a>
    </div>
</section>

<section class="card card--flush" aria-labelledby="ultimos-titulo">
    <div class="card__header">
        <div>
            <h2 class="card__title" id="ultimos-titulo">Últimos torneos</h2>
            <p class="muted small mb-0">Los cinco más recientes.</p>
        </div>
        <a class="link" href="/torneos">Ver todos <?= icon('arrow-right', 'icon--sm') ?></a>
    </div>
    <?php if ($ultimos === []): ?>
        <div class="card-pad">
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('trophy') ?></span>
                <p class="empty-state__title">Todavía no hay torneos</p>
                <p><?= $puedeGestionar ? 'Creá el primero para empezar a inscribir jugadores.' : 'Cuando la organización cree uno, aparecerá aquí.' ?></p>
                <?php if ($puedeGestionar): ?>
                    <div class="actions"><a class="btn btn--primary" href="/torneos/nuevo"><?= icon('plus') ?> Nuevo torneo</a></div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr>
                        <th scope="col">Torneo</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Modalidad</th>
                        <th scope="col" class="num">Inscritos</th>
                        <th scope="col"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ultimos as $t): ?>
                    <?php
                    $estado = $EstadoTorneo::from($t['estado']);
                    $modalidad = $Modalidad::from($t['modalidad']);
                    $url = '/torneos/' . (string) $t['id'];
                    $fecha = !empty($t['fecha_inicio']) ? strtotime((string) $t['fecha_inicio']) : false;
                    ?>
                    <tr>
                        <td class="cell--primary">
                            <a class="table__title" href="<?= e($url) ?>"><?= e($t['nombre']) ?></a>
                            <?php if ($fecha !== false): ?>
                                <span class="table__sub">Inicio: <?= e(date('d/m/Y', $fecha)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado"><span class="badge badge--dot <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></td>
                        <td data-label="Modalidad">
                            <span class="badge <?= $modalidad->esDobles() ? 'badge--purple' : 'badge--cyan' ?>">
                                <?= icon($modalidad->esDobles() ? 'users' : 'user', 'icon--sm') ?> <?= e($modalidad->label()) ?>
                            </span>
                        </td>
                        <td data-label="Inscritos" class="num"><?= e((string) ($t['inscritos'] ?? 0)) ?></td>
                        <td class="actions-cell text-right">
                            <a class="btn btn--ghost btn--sm" href="<?= e($url) ?>">Abrir<span class="sr-only"> <?= e($t['nombre']) ?></span> <?= icon('chevron-right', 'icon--sm') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
