<?php
/** @var array<string,mixed> $fiesta */
/** @var list<array{id:int,persona_id:int,nombre:string,area:?string}> $participantes */
/** @var list<array<string,mixed>> $disponibles */
/** @var array<int,array<string,mixed>> $equipos */
/** @var array<int,array<string,mixed>> $grupos */
/** @var array<int,list<array<string,mixed>>> $rondas */
/** @var array<int,true> $partidosBloqueados */
/** @var array<string,mixed>|null $campeon */
/** @var string|null $errorSorteo */
/** @var bool $gruposListos */
/** @var bool $hayResultados */

use PingPong\Domain\BracketEliminacion;
use PingPong\Service\FiestaService;

$puedeGestionar = $rol?->puedeGestionar() ?? false;
$puedeAnotar = $rol?->puedeAnotar() ?? false;
$base = '/fiesta/' . (string) $fiesta['id'];
$estado = (string) $fiesta['estado'];
$dobles = $fiesta['modalidad'] === 'DOBLES';
$esLiga = $fiesta['formato'] === 'LIGA';
$conGrupos = in_array($fiesta['formato'], ['GRUPOS', 'LIGA'], true);
$n = count($participantes);
$numEquipos = $dobles ? intdiv($n, 2) : $n;
$clasifican = (int) $fiesta['clasificados_por_grupo'];
$mejorDe = (int) ($fiesta['mejor_de'] ?? 1);
$setsOverrides = FiestaService::parsearSetsRondas(
    is_string($fiesta['sets_rondas_json'] ?? null) ? $fiesta['sets_rondas_json'] : null
);
$mejorDeLabel = ($mejorDe === 1 ? '1 set' : "Mejor de {$mejorDe}")
    . ($setsOverrides !== [] ? ' · por ronda' : '');
$necesitaScriptSets = $mejorDe > 1 || ($setsOverrides !== [] && max($setsOverrides) > 1);

$pasos = [
    ['INSCRIPCION', 'Anotar', 'user-plus'],
    ['SORTEO', 'Ruleta', 'shuffle'],
    ['JUEGO', 'Jugar', 'swords'],
    ['FINALIZADO', 'Campeón', 'crown'],
];
$pasoActual = match ($estado) {
    'INSCRIPCION' => 0,
    'GRUPOS', 'ELIMINATORIA' => 2,
    default => 3,
};

$resumenEquipos = $dobles
    ? ($n % 2 === 0 ? "{$numEquipos} parejas" : "{$n} anotados · falta 1 para cerrar parejas")
    : "{$n} jugadores";
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/fiesta">Torneo fiesta</a></li>
        <li><span aria-current="page"><?= e((string) $fiesta['nombre']) ?></span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow">Torneo fiesta</span>
        <h1><?= e((string) $fiesta['nombre']) ?></h1>
        <div class="meta-row">
            <span class="meta-pill"><?= icon($dobles ? 'users' : 'user') ?> <?= $dobles ? 'Dobles' : 'Individual' ?></span>
            <span class="meta-pill"><?= icon($esLiga ? 'table' : ($conGrupos ? 'layers' : 'bracket')) ?>
                <?php if ($esLiga): ?>
                    <?= e("Liga todos contra todos · clasifican {$clasifican}") ?>
                <?php elseif ($conGrupos): ?>
                    <?= e("{$fiesta['num_grupos']} grupos · clasifican {$clasifican}") ?>
                <?php else: ?>
                    Eliminación directa
                <?php endif; ?>
            </span>
            <span class="meta-pill"><?= icon('scoreboard') ?> <?= e($mejorDeLabel) ?></span>
        </div>
    </div>
    <div class="actions">
        <?php if ($fiesta['sorteo_json'] !== null): ?>
            <a class="btn btn--secondary" href="<?= e($base) ?>/ruleta"><?= icon('play') ?> Ver la ruleta</a>
        <?php endif; ?>
        <?php if ($puedeGestionar && $estado !== 'INSCRIPCION' && !$hayResultados): ?>
            <form method="post" action="<?= e($base) ?>/deshacer-sorteo" class="inline"
                  data-confirm="Se borran los equipos y los cruces sorteados y vuelven a la etapa de inscripción." data-confirm-title="Repetir el sorteo" data-confirm-ok="Anular sorteo">
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit"><?= icon('refresh') ?> Repetir sorteo</button>
            </form>
        <?php endif; ?>
        <?php if ($puedeGestionar): ?>
            <form method="post" action="<?= e($base) ?>/eliminar" class="inline"
                  data-confirm="Se elimina la fiesta con sus participantes y resultados. No se puede deshacer." data-confirm-title="Eliminar fiesta" data-confirm-ok="Eliminar">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--icon" type="submit" title="Eliminar fiesta" aria-label="Eliminar fiesta"><?= icon('trash') ?></button>
            </form>
        <?php endif; ?>
    </div>
