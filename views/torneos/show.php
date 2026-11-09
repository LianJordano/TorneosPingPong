<?php
/** @var array<string,mixed> $torneo */
/** @var \PingPong\Domain\EstadoTorneo $estado */
/** @var \PingPong\Domain\Modalidad $modalidad */
/** @var \PingPong\Domain\Emparejamiento $emparejamiento */
/** @var list<\PingPong\Domain\EstadoTorneo> $transiciones */
/** @var list<array<string,mixed>> $inscripciones */
/** @var list<array<string,mixed>> $disponibles */

use PingPong\Domain\EstadoTorneo;

$puedeGestionar = $rol?->puedeGestionar() ?? false;
$tid = (string) $torneo['id'];
$base = '/torneos/' . $tid;
$formatoLabel = static fn (int $n): string => $n === 1 ? '1 set' : "Mejor de {$n}";
$esDobles = $modalidad->esDobles();
$esParejaManual = $esDobles && !$emparejamiento->esAleatorio();
$esParejaAleatoria = $esDobles && $emparejamiento->esAleatorio();

$archivado = (int) ($torneo['archivado'] ?? 0) === 1;
$totalInscritos = count($inscripciones);
$pagados = count(array_filter($inscripciones, static fn (array $i): bool => (int) $i['pago_realizado'] === 1));

$enLigaOmas = in_array($estado, [EstadoTorneo::FASE_LIGA, EstadoTorneo::FASE_ELIMINATORIA, EstadoTorneo::FINALIZADO], true);
$enBracket = in_array($estado, [EstadoTorneo::FASE_ELIMINATORIA, EstadoTorneo::FINALIZADO], true);
$muestraPublica = $enLigaOmas || $estado === EstadoTorneo::INSCRIPCION_CERRADA;
$hayParejasFormadas = array_filter($inscripciones, static fn (array $i): bool => !empty($i['persona_id_2'])) !== [];
$muestraParejas = $esParejaManual || ($esDobles && $hayParejasFormadas);
$rotuloParticipante = $muestraParejas ? 'parejas' : 'jugadores';
$puedeSortear = $puedeGestionar && $esParejaAleatoria && $estado === EstadoTorneo::INSCRIPCION_CERRADA;
$puedeQuitar = $puedeGestionar && $estado->permiteInscripciones();
$puedeFixture =$puedeGestionar && $estado === EstadoTorneo::INSCRIPCION_CERRADA && $totalInscritos >= 2;

$fecha = static function (?string $valor): string {
    if ($valor === null || $valor === '') {
        return '';
    }
    $ts = strtotime($valor);
    return $ts === false ? $valor : date('d/m/Y', $ts);
};
$inicio = $fecha($torneo['fecha_inicio'] ?? null);
$fin = $fecha($torneo['fecha_fin'] ?? null);
$fechas = $inicio === '' && $fin === '' ? '' : ($inicio !== '' ? $inicio : '—') . ($fin !== '' ? ' – ' . $fin : '');

$transicion = static function (array $lista, EstadoTorneo $destino): ?EstadoTorneo {
    return in_array($destino, $lista, true) ? $destino : null;
};
$etiquetaTransicion = static fn (EstadoTorneo $d): string => match ($d) {
    EstadoTorneo::INSCRIPCION_ABIERTA => 'Abrir inscripción',
    EstadoTorneo::INSCRIPCION_CERRADA => 'Cerrar inscripción',
    EstadoTorneo::CANCELADO           => 'Cancelar torneo',
    default                           => 'Pasar a ' . mb_strtolower($d->label()),
};
$iconoTransicion = static fn (EstadoTorneo $d): string => match ($d) {
    EstadoTorneo::INSCRIPCION_ABIERTA => 'play',
    EstadoTorneo::INSCRIPCION_CERRADA => 'lock',
    EstadoTorneo::CANCELADO           => 'x-circle',
    default                           => 'arrow-right',
};

