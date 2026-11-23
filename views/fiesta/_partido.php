<?php
/**
 * One match card with optional inline score form.
 * @var array<string,mixed> $p match row
 * @var array<int,array<string,mixed>> $equipos team id => row
 * @var array<string,mixed> $fiesta
 * @var bool $puedeAnotar
 * @var bool $puedeGestionar
 * @var array<int,true> $partidosBloqueados
 * @var bool|null $enBracket
 * @var string|null $etiquetaCruce
 */

use PingPong\Service\FiestaService;

$a = $p['equipo_a_id'] !== null ? ($equipos[(int) $p['equipo_a_id']] ?? null) : null;
$b = $p['equipo_b_id'] !== null ? ($equipos[(int) $p['equipo_b_id']] ?? null) : null;
$jugado = $p['estado'] === 'FINALIZADO';
$bye = (int) $p['es_bye'] === 1;
$ganador = $p['ganador_id'] !== null ? (int) $p['ganador_id'] : null;
$esFinal = $p['fase'] === 'ELIMINATORIA' && $p['siguiente_id'] === null;
$corrigiendoFinal = $esFinal && $fiesta['estado'] === 'FINALIZADO' && ($puedeGestionar ?? false);
$enCuadro = (bool) ($enBracket ?? false);
$abrirEditor = ($abrirPorDefecto ?? $enCuadro) && !$jugado;
$mejorDe = isset($mejorDePartido)
    ? (int) $mejorDePartido
    : FiestaService::mejorDeParaPartido($fiesta, $p);
$setsGuardados = [];
if (is_string($p['sets_json'] ?? null) && $p['sets_json'] !== '') {
    $decodedSets = json_decode($p['sets_json'], true);
    if (is_array($decodedSets)) {
        foreach ($decodedSets as $s) {
            if (is_array($s) && isset($s['a'], $s['b'])) {
                $setsGuardados[] = ['a' => (int) $s['a'], 'b' => (int) $s['b']];
            }
        }
    }
}
$editable = FiestaService::puedeEditarPartido(
    $p,
    $fiesta,
    $puedeAnotar,
    $puedeGestionar ?? false,
    $partidosBloqueados ?? [],
);

$estadoBadge = match (true) {
    $bye => ['Pase directo', 'bye'],
    $jugado => ['Finalizado', 'done'],
    $a !== null && $b !== null => ['Listo', 'ready'],
    default => ['Esperando', 'wait'],
};

$lado = static function (?array $eq, ?string $marcador, bool $gana, bool $pierde, bool $esBye, string $vacio): string {
    $seed = $eq !== null
        ? '<span class="fiesta-match__seed">#' . e((string) $eq['numero']) . '</span>'
        : '<span class="fiesta-match__seed fiesta-match__seed--empty" aria-hidden="true">–</span>';
    $nombre = $eq !== null
        ? e((string) $eq['label'])
        : '<span class="muted">' . e($vacio) . '</span>';
    $clase = 'fiesta-match__team'
        . ($gana ? ' is-winner' : '')
        . ($pierde ? ' is-loser' : '')
        . ($eq === null ? ' is-empty' : '')
        . ($esBye && $eq !== null ? ' is-bye-advancer' : '')
        . ($esBye && $eq === null ? ' is-bye-slot' : '');

    if ($esBye && $eq !== null) {
        $derecho = '<span class="fiesta-match__bye-tag">Avanza</span>';
    } elseif ($marcador !== null) {
        $derecho = '<span class="fiesta-match__score">' . e($marcador) . '</span>';
    } else {
        $derecho = '<span class="fiesta-match__score fiesta-match__score--empty" aria-hidden="true">–</span>';
    }

    return '<div class="' . $clase . '">' . $seed . '<span class="fiesta-match__name">' . $nombre . '</span>' . $derecho . '</div>';
};

$aGana = $ganador !== null && $a !== null && $ganador === (int) $a['id'];
$bGana = $ganador !== null && $b !== null && $ganador === (int) $b['id'];
$aPierde = $jugado && !$bye && $ganador !== null && $a !== null && !$aGana;
$bPierde = $jugado && !$bye && $ganador !== null && $b !== null && !$bGana;

$cardClasses = 'fiesta-match'
    . ($enCuadro ? ' fiesta-match--bracket' : '')
    . ($jugado && !$bye ? ' is-played' : '')
    . ($bye ? ' is-bye' : '')
    . ($esFinal ? ' is-final' : '');