</header>

<ol class="fiesta-stepper" aria-label="Etapas">
    <?php foreach ($pasos as $i => [, $label, $ico]): ?>
        <li class="fiesta-stepper__step<?= $i < $pasoActual ? ' is-done' : '' ?><?= $i === $pasoActual ? ' is-current' : '' ?>"<?= $i === $pasoActual ? ' aria-current="step"' : '' ?>>
            <span class="fiesta-stepper__dot"><?= icon($i < $pasoActual ? 'check' : $ico) ?></span>
            <span class="fiesta-stepper__label"><?= e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>

<?php if ($campeon !== null): ?>
    <section class="fiesta-champion" id="campeon" aria-label="Campeón">
        <span class="fiesta-champion__crown"><?= icon('crown') ?></span>
        <p class="fiesta-champion__eyebrow"><?= $dobles ? 'Pareja campeona' : 'Campeón' ?></p>
        <p class="fiesta-champion__name"><?= e((string) $campeon['label']) ?></p>
    </section>
<?php endif; ?>

<?php if ($estado === 'INSCRIPCION'): ?>
    <div class="fiesta-layout">
        <section class="card" id="participantes" aria-labelledby="t-participantes">
            <div class="card__header">
                <h2 class="card__title" id="t-participantes">Participantes <span class="fiesta-count"><?= e((string) $n) ?></span></h2>
                <span class="badge <?= $dobles && $n % 2 !== 0 ? 'badge--gold' : 'badge--blue' ?>"><?= e($resumenEquipos) ?></span>
            </div>

            <?php if ($puedeGestionar): ?>
                <?php if ($disponibles === []): ?>
                    <p class="muted small"><?= icon('info') ?> Ya están anotados todos los jugadores activos.</p>
                <?php else: ?>
                    <form method="post" action="<?= e($base) ?>/participantes" class="form-panel">
                        <?= csrf_field() ?>
                        <?php
                        $seleccionados = [];
                        $submitLabel = 'Sumar a la fiesta';
                        $paridadBase = $n;
                        $modalidadFija = (string) $fiesta['modalidad'];
                        require __DIR__ . '/_selector-jugadores.php';
                        ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($participantes === []): ?>
                <div class="empty-state fiesta-empty">
                    <span class="empty-state__icon"><?= icon('users') ?></span>
                    <p class="empty-state__title">Nadie anotado todavía</p>
                    <p>Elegí a los jugadores de la plataforma que van a participar.</p>
                </div>
            <?php else: ?>
                <ul class="fiesta-chips">
                    <?php foreach ($participantes as $p): ?>
                        <li class="fiesta-chip">
                            <span class="avatar avatar--sm" aria-hidden="true"><?= e(initials($p['nombre'])) ?></span>
                            <span class="fiesta-chip__name"><?= e($p['nombre']) ?></span>
                            <?php if ($puedeGestionar): ?>
                                <form method="post" action="<?= e($base) ?>/participantes/<?= e((string) $p['id']) ?>/eliminar" class="inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="fiesta-chip__remove" aria-label="Quitar a <?= e($p['nombre']) ?>" title="Quitar"><?= icon('x') ?></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <aside class="fiesta-side">
            <section class="card fiesta-cta" aria-labelledby="t-ruleta">
                <span class="fiesta-cta__wheel" aria-hidden="true"><?= icon('shuffle') ?></span>
                <h2 class="card__title" id="t-ruleta">La ruleta</h2>
                <p class="muted">
                    <?php if ($esLiga): ?>
                        <?php if ($dobles): ?>
                            Primero salen, de a uno, los primeros integrantes de cada pareja y después sus compañeros.
                        <?php else: ?>
                            La ruleta sortea el orden de los jugadores en la tabla.
                        <?php endif; ?>
                        Todos los equipos juegan contra todos por puntos y los <?= e((string) $clasifican) ?> mejores pasan a la eliminatoria.
                    <?php else: ?>
                        <?php if ($dobles): ?>
                            Primero salen, de a uno, los primeros integrantes de cada pareja. Después la ruleta gira otra vez para cada uno y revela a su compañero.
                        <?php else: ?>
                            La ruleta va sacando a los jugadores de a uno y los ubica en su lugar del cuadro.
                        <?php endif; ?>
                        <?= $conGrupos ? 'Al final reparte los equipos en los grupos.' : 'Al final arma los cruces de la eliminación directa.' ?>
                    <?php endif; ?>
                </p>
                <?php if ($errorSorteo !== null): ?>
                    <p class="alert alert--warning fiesta-cta__warn"><?= icon('info') ?> <span><?= e($errorSorteo) ?></span></p>
                <?php endif; ?>
                <?php if ($puedeGestionar): ?>
                    <form method="post" action="<?= e($base) ?>/sortear"
                          data-confirm="Se sortean <?= $dobles ? 'las parejas y ' : '' ?>los cruces con los <?= e((string) $n) ?> participantes. Después ya no se pueden sumar más." data-confirm-title="¿Giramos la ruleta?" data-confirm-ok="¡Girar!" data-confirm-tone="info">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--primary btn--lg btn--block fiesta-cta__btn"<?= $errorSorteo !== null ? ' disabled' : '' ?>>
                            <?= icon('play') ?> ¡Girar la ruleta!
                        </button>
                    </form>
                <?php else: ?>
                    <p class="muted small">El organizador va a girar la ruleta cuando estén todos.</p>
                <?php endif; ?>
            </section>

            <?php if ($puedeGestionar): ?>
                <details class="card fiesta-settings">
                    <summary><?= icon('settings') ?> Ajustes de la fiesta</summary>
                    <form method="post" action="<?= e($base) ?>/config" novalidate>
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="field field--wide">
                                <label for="f-nombre">Nombre</label>
                                <input type="text" id="f-nombre" name="nombre" maxlength="150" required value="<?= e((string) $fiesta['nombre']) ?>">
                            </div>
                            <?php $cfg = $fiesta; require __DIR__ . '/_config.php'; ?>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn--secondary"><?= icon('check') ?> Guardar ajustes</button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
        </aside>
    </div>