/*
 * Next step: one obvious primary action per state.
 * type: 'estado' (POST /estado), 'fixture', 'sortear', 'link' or null.
 */
$siguiente = ['texto' => '', 'tipo' => null];
if ($puedeGestionar) {
    switch ($estado) {
        case EstadoTorneo::BORRADOR:
            $siguiente = ['texto' => 'Revisá la configuración y abrí la inscripción cuando esté listo.', 'tipo' => 'estado', 'destino' => $transicion($transiciones, EstadoTorneo::INSCRIPCION_ABIERTA)];
            break;
        case EstadoTorneo::INSCRIPCION_ABIERTA:
            $siguiente = $totalInscritos >= 2
                ? ['texto' => 'Cuando estén todos anotados, cerrá la inscripción para armar la liga.', 'tipo' => 'estado', 'destino' => $transicion($transiciones, EstadoTorneo::INSCRIPCION_CERRADA)]
                : ['texto' => 'Inscribí al menos 2 participantes para poder empezar.', 'tipo' => 'link', 'href' => '#inscripciones', 'label' => 'Inscribir jugadores', 'icon' => 'user-plus'];
            break;
        case EstadoTorneo::INSCRIPCION_CERRADA:
            if ($puedeSortear && !$hayParejasFormadas && $totalInscritos > 0) {
                $siguiente = ['texto' => 'Sorteá las parejas antes de generar el fixture.', 'tipo' => 'sortear'];
            } elseif ($puedeFixture) {
                $siguiente = ['texto' => 'Generá el fixture para comenzar la fase de liga.', 'tipo' => 'fixture'];
            } else {
                $siguiente = ['texto' => 'Se necesitan al menos 2 inscritos para generar el fixture. Reabrí la inscripción para sumar más.', 'tipo' => 'estado', 'destino' => $transicion($transiciones, EstadoTorneo::INSCRIPCION_ABIERTA)];
            }
            break;
        case EstadoTorneo::FASE_LIGA:
            $siguiente = ['texto' => 'Cargá los resultados. Al terminar la liga, generá el bracket desde la tabla de posiciones.', 'tipo' => 'link', 'href' => $base . '/partidos', 'label' => 'Operar partidos', 'icon' => 'scoreboard'];
            break;
        case EstadoTorneo::FASE_ELIMINATORIA:
            $siguiente = ['texto' => 'Jugá el cuadro eliminatorio. Al definirse la final, finalizá el torneo desde el bracket.', 'tipo' => 'link', 'href' => $base . '/bracket', 'label' => 'Ir al bracket', 'icon' => 'bracket'];
            break;
        case EstadoTorneo::FINALIZADO:
            $siguiente = ['texto' => 'El torneo terminó. Revisá el podio y la premiación.', 'tipo' => 'link', 'href' => $base . '/podio', 'label' => 'Ver podio', 'icon' => 'podium'];
            break;
        case EstadoTorneo::CANCELADO:
            $siguiente = ['texto' => 'El torneo fue cancelado y no admite más cambios.', 'tipo' => null];
            break;
    }
} else {
    $siguiente = match ($estado) {
        EstadoTorneo::FASE_LIGA         => ['texto' => 'La liga está en juego.', 'tipo' => 'link', 'href' => $base . '/partidos', 'label' => 'Ver partidos', 'icon' => 'scoreboard'],
        EstadoTorneo::FASE_ELIMINATORIA => ['texto' => 'Se está jugando la fase eliminatoria.', 'tipo' => 'link', 'href' => $base . '/bracket', 'label' => 'Ver bracket', 'icon' => 'bracket'],
        EstadoTorneo::FINALIZADO        => ['texto' => 'El torneo terminó.', 'tipo' => 'link', 'href' => $base . '/podio', 'label' => 'Ver podio', 'icon' => 'podium'],
        EstadoTorneo::CANCELADO         => ['texto' => 'El torneo fue cancelado.', 'tipo' => null],
        default                         => ['texto' => 'La competencia todavía no empezó.', 'tipo' => null],
    };
}
if (($siguiente['tipo'] ?? null) === 'estado' && ($siguiente['destino'] ?? null) === null) {
    $siguiente['tipo'] = null;
}
$destinoPrincipal = ($siguiente['tipo'] ?? null) === 'estado' ? $siguiente['destino'] : null;