?>
<article class="<?= $cardClasses ?>" id="partido-<?= e((string) $p['id']) ?>">
    <?php if ($enCuadro): ?>
        <header class="fiesta-match__head">
            <span class="fiesta-match__label"><?= e((string) ($etiquetaCruce ?? 'Cruce')) ?></span>
            <span class="fiesta-match__status fiesta-match__status--<?= e($estadoBadge[1]) ?>"><?= e($estadoBadge[0]) ?></span>
        </header>
    <?php endif; ?>

    <div class="fiesta-match__rows">
        <?= $lado($a, $jugado && !$bye ? (string) $p['marcador_a'] : null, $aGana, $aPierde, $bye, $bye ? 'Sin rival (BYE)' : 'Por definir') ?>
        <?= $lado($b, $jugado && !$bye ? (string) $p['marcador_b'] : null, $bGana, $bPierde, $bye, $bye ? 'Sin rival (BYE)' : 'Por definir') ?>
    </div>

    <?php if ($jugado && !$bye && $setsGuardados !== []): ?>
        <div class="fiesta-match__partials" aria-label="Parciales por set">
            <?php foreach ($setsGuardados as $sIdx => $set): ?>
                <span class="fiesta-partial" title="Set <?= e((string) ($sIdx + 1)) ?>">
                    <span class="fiesta-partial__idx">S<?= e((string) ($sIdx + 1)) ?></span>
                    <span class="<?= $set['a'] > $set['b'] ? 'is-high' : '' ?>"><?= e((string) $set['a']) ?></span>–<span class="<?= $set['b'] > $set['a'] ? 'is-high' : '' ?>"><?= e((string) $set['b']) ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($editable): ?>
        <details class="fiesta-match__edit"<?= $abrirEditor ? ' open' : '' ?>>
            <summary><?= $corrigiendoFinal ? icon('edit') . ' Corregir final' : ($jugado ? icon('edit') . ' Corregir marcador' : icon('scoreboard') . ' Cargar resultado') ?></summary>
            <?php if ($mejorDe === 1): ?>
                <form method="post" action="/fiesta/<?= e((string) $fiesta['id']) ?>/partidos/<?= e((string) $p['id']) ?>" class="fiesta-score">
                    <?= csrf_field() ?>
                    <div class="fiesta-score__field">
                        <label for="ma-<?= e((string) $p['id']) ?>" title="<?= e((string) $a['label']) ?>">#<?= e((string) $a['numero']) ?></label>
                        <input id="ma-<?= e((string) $p['id']) ?>" type="number" name="marcador_a" min="0" max="999" inputmode="numeric" required placeholder="0" value="<?= e((string) ($p['marcador_a'] ?? '')) ?>">
                    </div>
                    <span class="fiesta-score__sep" aria-hidden="true">–</span>
                    <div class="fiesta-score__field">
                        <label for="mb-<?= e((string) $p['id']) ?>" title="<?= e((string) $b['label']) ?>">#<?= e((string) $b['numero']) ?></label>
                        <input id="mb-<?= e((string) $p['id']) ?>" type="number" name="marcador_b" min="0" max="999" inputmode="numeric" required placeholder="0" value="<?= e((string) ($p['marcador_b'] ?? '')) ?>">
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm"><?= icon('check') ?> Guardar</button>
                </form>
            <?php else: ?>
                <?php
                $targetSets = intdiv($mejorDe, 2) + 1;
                $liveA = $jugado ? (int) ($p['marcador_a'] ?? 0) : 0;
                $liveB = $jugado ? (int) ($p['marcador_b'] ?? 0) : 0;
                ?>
                <form method="post"
                      action="/fiesta/<?= e((string) $fiesta['id']) ?>/partidos/<?= e((string) $p['id']) ?>"
                      class="fiesta-sets-form"
                      data-sets-form
                      data-mejor-de="<?= e((string) $mejorDe) ?>"
                      data-label-a="#<?= e((string) $a['numero']) ?>"
                      data-label-b="#<?= e((string) $b['numero']) ?>">
                    <?= csrf_field() ?>
                    <div class="fiesta-sets__live">
                        <div class="fiesta-sets__scoreboard">
                            <span class="fiesta-sets__team" title="<?= e((string) $a['label']) ?>">#<?= e((string) $a['numero']) ?></span>
                            <strong class="fiesta-sets__score" data-sets-live><?= e("{$liveA} – {$liveB}") ?></strong>
                            <span class="fiesta-sets__team" title="<?= e((string) $b['label']) ?>">#<?= e((string) $b['numero']) ?></span>
                        </div>
                        <span class="fiesta-sets__status" data-sets-status>Al mejor de <?= e((string) $mejorDe) ?></span>
                    </div>

                    <div class="fiesta-sets__rows">
                        <?php for ($s = 0; $s < $mejorDe; $s++): ?>
                            <?php
                            $valA = isset($setsGuardados[$s]) ? (string) $setsGuardados[$s]['a'] : '';
                            $valB = isset($setsGuardados[$s]) ? (string) $setsGuardados[$s]['b'] : '';
                            $ocultoInicial = $s >= $targetSets && $valA === '' && $valB === '';
                            ?>
                            <div class="fiesta-sets__row" data-set-row="<?= e((string) $s) ?>"<?= $ocultoInicial ? ' hidden' : '' ?>>
                                <span class="fiesta-sets__label">S<?= e((string) ($s + 1)) ?></span>
                                <input type="number"
                                       name="sets_a[]"
                                       min="0"
                                       max="999"
                                       inputmode="numeric"
                                       placeholder="0"
                                       aria-label="Set <?= e((string) ($s + 1)) ?> puntos #<?= e((string) $a['numero']) ?>"
                                       value="<?= e($valA) ?>"<?= $ocultoInicial ? ' disabled' : ' required' ?>>
                                <span class="fiesta-score__sep" aria-hidden="true">–</span>
                                <input type="number"
                                       name="sets_b[]"
                                       min="0"
                                       max="999"
                                       inputmode="numeric"
                                       placeholder="0"
                                       aria-label="Set <?= e((string) ($s + 1)) ?> puntos #<?= e((string) $b['numero']) ?>"
                                       value="<?= e($valB) ?>"<?= $ocultoInicial ? ' disabled' : ' required' ?>>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="fiesta-sets__actions">
                        <button type="submit" class="btn btn--primary btn--sm btn--block"><?= icon('check') ?> Guardar</button>
                    </div>
                </form>
            <?php endif; ?>
        </details>
    <?php endif; ?>
</article>
