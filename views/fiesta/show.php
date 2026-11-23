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

$puedeGestionar = $rol?->puedeGestionar() ?? false;
$puedeAnotar = $rol?->puedeAnotar() ?? false;
$base = '/fiesta/' . (string) $fiesta['id'];
$estado = (string) $fiesta['estado'];
$dobles = $fiesta['modalidad'] === 'DOBLES';
$conGrupos = $fiesta['formato'] === 'GRUPOS';
$n = count($participantes);
$numEquipos = $dobles ? intdiv($n, 2) : $n;
$clasifican = (int) $fiesta['clasificados_por_grupo'];

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
            <span class="meta-pill"><?= icon($conGrupos ? 'layers' : 'bracket') ?>
                <?= $conGrupos ? e("{$fiesta['num_grupos']} grupos · clasifican {$clasifican}") : 'Eliminación directa' ?></span>
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
                    <?php if ($dobles): ?>
                        Primero salen, de a uno, los primeros integrantes de cada pareja. Después la ruleta gira otra vez para cada uno y revela a su compañero.
                    <?php else: ?>
                        La ruleta va sacando a los jugadores de a uno y los ubica en su lugar del cuadro.
                    <?php endif; ?>
                    <?= $conGrupos ? 'Al final reparte los equipos en los grupos.' : 'Al final arma los cruces de la eliminación directa.' ?>
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
                    <?php if ($eq['grupo'] !== null): ?>
                        <span class="badge badge--blue">Grupo <?= e(chr(64 + (int) $eq['grupo'])) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($grupos !== []): ?>
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
                                Zona de clasificación (Top <?= e((string) $clasifican) ?>)
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
                                <span class="fiesta-bracket__meta">
                                    <?= $esRondaFinal
                                        ? ($resueltos > 0 ? 'Definida' : 'Título en juego')
                                        : e("{$resueltos}/{$jugables} jugados") ?>
                                </span>
                            </header>
                            <div class="fiesta-bracket__matches">
                                <?php foreach ($partidosRonda as $idx => $p): ?>
                                    <?php
                                    $pairPos = ($idx % 2 === 0) ? 'top' : 'bottom';
                                    $slotResuelto = $p['ganador_id'] !== null;
                                    $etiquetaCruce = $esRondaFinal
                                        ? 'GRAN FINAL'
                                        : 'Cruce ' . (string) ($idx + 1);
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
<?php endif; ?>
