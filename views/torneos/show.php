<?php
/** @var array<string,mixed> $torneo */
/** @var \PingPong\Domain\EstadoTorneo $estado */
/** @var \PingPong\Domain\Modalidad $modalidad */
/** @var \PingPong\Domain\Emparejamiento $emparejamiento */
/** @var list<\PingPong\Domain\EstadoTorneo> $transiciones */
/** @var list<array<string,mixed>> $inscripciones */
/** @var list<array<string,mixed>> $disponibles */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$tid = (string) $torneo['id'];
$formatoLabel = static fn (int $n): string => $n === 1 ? '1 set' : "Mejor de {$n}";
$esDobles = $modalidad->esDobles();
$esParejaManual = $esDobles && !$emparejamiento->esAleatorio();
$esParejaAleatoria = $esDobles && $emparejamiento->esAleatorio();
$rotuloParticipante = $esDobles ? 'parejas' : 'jugadores';
?>
<section class="page-head page-head--hero tournament-hero">
    <div>
        <span class="eyebrow">Centro del torneo</span>
        <h1><?= e($torneo['nombre']) ?> <span class="badge <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></h1>
        <?php if (!empty($torneo['descripcion'])): ?><p class="muted"><?= e($torneo['descripcion']) ?></p><?php endif; ?>
        <div class="meta-row">
            <span class="meta-pill">📍 <?= e($torneo['lugar'] ?? 'Lugar pendiente') ?></span>
            <span class="meta-pill">🏓 Mesa única</span>
            <span class="meta-pill"><?= $esDobles ? '👥👥' : '👤' ?> <?= e($modalidad->label()) ?><?= $esDobles ? ' · ' . e($emparejamiento->esAleatorio() ? 'sorteo' : 'manual') : '' ?></span>
            <span class="meta-pill">👥 <?= e((string) count($inscripciones)) ?> <?= e($rotuloParticipante) ?></span>
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
            <a class="btn btn--primary btn--sm" href="/torneos/<?= e($tid) ?>/partidos">🏓 Operar partidos</a>
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
        <h2><?= $esDobles ? 'Parejas inscritas' : 'Inscritos' ?> (<?= e((string) count($inscripciones)) ?>)</h2>

        <?php
        $opcionesPersona = static function (array $disponibles, string $placeholder): string {
            $html = '<option value="">' . e($placeholder) . '</option>';
            foreach ($disponibles as $d) {
                $area = !empty($d['area']) ? ' (' . e($d['area']) . ')' : '';
                $html .= '<option value="' . e((string) $d['id']) . '">'
                    . e($d['apellido']) . ', ' . e($d['nombre']) . $area . '</option>';
            }
            return $html;
        };
        ?>

        <?php if ($puedeGestionar && $estado->permiteInscripciones()): ?>
            <?php if ($disponibles === [] || ($esParejaManual && count($disponibles) < 2)): ?>
                <p class="muted">No quedan jugadores suficientes por inscribir.</p>
            <?php elseif ($esParejaManual): ?>
                <form method="post" action="/torneos/<?= e($tid) ?>/inscripciones" class="inline-form">
                    <?= csrf_field() ?>
                    <select name="persona_id" required><?= $opcionesPersona($disponibles, '— Jugador 1 —') ?></select>
                    <select name="persona_id_2" required><?= $opcionesPersona($disponibles, '— Jugador 2 —') ?></select>
                    <button class="btn btn--primary btn--sm" type="submit">Inscribir pareja</button>
                </form>
                <p class="hint">Elegí los DOS integrantes de la pareja.</p>
            <?php else: ?>
                <form method="post" action="/torneos/<?= e($tid) ?>/inscripciones" class="inscribir-form" data-pick>
                    <?= csrf_field() ?>
                    <select name="persona_ids[]" multiple data-ts placeholder="Buscá y elegí jugadores…" autocomplete="off">
                        <?php foreach ($disponibles as $d): ?>
                            <?php $area = !empty($d['area']) ? ' (' . $d['area'] . ')' : ''; ?>
                            <option value="<?= e((string) $d['id']) ?>"><?= e($d['apellido'] . ', ' . $d['nombre'] . $area) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="inscribir-form__acciones">
                        <button class="btn btn--ghost btn--sm" type="button" data-ts-all>Seleccionar todos</button>
                        <button class="btn btn--ghost btn--sm" type="button" data-ts-none hidden>Limpiar</button>
                        <button class="btn btn--primary btn--sm" type="submit" data-ts-submit>Inscribir seleccionados</button>
                    </div>
                </form>
                <p class="hint">
                    Buscá y marcá uno, varios o <strong>todos</strong> los jugadores, y inscribilos de un saque.
                    <?php if ($esParejaAleatoria): ?>Las parejas se sortean al cerrar la inscripción.<?php endif; ?>
                </p>
            <?php endif; ?>
        <?php elseif ($puedeGestionar): ?>
            <p class="hint">Las inscripciones están <strong>cerradas</strong> en el estado actual.</p>
        <?php endif; ?>

        <?php if ($puedeGestionar && $esParejaAleatoria && $estado === \PingPong\Domain\EstadoTorneo::INSCRIPCION_CERRADA): ?>
            <form method="post" action="/torneos/<?= e($tid) ?>/sortear-parejas" class="inline"
                  onsubmit="return confirm('¿Sortear las parejas al azar entre los inscriptos individuales?');">
                <?= csrf_field() ?>
                <button class="btn btn--sm" type="submit">🎲 Sortear parejas</button>
            </form>
        <?php endif; ?>

        <?php if ($inscripciones === []): ?>
            <p class="muted">Todavía no hay inscritos.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= $esDobles ? 'Pareja' : 'Jugador' ?></th><th>Área</th><th>Pago</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($inscripciones as $i): ?>
                        <tr>
                            <td><?= e($i['label']) ?></td>
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

<?php if ($puedeGestionar && $estado->permiteInscripciones() && !$esParejaManual && $disponibles !== []): ?>
<!-- Tom Select: multiselect con chips/buscador. Se carga sólo en esta vista. -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
// Inscripción múltiple con Tom Select. El <select multiple name="persona_ids[]">
// es la fuente de verdad; Tom Select sólo lo embellece. Si el script no carga
// (offline / CDN caído), queda el <select> nativo y la inscripción funciona igual.
(function () {
    var el = document.querySelector('[data-ts]');
    if (!el || typeof TomSelect === 'undefined') return;

    var form   = el.closest('form');
    var submit = form.querySelector('[data-ts-submit]');
    var btnAll = form.querySelector('[data-ts-all]');
    var btnNone = form.querySelector('[data-ts-none]');
    var base   = submit.textContent;

    var ts = new TomSelect(el, {
        plugins: ['remove_button'],
        maxItems: null,
        hideSelected: true,
        closeAfterSelect: false,
        placeholder: el.getAttribute('placeholder'),
        sortField: { field: 'text', direction: 'asc' },
        render: {
            no_results: function () { return '<div class="no-results">Sin coincidencias.</div>'; }
        }
    });

    function refresh() {
        var n = ts.items.length;
        submit.textContent = n > 0 ? base + ' (' + n + ')' : base;
        submit.disabled = n === 0;
        btnNone.hidden = n === 0;
        var total = Object.keys(ts.options).length;
        btnAll.disabled = n >= total;
    }

    ts.on('change', refresh);

    btnAll.addEventListener('click', function () {
        ts.setValue(Object.keys(ts.options), false);
        ts.close();
    });
    btnNone.addEventListener('click', function () {
        ts.clear();
    });

    refresh();
})();
</script>
<?php endif; ?>
