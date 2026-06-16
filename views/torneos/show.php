<?php
/** @var array<string,mixed> $torneo */
/** @var \PingPong\Domain\EstadoTorneo $estado */
/** @var list<\PingPong\Domain\EstadoTorneo> $transiciones */
/** @var list<array<string,mixed>> $inscripciones */
/** @var list<array<string,mixed>> $disponibles */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$tid = (string) $torneo['id'];
$formatoLabel = static fn (int $n): string => $n === 1 ? '1 set' : "Mejor de {$n}";
?>
<section class="page-head page-head--hero tournament-hero">
    <div>
        <span class="eyebrow">Tournament overview</span>
        <h1><?= e($torneo['nombre']) ?> <span class="badge <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></h1>
        <?php if (!empty($torneo['descripcion'])): ?><p class="muted"><?= e($torneo['descripcion']) ?></p><?php endif; ?>
        <div class="meta-row">
            <span class="meta-pill">📍 <?= e($torneo['lugar'] ?? 'Lugar pendiente') ?></span>
            <span class="meta-pill">👥 <?= e((string) count($inscripciones)) ?> inscriptos</span>
            <span class="meta-pill">🏁 <?= e((string) $torneo['num_clasificados']) ?> clasificados</span>
        </div>
    </div>
    <a class="btn btn--ghost btn--sm" href="/torneos">Volver</a>
</section>

