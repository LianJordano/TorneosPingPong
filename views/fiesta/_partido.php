<?php
/**
 * One match card with optional inline score form.
 * @var array<string,mixed> $p match row
 * @var array<int,array<string,mixed>> $equipos team id => row
 * @var array<string,mixed> $fiesta
 * @var bool $puedeAnotar
 */
$a = $p['equipo_a_id'] !== null ? ($equipos[(int) $p['equipo_a_id']] ?? null) : null;
$b = $p['equipo_b_id'] !== null ? ($equipos[(int) $p['equipo_b_id']] ?? null) : null;
$jugado = $p['estado'] === 'FINALIZADO';
$bye = (int) $p['es_bye'] === 1;
$ganador = $p['ganador_id'] !== null ? (int) $p['ganador_id'] : null;
$editable = $puedeAnotar && !$bye && $a !== null && $b !== null && $p['fase'] === $fiesta['estado'];
$lado = static function (?array $eq, ?string $marcador, bool $gana, string $vacio): string {
    $nombre = $eq !== null ? e((string) $eq['label']) : '<span class="muted">' . e($vacio) . '</span>';
    $clase = 'fiesta-match__team' . ($gana ? ' is-winner' : '') . ($eq === null ? ' is-empty' : '');
    $score = $marcador !== null ? '<span class="fiesta-match__score">' . e($marcador) . '</span>' : '';
    return '<div class="' . $clase . '"><span class="fiesta-match__name">' . $nombre . '</span>' . $score . '</div>';
};
?>
<article class="fiesta-match<?= $jugado && !$bye ? ' is-played' : '' ?><?= $bye ? ' is-bye' : '' ?>" id="partido-<?= e((string) $p['id']) ?>">
    <?= $lado($a, $jugado && !$bye ? (string) $p['marcador_a'] : null, $ganador !== null && $a !== null && $ganador === (int) $a['id'], $bye ? 'Pasa directo' : 'Por definir') ?>
    <?= $lado($b, $jugado && !$bye ? (string) $p['marcador_b'] : null, $ganador !== null && $b !== null && $ganador === (int) $b['id'], $bye ? 'Pasa directo' : 'Por definir') ?>

    <?php if ($editable): ?>
        <details class="fiesta-match__edit"<?= $jugado ? '' : ' open' ?>>
            <summary><?= $jugado ? icon('edit') . ' Corregir' : icon('scoreboard') . ' Cargar resultado' ?></summary>
            <form method="post" action="/fiesta/<?= e((string) $fiesta['id']) ?>/partidos/<?= e((string) $p['id']) ?>" class="fiesta-score">
                <?= csrf_field() ?>
                <label class="sr-only" for="ma-<?= e((string) $p['id']) ?>">Marcador de <?= e((string) $a['label']) ?></label>
                <input id="ma-<?= e((string) $p['id']) ?>" type="number" name="marcador_a" min="0" max="999" inputmode="numeric" required value="<?= e((string) ($p['marcador_a'] ?? '')) ?>">
                <span aria-hidden="true">–</span>
                <label class="sr-only" for="mb-<?= e((string) $p['id']) ?>">Marcador de <?= e((string) $b['label']) ?></label>
                <input id="mb-<?= e((string) $p['id']) ?>" type="number" name="marcador_b" min="0" max="999" inputmode="numeric" required value="<?= e((string) ($p['marcador_b'] ?? '')) ?>">
                <button type="submit" class="btn btn--primary btn--sm"><?= icon('check') ?> Guardar</button>
            </form>
        </details>
    <?php endif; ?>
</article>