// Secondary state changes (the primary one and the cancel one are rendered elsewhere).
$otrasTransiciones = array_values(array_filter(
    $transiciones,
    static fn (EstadoTorneo $d): bool => $d !== $destinoPrincipal && $d !== EstadoTorneo::CANCELADO
));
$puedeCancelar = in_array(EstadoTorneo::CANCELADO, $transiciones, true);

$fixtureConfirm = '¿Generar el fixture round-robin y comenzar la liga? No se podrán editar las inscripciones después.';
$sorteoConfirm = '¿Sortear las parejas al azar entre los inscritos individuales?';

$tabs = [['href' => $base, 'label' => 'Resumen', 'icon' => 'home', 'current' => true]];
if ($enLigaOmas) {
    $tabs[] = ['href' => $base . '/partidos', 'label' => 'Partidos', 'icon' => 'scoreboard'];
    $tabs[] = ['href' => $base . '/tabla', 'label' => 'Tabla', 'icon' => 'table'];
}
if ($enBracket) {
    $tabs[] = ['href' => $base . '/bracket', 'label' => 'Bracket', 'icon' => 'bracket'];
    $tabs[] = ['href' => $base . '/podio', 'label' => 'Podio', 'icon' => 'podium'];
}
if ($puedeGestionar) {
    $tabs[] = ['href' => $base . '/premios', 'label' => 'Premios', 'icon' => 'gift'];
    $tabs[] = ['href' => $base . '/auditoria', 'label' => 'Auditoría', 'icon' => 'history'];
}
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><span aria-current="page"><?= e($torneo['nombre']) ?></span></li>
    </ol>
</nav>