<?php
$enLigaOmas = in_array($estado, [\PingPong\Domain\EstadoTorneo::FASE_LIGA, \PingPong\Domain\EstadoTorneo::FASE_ELIMINATORIA, \PingPong\Domain\EstadoTorneo::FINALIZADO], true);
?>
<?php if ($enLigaOmas || ($puedeGestionar && $estado === \PingPong\Domain\EstadoTorneo::INSCRIPCION_CERRADA)): ?>
    <section class="card liga-nav">
        <?php $enBracket = in_array($estado, [\PingPong\Domain\EstadoTorneo::FASE_ELIMINATORIA, \PingPong\Domain\EstadoTorneo::FINALIZADO], true); ?>
        <?php if ($enLigaOmas): ?>
            <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/partidos">📋 Partidos</a>
            <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/tabla">🏆 Tabla de posiciones</a>
        <?php endif; ?>
        <?php if ($enBracket): ?>
            <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/bracket">🎯 Bracket</a>
            <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/podio">🥇 Podio</a>
        <?php endif; ?>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>/premios">💰 Premios</a>
            <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>/auditoria">📜 Auditoría</a>
        <?php endif; ?>
        <a class="btn btn--ghost btn--sm" href="/publico/torneos/<?= e($tid) ?>" target="_blank" rel="noopener">📺 Vista pública</a>

        <?php if ($puedeGestionar && $estado === \PingPong\Domain\EstadoTorneo::INSCRIPCION_CERRADA): ?>
            <?php if (count($inscripciones) >= 2): ?>
                <form method="post" action="/torneos/<?= e($tid) ?>/fixture" class="inline"
                      onsubmit="return confirm('¿Generar el fixture round-robin y comenzar la liga? No se podrán editar las inscripciones después.');">
                    <?= csrf_field() ?>
                    <button class="btn btn--primary btn--sm" type="submit">⚙️ Generar fixture y comenzar liga</button>
                </form>
            <?php else: ?>
                <span class="hint">Se necesitan al menos 2 inscritos para generar el fixture.</span>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="cols">
    <section class="card">
        <h2>Configuración</h2>
        <dl class="datalist">
            <dt>Lugar</dt><dd><?= e($torneo['lugar'] ?? '—') ?></dd>
            <dt>Fechas</dt><dd><?= e($torneo['fecha_inicio'] ?? '—') ?> <?= !empty($torneo['fecha_fin']) ? '→ ' . e($torneo['fecha_fin']) : '' ?></dd>
            <dt>Sets liga</dt><dd><?= e($formatoLabel((int) $torneo['formato_sets_liga'])) ?></dd>
            <dt>Sets bracket</dt><dd><?= e($formatoLabel((int) $torneo['formato_sets_bracket'])) ?></dd>
            <dt>Puntos por set</dt><dd><?= e((string) $torneo['puntos_por_set']) ?> (dif. <?= e((string) $torneo['diferencia_minima']) ?>)</dd>
            <dt>Puntos por victoria</dt><dd><?= e((string) $torneo['puntos_por_victoria']) ?></dd>
            <dt>Clasificados</dt><dd><?= e((string) $torneo['num_clasificados']) ?></dd>
            <dt>Posiciones premiadas</dt><dd><?= e((string) $torneo['posiciones_premiadas']) ?></dd>
            <dt>Cuota / Pozo</dt><dd><?= e($torneo['cuota_inscripcion'] ?? '—') ?> / <?= e($torneo['pozo_total'] ?? '—') ?></dd>
        </dl>

        <?php if ($puedeGestionar): ?>
            <div class="actions actions--wrap">
                <?php if ($estado->permiteEditarConfig()): ?>
                    <a class="btn btn--sm" href="/torneos/<?= e($tid) ?>/editar">Editar</a>
                <?php endif; ?>

                <?php foreach ($transiciones as $destino): ?>
                    <form method="post" action="/torneos/<?= e($tid) ?>/estado" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="estado" value="<?= e($destino->value) ?>">
                        <button class="btn btn--sm <?= $destino->value === 'CANCELADO' ? 'btn--danger' : 'btn--primary' ?>" type="submit">
                            → <?= e($destino->label()) ?>
                        </button>
                    </form>
                <?php endforeach; ?>

                <form method="post" action="/torneos/<?= e($tid) ?>/duplicar" class="inline">
                    <?= csrf_field() ?>
                    <button class="btn btn--sm btn--ghost" type="submit">Duplicar</button>
                </form>
                <form method="post" action="/torneos/<?= e($tid) ?>/archivar" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="archivar" value="<?= (int) ($torneo['archivado'] ?? 0) === 1 ? '0' : '1' ?>">
                    <button class="btn btn--sm btn--ghost" type="submit"><?= (int) ($torneo['archivado'] ?? 0) === 1 ? 'Desarchivar' : 'Archivar' ?></button>
                </form>
                <form method="post" action="/torneos/<?= e($tid) ?>/eliminar" class="inline"
                      onsubmit="return confirm('¿Eliminar el torneo y todos sus datos? Esta acción no se puede deshacer.');">
                    <?= csrf_field() ?>
                    <button class="link link--danger" type="submit">Eliminar</button>
                </form>
            </div>

            <?php if (!$estado->permiteEditarConfig() && !$estado->esTerminal()): ?>
                <p class="hint">ℹ️ El avance a la <strong>fase de liga</strong> se habilita al generar el fixture (Fase 2).</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Inscritos (<?= e((string) count($inscripciones)) ?>)</h2>

        <?php if ($puedeGestionar && $estado->permiteInscripciones()): ?>
            <?php if ($disponibles === []): ?>
                <p class="muted">No quedan jugadores activos por inscribir.</p>
            <?php else: ?>
                <form method="post" action="/torneos/<?= e($tid) ?>/inscripciones" class="inline-form">
                    <?= csrf_field() ?>
                    <select name="persona_id" required>
                        <option value="">— Elegí un jugador —</option>
                        <?php foreach ($disponibles as $d): ?>
                            <option value="<?= e((string) $d['id']) ?>"><?= e($d['apellido']) ?>, <?= e($d['nombre']) ?><?= !empty($d['area']) ? ' (' . e($d['area']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--primary btn--sm" type="submit">Inscribir</button>
                </form>
            <?php endif; ?>
        <?php elseif ($puedeGestionar): ?>
            <p class="hint">Las inscripciones están <strong>cerradas</strong> en el estado actual.</p>
        <?php endif; ?>

        <?php if ($inscripciones === []): ?>
            <p class="muted">Todavía no hay inscritos.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Jugador</th><th>Área</th><th>Pago</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($inscripciones as $i): ?>
                        <tr>
                            <td><?= e($i['apellido']) ?>, <?= e($i['nombre']) ?></td>
                            <td><?= e($i['area'] ?? '—') ?></td>
                            <td>
                                <?php if ($puedeGestionar): ?>
                                    <form method="post" action="/torneos/<?= e($tid) ?>/inscripciones/<?= e((string) $i['id']) ?>/pago" class="inline">
                                        <?= csrf_field() ?>
                                        <button class="badge <?= (int) $i['pago_realizado'] === 1 ? 'badge--green' : 'badge--gray' ?>" type="submit">
                                            <?= (int) $i['pago_realizado'] === 1 ? 'Pagado' : 'Pendiente' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge <?= (int) $i['pago_realizado'] === 1 ? 'badge--green' : 'badge--gray' ?>">
                                        <?= (int) $i['pago_realizado'] === 1 ? 'Pagado' : 'Pendiente' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($puedeGestionar && $estado->permiteInscripciones()): ?>
                                    <form method="post" action="/torneos/<?= e($tid) ?>/inscripciones/<?= e((string) $i['id']) ?>/eliminar" class="inline"
                                          onsubmit="return confirm('¿Desinscribir a este jugador?');">
                                        <?= csrf_field() ?>
                                        <button class="link link--danger" type="submit">Quitar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