<?php else: ?>

    <section class="section" aria-labelledby="t-equipos">
        <div class="section-head">
            <h2 id="t-equipos"><?= $dobles ? 'Parejas sorteadas' : 'Jugadores' ?></h2>
        </div>
        <ul class="fiesta-teams">
            <?php foreach ($equipos as $eq): ?>
                <li class="fiesta-team">
                    <span class="fiesta-team__num"><?= e((string) $eq['numero']) ?></span>
                    <span class="fiesta-team__name"><?= e((string) $eq['label']) ?></span>
                    <?php if (!$esLiga && $eq['grupo'] !== null): ?>
                        <span class="badge badge--blue">Grupo <?= e(chr(64 + (int) $eq['grupo'])) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($esLiga && $grupos !== []): ?>
        <?php
        $liga = reset($grupos);
        $pendientesLiga = [];
        $jugadosLiga = [];
        foreach ($liga['partidos'] as $pl) {
            if ($pl['estado'] === 'FINALIZADO') {
                $jugadosLiga[] = $pl;
            } else {
                $pendientesLiga[] = $pl;
            }
        }
        $totalL = count($liga['partidos']);
        $doneL = count($jugadosLiga);
        $pctL = $totalL > 0 ? (int) round(($doneL / $totalL) * 100) : 0;
        $ligaCompleta = $totalL > 0 && $doneL === $totalL;
        ?>
        <section class="section" aria-labelledby="t-liga">
            <article class="card fiesta-liga<?= $ligaCompleta ? ' is-complete' : '' ?>">
                <header class="fiesta-liga__head">
                    <div class="fiesta-group__id">
                        <span class="fiesta-liga__icon" aria-hidden="true"><?= icon('trophy') ?></span>
                        <div>
                            <h2 id="t-liga">Tabla general — Todos contra todos</h2>
                            <p class="fiesta-group__sub">
                                <?= e((string) count($liga['tabla'])) ?> <?= $dobles ? 'parejas' : 'jugadores' ?> · Clasifican top <?= e((string) $clasifican) ?> a la eliminatoria
                            </p>
                        </div>
                    </div>
                    <div class="fiesta-liga__actions">
                        <span class="fiesta-liga__rule">Victoria = +3 pts</span>
                        <span class="fiesta-group__pill<?= $ligaCompleta ? ' is-done' : '' ?>">
                            <?= $ligaCompleta ? icon('check') . ' Liga completa' : e("{$doneL}/{$totalL} jugados") ?>
                        </span>
                        <?php if ($estado === 'GRUPOS' && $puedeGestionar): ?>
                            <form method="post" action="<?= e($base) ?>/eliminatoria"
                                  data-confirm="Clasifican los <?= e((string) $clasifican) ?> mejores de la tabla general y se arman los cruces eliminatorios." data-confirm-title="Pasar a la eliminatoria" data-confirm-ok="Armar cruces" data-confirm-tone="info">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--primary"<?= $gruposListos ? '' : ' disabled title="Completá todos los partidos de la liga primero"' ?>>
                                    <?= icon('bracket') ?> Pasar a la eliminatoria (Top <?= e((string) $clasifican) ?>)
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="fiesta-group__progress" aria-hidden="true">
                    <span style="width: <?= e((string) $pctL) ?>%"></span>
                </div>

                <div class="fiesta-group__standings">
                    <div class="table-wrap">
                        <table class="table fiesta-table fiesta-table--liga">
                            <caption class="sr-only">Tabla general de la liga todos contra todos</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="fiesta-table__pos">#</th>
                                    <th scope="col"><?= $dobles ? 'Pareja' : 'Jugador' ?></th>
                                    <th scope="col" class="num" title="Puntos">Pts</th>
                                    <th scope="col" class="num" title="Partidos jugados">PJ</th>
                                    <th scope="col" class="num" title="Partidos ganados">PG</th>
                                    <th scope="col" class="num" title="Partidos perdidos">PP</th>
                                    <th scope="col" class="num" title="Puntos a favor">PF</th>
                                    <th scope="col" class="num" title="Puntos en contra">PC</th>
                                    <th scope="col" class="num" title="Diferencia de puntos">Dif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $totalFilasLiga = count($liga['tabla']); ?>
                                <?php foreach ($liga['tabla'] as $fila): ?>
                                    <?php
                                    $eqFila = $equipos[$fila['equipo']] ?? null;
                                    $pos = (int) $fila['posicion'];
                                    $clasifica = $pos <= $clasifican;
                                    $esCorte = $pos === $clasifican && $clasifican < $totalFilasLiga;
                                    $pts = (int) ($fila['puntos'] ?? ((int) $fila['pg'] * 3));
                                    $pf = (int) ($fila['favor'] ?? 0);
                                    $pc = (int) ($fila['contra'] ?? 0);
                                    $dif = (int) $fila['diferencia'];
                                    $difClass = $dif > 0 ? 'fiesta-diff--pos' : ($dif < 0 ? 'fiesta-diff--neg' : 'fiesta-diff--zero');
                                    $posClass = $clasifica ? ' is-qualified' : '';
                                    if ($pos <= 3) {
                                        $posClass .= ' is-pos-' . $pos;
                                    }
                                    ?>
                                    <tr class="<?= $clasifica ? 'is-qualified' : '' ?><?= $esCorte ? ' is-cutoff' : '' ?>">
                                        <td class="fiesta-table__pos">
                                            <span class="fiesta-pos-badge<?= $posClass ?>"><?= e((string) $pos) ?></span>
                                        </td>
                                        <td class="cell--primary fiesta-table__team">
                                            <?php if ($eqFila !== null): ?>
                                                <span class="fiesta-table__seed">#<?= e((string) $eqFila['numero']) ?></span>
                                                <span class="fiesta-table__name"><?= e((string) $eqFila['label']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="num"><span class="fiesta-liga__pts"><?= e((string) $pts) ?></span></td>
                                        <td class="num"><?= e((string) $fila['pj']) ?></td>
                                        <td class="num"><strong><?= e((string) $fila['pg']) ?></strong></td>
                                        <td class="num muted"><?= e((string) $fila['pp']) ?></td>
                                        <td class="num"><?= e((string) $pf) ?></td>
                                        <td class="num muted"><?= e((string) $pc) ?></td>
                                        <td class="num"><span class="fiesta-diff <?= $difClass ?>"><?= e(($dif > 0 ? '+' : '') . $dif) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="fiesta-table__legend">
                        <span class="fiesta-legend__dot fiesta-legend__dot--win" aria-hidden="true"></span>
                        Zona de clasificación (Top <?= e((string) $clasifican) ?> pasan a la eliminatoria) · Victoria = +3 pts
                    </p>
                </div>

                <div class="fiesta-group__fixtures">
                    <?php $enBracket = false; $abrirPorDefecto = false; $mejorDePartido = $mejorDe; ?>

                    <?php if ($pendientesLiga !== []): ?>
                        <div class="fiesta-group__block">
                            <h3 class="fiesta-group__block-title">
                                Por jugar <span class="fiesta-group__count"><?= e((string) count($pendientesLiga)) ?></span>
                            </h3>
                            <div class="fiesta-liga__matches">
                                <?php foreach ($pendientesLiga as $p): ?>
                                    <?php require __DIR__ . '/_partido.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($jugadosLiga !== []): ?>
                        <div class="fiesta-group__block">
                            <h3 class="fiesta-group__block-title fiesta-group__block-title--done">
                                Resultados <span class="fiesta-group__count"><?= e((string) count($jugadosLiga)) ?></span>
                            </h3>
                            <div class="fiesta-liga__matches">
                                <?php foreach ($jugadosLiga as $p): ?>
                                    <?php require __DIR__ . '/_partido.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    <?php elseif ($grupos !== []): ?>
        <?php
        $totalPartidosGrupos = 0;
        $jugadosGrupos = 0;
        foreach ($grupos as $gStats) {
            foreach ($gStats['partidos'] as $pStats) {
                $totalPartidosGrupos++;
                if ($pStats['estado'] === 'FINALIZADO') {
                    $jugadosGrupos++;
                }
            }
        }
        ?>
        <section class="section" aria-labelledby="t-grupos">
            <div class="section-head">
                <div>
                    <h2 id="t-grupos">Fase de grupos</h2>
                    <p class="muted small fiesta-bracket-sub">
                        Todos contra todos por grupo. Clasifican los <strong><?= e((string) $clasifican) ?> mejores</strong> de cada grupo a la eliminatoria
                        · <strong><?= e("{$jugadosGrupos}/{$totalPartidosGrupos}") ?></strong> partidos jugados.
                    </p>
                </div>
                <?php if ($estado === 'GRUPOS' && $puedeGestionar): ?>
                    <form method="post" action="<?= e($base) ?>/eliminatoria"
                          data-confirm="Clasifican los <?= e((string) $clasifican) ?> mejores de cada grupo y se arman los cruces." data-confirm-title="Pasar a la eliminatoria" data-confirm-ok="Armar cruces" data-confirm-tone="info">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--primary"<?= $gruposListos ? '' : ' disabled title="Completá todos los partidos de grupo primero"' ?>>
                            <?= icon('bracket') ?> Pasar a la eliminatoria
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="fiesta-groups">
                <?php foreach ($grupos as $g): ?>
                    <?php
                    $pendientesGrupo = [];
                    $jugadosGrupo = [];
                    foreach ($g['partidos'] as $pg) {
                        if ($pg['estado'] === 'FINALIZADO') {
                            $jugadosGrupo[] = $pg;
                        } else {
                            $pendientesGrupo[] = $pg;
                        }
                    }
                    $totalG = count($g['partidos']);
                    $doneG = count($jugadosGrupo);
                    $pctG = $totalG > 0 ? (int) round(($doneG / $totalG) * 100) : 0;
                    $grupoCompleto = $totalG > 0 && $doneG === $totalG;
                    ?>
                    <article class="card fiesta-group<?= $grupoCompleto ? ' is-complete' : '' ?>">
                        <header class="fiesta-group__head">
                            <div class="fiesta-group__id">
                                <span class="fiesta-group__letter"><?= e($g['letra']) ?></span>
                                <div>
                                    <h3>Grupo <?= e($g['letra']) ?></h3>
                                    <p class="fiesta-group__sub">
                                        <?= e((string) count($g['tabla'])) ?> <?= $dobles ? 'parejas' : 'jugadores' ?> · Clasifican top <?= e((string) $clasifican) ?>
                                    </p>
                                </div>
                            </div>
                            <span class="fiesta-group__pill<?= $grupoCompleto ? ' is-done' : '' ?>">
                                <?= $grupoCompleto ? icon('check') . ' Completo' : e("{$doneG}/{$totalG} jugados") ?>
                            </span>
                        </header>

                        <div class="fiesta-group__progress" aria-hidden="true">
                            <span style="width: <?= e((string) $pctG) ?>%"></span>
                        </div>

                        <div class="fiesta-group__standings">
                            <div class="table-wrap">
                                <table class="table fiesta-table">
                                    <caption class="sr-only">Posiciones del grupo <?= e($g['letra']) ?></caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" class="fiesta-table__pos">#</th>
                                            <th scope="col"><?= $dobles ? 'Pareja' : 'Jugador' ?></th>
                                            <th scope="col" class="num" title="Puntos">Pts</th>
                                            <th scope="col" class="num" title="Partidos jugados">PJ</th>
                                            <th scope="col" class="num" title="Partidos ganados">PG</th>
                                            <th scope="col" class="num" title="Partidos perdidos">PP</th>
                                            <th scope="col" class="num" title="Diferencia de puntos">Dif</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $totalFilas = count($g['tabla']); ?>
                                        <?php foreach ($g['tabla'] as $fila): ?>
                                            <?php
                                            $eqFila = $equipos[$fila['equipo']] ?? null;
                                            $clasifica = $fila['posicion'] <= $clasifican;
                                            $esCorte = $fila['posicion'] === $clasifican && $clasifican < $totalFilas;
                                            $pts = (int) ($fila['puntos'] ?? ((int) $fila['pg'] * 3));
                                            $dif = (int) $fila['diferencia'];
                                            $difClass = $dif > 0 ? 'fiesta-diff--pos' : ($dif < 0 ? 'fiesta-diff--neg' : 'fiesta-diff--zero');
                                            ?>
                                            <tr class="<?= $clasifica ? 'is-qualified' : '' ?><?= $esCorte ? ' is-cutoff' : '' ?>">
                                                <td class="fiesta-table__pos">
                                                    <span class="fiesta-pos-badge<?= $clasifica ? ' is-qualified' : '' ?>"><?= e((string) $fila['posicion']) ?></span>
                                                </td>
                                                <td class="cell--primary fiesta-table__team">
                                                    <?php if ($eqFila !== null): ?>
                                                        <span class="fiesta-table__seed">#<?= e((string) $eqFila['numero']) ?></span>
                                                        <span class="fiesta-table__name"><?= e((string) $eqFila['label']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="num"><span class="fiesta-liga__pts"><?= e((string) $pts) ?></span></td>
                                                <td class="num"><?= e((string) $fila['pj']) ?></td>
                                                <td class="num"><strong><?= e((string) $fila['pg']) ?></strong></td>
                                                <td class="num muted"><?= e((string) $fila['pp']) ?></td>
                                                <td class="num"><span class="fiesta-diff <?= $difClass ?>"><?= e(($dif > 0 ? '+' : '') . $dif) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="fiesta-table__legend">
                                <span class="fiesta-legend__dot fiesta-legend__dot--win" aria-hidden="true"></span>
                                Zona de clasificación (Top <?= e((string) $clasifican) ?>) · Victoria = +3 pts
                            </p>
                        </div>

                        <div class="fiesta-group__fixtures">
                            <?php $enBracket = false; $abrirPorDefecto = false; ?>

                            <?php if ($pendientesGrupo !== []): ?>
                                <div class="fiesta-group__block">
                                    <h4 class="fiesta-group__block-title">
                                        Por jugar <span class="fiesta-group__count"><?= e((string) count($pendientesGrupo)) ?></span>
                                    </h4>
                                    <div class="fiesta-group__matches">
                                        <?php foreach ($pendientesGrupo as $p): ?>
                                            <?php require __DIR__ . '/_partido.php'; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($jugadosGrupo !== []): ?>
                                <div class="fiesta-group__block">
                                    <h4 class="fiesta-group__block-title fiesta-group__block-title--done">
                                        Resultados <span class="fiesta-group__count"><?= e((string) count($jugadosGrupo)) ?></span>
                                    </h4>
                                    <div class="fiesta-group__matches">
                                        <?php foreach ($jugadosGrupo as $p): ?>
                                            <?php require __DIR__ . '/_partido.php'; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($rondas !== []): ?>
        <?php
        $totalRondas = max(array_keys($rondas));
        $enBracket = true;
        ?>
        <section class="section" id="eliminatoria" aria-labelledby="t-eliminatoria">
            <div class="section-head">
                <div>
                    <h2 id="t-eliminatoria">Cuadro eliminatorio</h2>
                    <p class="muted small fiesta-bracket-sub">Seguí el camino directo hacia la final. Los cruces con pase directo avanzan automáticamente a la siguiente ronda.</p>
                </div>
                <div class="fiesta-bracket-legend" aria-label="Leyenda del cuadro">
                    <span class="fiesta-legend__item"><span class="fiesta-legend__dot fiesta-legend__dot--win"></span> Avanza</span>
                    <span class="fiesta-legend__item"><span class="fiesta-legend__dot fiesta-legend__dot--bye"></span> Pase directo</span>
                    <span class="fiesta-legend__item"><span class="fiesta-legend__dot fiesta-legend__dot--final"></span> Final</span>
                </div>
            </div>
            <div class="fiesta-bracket-wrap">
                <div class="fiesta-bracket" style="--rondas: <?= e((string) $totalRondas) ?>">
                    <?php foreach ($rondas as $numRonda => $partidosRonda): ?>
                        <?php
                        $esRondaFinal = $numRonda === $totalRondas;
                        $nombreRonda = BracketEliminacion::nombreRonda($numRonda, $totalRondas);
                        $mejorDeRonda = FiestaService::mejorDeParaPartido(
                            $fiesta,
                            ['fase' => 'ELIMINATORIA', 'ronda' => $numRonda, 'siguiente_id' => $esRondaFinal ? null : 1],
                            $totalRondas,
                        );
                        $etiquetaSetsRonda = $mejorDeRonda === 1 ? '1 set' : "Mejor de {$mejorDeRonda}";
                        $jugables = 0;
                        $resueltos = 0;
                        foreach ($partidosRonda as $pr) {
                            if ((int) $pr['es_bye'] === 0) {
                                $jugables++;
                                if ($pr['estado'] === 'FINALIZADO') {
                                    $resueltos++;
                                }
                            }
                        }
                        ?>
                        <div class="fiesta-bracket__round<?= $esRondaFinal ? ' fiesta-bracket__round--final' : '' ?>">
                            <header class="fiesta-bracket__head">
                                <h3 class="fiesta-bracket__title">
                                    <?php if ($esRondaFinal): ?><?= icon('trophy') ?> <?php endif; ?>
                                    <?= e($nombreRonda) ?>
                                </h3>
                                <div class="fiesta-bracket__head-right">
                                    <span class="fiesta-bracket__sets"><?= e($etiquetaSetsRonda) ?></span>
                                    <span class="fiesta-bracket__meta">
                                        <?= $esRondaFinal
                                            ? ($resueltos > 0 ? 'Definida' : 'Título en juego')
                                            : e("{$resueltos}/{$jugables} jugados") ?>
                                    </span>
                                </div>
                            </header>
                            <div class="fiesta-bracket__matches">
                                <?php foreach ($partidosRonda as $idx => $p): ?>
                                    <?php
                                    $pairPos = ($idx % 2 === 0) ? 'top' : 'bottom';
                                    $slotResuelto = $p['ganador_id'] !== null;
                                    $etiquetaCruce = $esRondaFinal
                                        ? 'GRAN FINAL'
                                        : 'Cruce ' . (string) ($idx + 1);
                                    $mejorDePartido = FiestaService::mejorDeParaPartido($fiesta, $p, $totalRondas);
                                    ?>
                                    <div class="fiesta-bracket__slot<?= $slotResuelto ? ' is-resolved' : '' ?>"
                                         data-pair-pos="<?= $esRondaFinal ? 'final' : $pairPos ?>">
                                        <?php require __DIR__ . '/_partido.php'; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="fiesta-bracket__round fiesta-bracket__round--champion">
                        <header class="fiesta-bracket__head">
                            <h3 class="fiesta-bracket__title"><?= icon('crown') ?> Campeón</h3>
                            <span class="fiesta-bracket__meta"><?= $campeon !== null ? 'Coronado' : 'Pendiente' ?></span>
                        </header>
                        <div class="fiesta-bracket__matches">
                            <div class="fiesta-bracket__slot<?= $campeon !== null ? ' is-resolved' : '' ?>" data-pair-pos="champion">
                                <article class="fiesta-trophy-node<?= $campeon !== null ? ' is-crowned' : '' ?>">
                                    <span class="fiesta-trophy-node__icon" aria-hidden="true"><?= icon('trophy') ?></span>
                                    <span class="fiesta-trophy-node__eyebrow"><?= $campeon !== null ? 'Campeón de la fiesta' : 'Próximo campeón' ?></span>
                                    <strong class="fiesta-trophy-node__name">
                                        <?= $campeon !== null ? e((string) $campeon['label']) : 'Por definir' ?>
                                    </strong>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($necesitaScriptSets): ?>
        <script>
            (function () {
                function syncForm(form) {
                    var mejorDe = parseInt(form.getAttribute('data-mejor-de') || '3', 10);
                    var target = Math.floor(mejorDe / 2) + 1;
                    var labelA = form.getAttribute('data-label-a') || '#A';
                    var labelB = form.getAttribute('data-label-b') || '#B';
                    var rows = Array.prototype.slice.call(form.querySelectorAll('[data-set-row]'));
                    var liveEl = form.querySelector('[data-sets-live]');
                    var statusEl = form.querySelector('[data-sets-status]');

                    var winsA = 0;
                    var winsB = 0;
                    var decidedAt = -1;
                    var hasTie = false;

                    rows.forEach(function (row, idx) {
                        var inA = row.querySelector('input[name="sets_a[]"]');
                        var inB = row.querySelector('input[name="sets_b[]"]');
                        var rawA = inA ? inA.value.trim() : '';
                        var rawB = inB ? inB.value.trim() : '';

                        if (decidedAt !== -1) {
                            row.hidden = true;
                            if (inA) { inA.disabled = true; inA.required = false; }
                            if (inB) { inB.disabled = true; inB.required = false; }
                            return;
                        }

                        var valA = rawA !== '' ? parseInt(rawA, 10) : NaN;
                        var valB = rawB !== '' ? parseInt(rawB, 10) : NaN;
                        var bothValid = !isNaN(valA) && !isNaN(valB) && rawA !== '' && rawB !== '';

                        if (bothValid) {
                            if (valA === valB) {
                                hasTie = true;
                            } else if (valA > valB) {
                                winsA++;
                            } else {
                                winsB++;
                            }
                            if (winsA >= target || winsB >= target) {
                                decidedAt = idx;
                            }
                        }

                        var shouldShow = idx < target || (rawA !== '' || rawB !== '') || (idx === winsA + winsB);
                        row.hidden = !shouldShow;
                        if (inA) {
                            inA.disabled = !shouldShow;
                            inA.required = shouldShow;
                        }
                        if (inB) {
                            inB.disabled = !shouldShow;
                            inB.required = shouldShow;
                        }
                    });

                    if (liveEl) {
                        liveEl.textContent = winsA + ' – ' + winsB;
                    }
                    if (statusEl) {
                        statusEl.classList.remove('is-decided', 'is-warn');
                        if (hasTie) {
                            statusEl.textContent = 'Sin empates por set';
                            statusEl.classList.add('is-warn');
                        } else if (winsA >= target) {
                            statusEl.textContent = 'Gana ' + labelA;
                            statusEl.classList.add('is-decided');
                        } else if (winsB >= target) {
                            statusEl.textContent = 'Gana ' + labelB;
                            statusEl.classList.add('is-decided');
                        } else {
                            statusEl.textContent = 'Al mejor de ' + mejorDe;
                        }
                    }
                }

                document.querySelectorAll('[data-sets-form]').forEach(syncForm);
                document.addEventListener('input', function (ev) {
                    var form = ev.target && ev.target.closest ? ev.target.closest('[data-sets-form]') : null;
                    if (form) { syncForm(form); }
                });
            })();
        </script>
    <?php endif; ?>
<?php endif; ?>