<header class="hero torneo-hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Centro del torneo</span>
            <h1><?= e($torneo['nombre']) ?></h1>
            <?php if (!empty($torneo['descripcion'])): ?>
                <p><?= e($torneo['descripcion']) ?></p>
            <?php endif; ?>
            <div class="meta-row">
                <span class="meta-pill"><?= icon('map-pin') ?> <?= e(!empty($torneo['lugar']) ? $torneo['lugar'] : 'Lugar a definir') ?></span>
                <?php if ($fechas !== ''): ?>
                    <span class="meta-pill"><?= icon('calendar') ?> <?= e($fechas) ?></span>
                <?php endif; ?>
                <span class="meta-pill"><?= icon($esDobles ? 'users' : 'user') ?> <?= e($modalidad->label()) ?><?= $esDobles ? ' · ' . ($emparejamiento->esAleatorio() ? 'parejas por sorteo' : 'parejas manuales') : '' ?></span>
                <span class="meta-pill"><?= icon('users') ?> <?= e((string) $totalInscritos) ?> <?= e($rotuloParticipante) ?></span>
                <span class="meta-pill"><?= icon('flag') ?> <?= e((string) $torneo['num_clasificados']) ?> clasificados</span>
                <span class="meta-pill"><?= icon('paddle') ?> Mesa única</span>
                <?php if ($archivado): ?>
                    <span class="meta-pill"><?= icon('archive') ?> Archivado</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="hero-side">
            <div class="hero-card torneo-next">
                <span class="hero-card__label">Estado</span>
                <span class="badge badge--dot <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span>
                <p><?= e($siguiente['texto']) ?></p>

                <?php if ($siguiente['tipo'] === 'link'): ?>
                    <a class="btn btn--primary btn--block" href="<?= e($siguiente['href']) ?>"><?= icon($siguiente['icon']) ?> <?= e($siguiente['label']) ?></a>
                <?php elseif ($siguiente['tipo'] === 'estado'): ?>
                    <form method="post" action="<?= e($base) ?>/estado">
                        <?= csrf_field() ?>
                        <input type="hidden" name="estado" value="<?= e($destinoPrincipal->value) ?>">
                        <button class="btn btn--primary btn--block" type="submit"><?= icon($iconoTransicion($destinoPrincipal)) ?> <?= e($etiquetaTransicion($destinoPrincipal)) ?></button>
                    </form>
                <?php elseif ($siguiente['tipo'] === 'fixture'): ?>
                    <form method="post" action="<?= e($base) ?>/fixture"
                          data-confirm="<?= e($fixtureConfirm) ?>" data-confirm-title="Comenzar la liga" data-confirm-ok="Generar fixture" data-confirm-tone="info">
                        <?= csrf_field() ?>
                        <button class="btn btn--primary btn--block" type="submit"><?= icon('shuffle') ?> Generar fixture y comenzar liga</button>
                    </form>
                <?php elseif ($siguiente['tipo'] === 'sortear'): ?>
                    <form method="post" action="<?= e($base) ?>/sortear-parejas"
                          data-confirm="<?= e($sorteoConfirm) ?>" data-confirm-title="Sortear parejas" data-confirm-ok="Sortear" data-confirm-tone="info">
                        <?= csrf_field() ?>
                        <button class="btn btn--primary btn--block" type="submit"><?= icon('shuffle') ?> Sortear parejas</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($muestraPublica): ?>
                <a class="btn btn--block" href="/publico/torneos/<?= e($tid) ?>" target="_blank" rel="noopener">
                    <?= icon('tv') ?> Vista pública <span class="sr-only">(se abre en otra pestaña)</span> <?= icon('external') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<nav class="tabs" aria-label="Secciones del torneo">
    <?php foreach ($tabs as $tab): ?>
        <a class="tabs__link" href="<?= e($tab['href']) ?>"<?= !empty($tab['current']) ? ' aria-current="page"' : '' ?>><?= icon($tab['icon']) ?> <?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>

<div class="torneo-hub">
    <section class="card" id="inscripciones" aria-labelledby="inscripciones-titulo">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="inscripciones-titulo"><?= $muestraParejas ? 'Parejas inscritas' : 'Inscritos' ?></h2>
                <p class="muted small torneo-insc__meta">
                    <?= e((string) $totalInscritos) ?> <?= $totalInscritos === 1 ? ($muestraParejas ? 'pareja' : 'inscrito') : ($muestraParejas ? 'parejas' : 'inscritos') ?>
                    <?php if ($totalInscritos > 0): ?> · <?= e((string) $pagados) ?> con el pago al día<?php endif; ?>
                </p>
            </div>
            <?php if ($puedeSortear && $siguiente['tipo'] !== 'sortear'): ?>
                <form method="post" action="<?= e($base) ?>/sortear-parejas" class="inline"
                      data-confirm="<?= e($sorteoConfirm) ?>" data-confirm-title="Sortear parejas" data-confirm-ok="Sortear" data-confirm-tone="info">
                    <?= csrf_field() ?>
                    <button class="btn btn--secondary btn--sm" type="submit"><?= icon('shuffle') ?> Sortear parejas</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($totalInscritos > 0): ?>
            <div class="progress torneo-insc__progress" role="img" aria-label="<?= e((string) $pagados) ?> de <?= e((string) $totalInscritos) ?> con el pago registrado">
                <span style="--value: <?= e((string) round($pagados * 100 / $totalInscritos)) ?>%"></span>
            </div>
        <?php endif; ?>

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
            <div class="form-panel torneo-insc__panel">
                <?php if ($disponibles === [] || ($esParejaManual && count($disponibles) < 2)): ?>
                    <p class="muted small torneo-insc__empty"><?= icon('info') ?> No quedan jugadores suficientes por inscribir.</p>
                <?php elseif ($esParejaManual): ?>
                    <form method="post" action="<?= e($base) ?>/inscripciones">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="field">
                                <label for="insc-jugador-1">Jugador 1</label>
                                <select id="insc-jugador-1" name="persona_id" required><?= $opcionesPersona($disponibles, 'Elegí un jugador') ?></select>
                            </div>
                            <div class="field">
                                <label for="insc-jugador-2">Jugador 2</label>
                                <select id="insc-jugador-2" name="persona_id_2" required><?= $opcionesPersona($disponibles, 'Elegí su pareja') ?></select>
                            </div>
                        </div>
                        <div class="actions torneo-insc__actions">
                            <button class="btn btn--primary" type="submit"><?= icon('user-plus') ?> Inscribir pareja</button>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e($base) ?>/inscripciones" data-pick>
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="insc-jugadores">Jugadores a inscribir</label>
                            <select id="insc-jugadores" name="persona_ids[]" multiple data-ts placeholder="Buscá y elegí jugadores…" autocomplete="off" aria-describedby="insc-jugadores-hint">
                                <?php foreach ($disponibles as $d): ?>
                                    <?php $area = !empty($d['area']) ? ' (' . $d['area'] . ')' : ''; ?>
                                    <option value="<?= e((string) $d['id']) ?>"><?= e($d['apellido'] . ', ' . $d['nombre'] . $area) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field__hint" id="insc-jugadores-hint">
                                Buscá y marcá uno, varios o todos los jugadores, y inscribilos de una vez.
                                <?php if ($esParejaAleatoria): ?>Las parejas se sortean una vez cerrada la inscripción.<?php endif; ?>
                            </p>
                        </div>
                        <div class="actions torneo-insc__actions">
                            <button class="btn btn--primary" type="submit" data-ts-submit>Inscribir seleccionados</button>
                            <button class="btn btn--ghost" type="button" data-ts-all>Seleccionar todos</button>
                            <button class="btn btn--ghost" type="button" data-ts-none hidden>Limpiar</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif ($puedeGestionar): ?>
            <p class="hint"><?= icon('lock') ?> <span>La inscripción está <strong>cerrada</strong> en el estado actual.</span></p>
        <?php endif; ?>

        <?php if ($inscripciones === []): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('user-plus') ?></span>
                <p class="empty-state__title">Todavía no hay inscritos</p>
                <?php if ($puedeGestionar && $estado === EstadoTorneo::BORRADOR): ?>
                    <p>Abrí la inscripción para empezar a sumar <?= e($rotuloParticipante) ?>.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <caption class="sr-only">Participantes inscritos y estado de pago</caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= $muestraParejas ? 'Pareja' : 'Jugador' ?></th>
                            <th scope="col">Área</th>
                            <th scope="col">Pago</th>
                            <?php if ($puedeQuitar): ?><th scope="col"><span class="sr-only">Acciones</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($inscripciones as $i): ?>
                        <?php $pagado = (int) $i['pago_realizado'] === 1; ?>
                        <tr>
                            <td class="cell--primary"><span class="table__title"><?= e($i['label']) ?></span></td>
                            <td data-label="Área"><?= !empty($i['area']) ? e($i['area']) : '<span class="muted">—</span>' ?></td>
                            <td data-label="Pago">
                                <?php if ($puedeGestionar): ?>
                                    <form method="post" action="<?= e($base) ?>/inscripciones/<?= e((string) $i['id']) ?>/pago" class="inline">
                                        <?= csrf_field() ?>
                                        <button class="pay-toggle<?= $pagado ? ' is-paid' : '' ?>" type="submit"
                                                title="<?= $pagado ? 'Marcar como pendiente' : 'Marcar como pagado' ?>">
                                            <span class="pay-toggle__track" aria-hidden="true"><span class="pay-toggle__thumb"></span></span>
                                            <span class="pay-toggle__text"><?= $pagado ? 'Pagado' : 'Pendiente' ?></span>
                                            <span class="sr-only">: <?= e($i['label']) ?>. Cambiar a <?= $pagado ? 'pendiente' : 'pagado' ?></span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge--dot <?= $pagado ? 'badge--green' : 'badge--gray' ?>"><?= $pagado ? 'Pagado' : 'Pendiente' ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($puedeQuitar): ?>
                            <td class="actions-cell">
                                    <form method="post" action="<?= e($base) ?>/inscripciones/<?= e((string) $i['id']) ?>/eliminar" class="inline"
                                          data-confirm="¿Quitar a <?= e($i['label']) ?> del torneo?" data-confirm-title="Quitar inscripción" data-confirm-ok="Quitar">
                                        <?= csrf_field() ?>
                                        <button class="btn btn--danger-soft btn--sm" type="submit"><?= icon('trash') ?> Quitar<span class="sr-only"> a <?= e($i['label']) ?></span></button>
                                    </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="torneo-hub__side">
        <section class="card" aria-labelledby="config-titulo">
            <div class="card__header">
                <h2 class="card__title" id="config-titulo">Configuración</h2>
                <?php if ($puedeGestionar && $estado->permiteEditarConfig()): ?>
                    <a class="btn btn--secondary btn--sm" href="<?= e($base) ?>/editar"><?= icon('edit') ?> Editar</a>
                <?php endif; ?>
            </div>
            <dl class="datalist">
                <dt>Lugar</dt><dd><?= e(!empty($torneo['lugar']) ? $torneo['lugar'] : '—') ?></dd>
                <dt>Fechas</dt><dd><?= e($fechas !== '' ? $fechas : '—') ?></dd>
                <dt>Sets en la liga</dt><dd><?= e($formatoLabel((int) $torneo['formato_sets_liga'])) ?></dd>
                <dt>Sets en el bracket</dt><dd><?= e($formatoLabel((int) $torneo['formato_sets_bracket'])) ?></dd>
                <dt>Puntos por set</dt><dd><?= e((string) $torneo['puntos_por_set']) ?> <span class="muted">(diferencia mínima <?= e((string) $torneo['diferencia_minima']) ?>)</span></dd>
                <dt>Puntos por victoria</dt><dd><?= e((string) $torneo['puntos_por_victoria']) ?></dd>
                <dt>Clasificados</dt><dd><?= e((string) $torneo['num_clasificados']) ?></dd>
                <dt>Posiciones premiadas</dt><dd><?= e((string) $torneo['posiciones_premiadas']) ?></dd>
                <dt>Cuota</dt><dd><?= e(($torneo['cuota_inscripcion'] ?? '') !== '' && $torneo['cuota_inscripcion'] !== null ? $torneo['cuota_inscripcion'] : '—') ?></dd>
                <dt>Pozo</dt><dd><?= ($torneo['pozo_total'] ?? '') !== '' && $torneo['pozo_total'] !== null ? e($torneo['pozo_total']) : '<span class="muted">Cuota × inscritos</span>' ?></dd>
            </dl>
        </section>

        <?php if ($puedeGestionar): ?>
            <section class="card" aria-labelledby="gestion-titulo">
                <h2 class="card__title" id="gestion-titulo">Gestión</h2>

                <?php if ($estado === EstadoTorneo::INSCRIPCION_CERRADA): ?>
                    <p class="hint"><?= icon('info') ?> <span>La fase de liga comienza al generar el fixture.</span></p>
                <?php elseif ($estado === EstadoTorneo::FASE_LIGA): ?>
                    <p class="hint"><?= icon('info') ?> <span>La fase eliminatoria se genera desde la <a href="<?= e($base) ?>/tabla">tabla de posiciones</a> cuando termina la liga.</span></p>
                <?php elseif ($estado === EstadoTorneo::FASE_ELIMINATORIA): ?>
                    <p class="hint"><?= icon('info') ?> <span>El torneo se finaliza desde el <a href="<?= e($base) ?>/bracket">bracket</a>.</span></p>
                <?php endif; ?>

                <div class="actions torneo-manage">
                    <?php foreach ($otrasTransiciones as $destino): ?>
                        <form method="post" action="<?= e($base) ?>/estado" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="estado" value="<?= e($destino->value) ?>">
                            <button class="btn btn--secondary" type="submit"><?= icon($iconoTransicion($destino)) ?> <?= e($etiquetaTransicion($destino)) ?></button>
                        </form>
                    <?php endforeach; ?>

                    <?php if ($puedeFixture && $siguiente['tipo'] !== 'fixture'): ?>
                        <form method="post" action="<?= e($base) ?>/fixture" class="inline"
                              data-confirm="<?= e($fixtureConfirm) ?>" data-confirm-title="Comenzar la liga" data-confirm-ok="Generar fixture" data-confirm-tone="info">
                            <?= csrf_field() ?>
                            <button class="btn btn--secondary" type="submit"><?= icon('shuffle') ?> Generar fixture y comenzar liga</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?= e($base) ?>/duplicar" class="inline">
                        <?= csrf_field() ?>
                        <button class="btn btn--ghost" type="submit"><?= icon('copy') ?> Duplicar</button>
                    </form>
                    <form method="post" action="<?= e($base) ?>/archivar" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="archivar" value="<?= $archivado ? '0' : '1' ?>">
                        <button class="btn btn--ghost" type="submit"><?= icon('archive') ?> <?= $archivado ? 'Desarchivar' : 'Archivar' ?></button>
                    </form>
                </div>

                <div class="torneo-danger">
                    <h3 class="torneo-danger__title"><?= icon('alert') ?> Zona de riesgo</h3>
                    <div class="actions">
                        <?php if ($puedeCancelar): ?>
                            <form method="post" action="<?= e($base) ?>/estado" class="inline"
                                  data-confirm="¿Cancelar el torneo? Un torneo cancelado no admite más cambios de estado." data-confirm-title="Cancelar torneo" data-confirm-ok="Cancelar torneo">
                                <?= csrf_field() ?>
                                <input type="hidden" name="estado" value="<?= e(EstadoTorneo::CANCELADO->value) ?>">
                                <button class="btn btn--danger-soft" type="submit"><?= icon('x-circle') ?> Cancelar torneo</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e($base) ?>/eliminar" class="inline"
                              data-confirm="¿Eliminar el torneo y todos sus datos? Esta acción no se puede deshacer." data-confirm-title="Eliminar torneo" data-confirm-ok="Eliminar">
                            <?= csrf_field() ?>
                            <button class="btn btn--danger-soft" type="submit"><?= icon('trash') ?> Eliminar</button>
                        </form>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeGestionar && $estado->permiteInscripciones() && !$esParejaManual && $disponibles !== []): ?>
<!-- Tom Select: searchable multi-select with chips, loaded only on this view. Theme lives in core/components.css. -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
// The native <select multiple name="persona_ids[]"> is the source of truth;
// Tom Select only enhances it. If the script fails to load (offline / CDN down)
// the native select keeps working.
(function () {
    var el = document.querySelector('[data-ts]');
    if (!el || typeof TomSelect === 'undefined') return;

    var form    = el.closest('form');
    var submit  = form.querySelector('[data-ts-submit]');
    var btnAll  = form.querySelector('[data-ts-all]');
    var btnNone = form.querySelector('[data-ts-none]');
    var base    = submit.textContent.trim();

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
        btnAll.disabled = n >= Object.keys(ts.options).length;
    }

    ts.on('change', refresh);
    btnAll.addEventListener('click', function () {
        ts.setValue(Object.keys(ts.options), false);
        ts.close();
    });
    btnNone.addEventListener('click', function () { ts.clear(); });

    refresh();
})();
</script>
<?php endif; ?>
