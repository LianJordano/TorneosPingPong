<?php
/** @var array<string,mixed> $fiesta */
/** @var string $guion JSON stored at draw time */
$base = '/fiesta/' . (string) $fiesta['id'];
$payload = json_encode(
    json_decode($guion, true),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/fiesta">Torneo fiesta</a></li>
        <li><a href="<?= e($base) ?>"><?= e((string) $fiesta['nombre']) ?></a></li>
        <li><span aria-current="page">Ruleta</span></li>
    </ol>
</nav>

<section class="rt-stage" data-ruleta data-fiesta-id="<?= e((string) $fiesta['id']) ?>" aria-label="Ruleta del sorteo">
    <div class="rt-vignette" aria-hidden="true"></div>
    <header class="rt-bar" data-bar>
        <div class="rt-bar__title">
            <span class="rt-bar__eyebrow"><?= e((string) $fiesta['nombre']) ?></span>
            <span class="rt-bar__phase" data-phase>Sorteo</span>
        </div>
        <div class="rt-progress" aria-hidden="true"><span class="rt-progress__fill" data-progress></span></div>
        <span class="rt-bar__count" data-count>0 / 0</span>
        <div class="rt-controls">
            <button type="button" class="rt-btn" data-auto aria-pressed="true" title="Automático (P)"><?= icon('play') ?><span>Auto</span></button>
            <button type="button" class="rt-btn" data-sound aria-pressed="true" title="Sonido (M)"><?= icon('activity') ?><span>Sonido</span></button>
            <button type="button" class="rt-btn" data-skip-step title="Saltar giro actual (S)" disabled><?= icon('chevron-right') ?><span>Saltar giro</span></button>
            <button type="button" class="rt-btn" data-skip title="Revelar todo el sorteo"><?= icon('chevron-right') ?><span data-skip-label>Saltar todo</span></button>
            <button type="button" class="rt-btn rt-btn--icon" data-fullscreen aria-pressed="false" title="Pantalla completa (F)" aria-label="Pantalla completa"><?= icon('external') ?></button>
        </div>
    </header>

    <div class="rt-main" data-main>
        <div class="rt-wheel-zone">
            <p class="rt-pool-pill" data-pool-pill hidden>
                <span class="rt-pool-pill__dot" aria-hidden="true"></span>
                <span data-pool-count></span>
            </p>
            <p class="rt-caption" data-caption>¿Listos?</p>
            <div class="rt-matchup" data-matchup hidden>
                <span class="rt-matchup__name" data-matchup-cap></span>
                <span class="rt-matchup__sep" data-matchup-sep>+</span>
                <span class="rt-matchup__mystery" data-matchup-partner>¿???</span>
            </div>
            <div class="rt-reel" data-reel aria-hidden="true">
                <div class="rt-reel__strip" data-strip></div>
                <span class="rt-reel__marker rt-reel__marker--l"></span>
                <span class="rt-reel__marker rt-reel__marker--r"></span>
            </div>
            <p class="rt-result" data-result></p>
            <button type="button" class="btn btn--primary btn--lg rt-next" data-next hidden>
                <?= icon('chevron-right') ?> Siguiente <kbd>Espacio</kbd>
            </button>
        </div>
        <div class="rt-board" data-board></div>
    </div>

    <div class="rt-overlay" data-intro>
        <span class="rt-overlay__wheel" aria-hidden="true"><?= icon('shuffle') ?></span>
        <h1 class="rt-overlay__title">¡Que gire la ruleta!</h1>
        <p class="rt-overlay__text" data-intro-text></p>
        <div class="actions rt-overlay__actions">
            <button type="button" class="btn btn--primary btn--lg rt-overlay__start" data-resume hidden><?= icon('play') ?> <span data-resume-label>Continuar sorteo</span></button>
            <button type="button" class="btn btn--primary btn--lg rt-overlay__start" data-start><?= icon('play') ?> <span data-start-label>Empezar el sorteo</span></button>
        </div>
        <p class="rt-overlay__hint">Tip: poné pantalla completa y desactivá “Auto” para revelar cada uno con la barra espaciadora.</p>
    </div>

    <div class="rt-overlay rt-overlay--count" data-countdown hidden><span data-countdown-num>3</span></div>

    <div class="rt-overlay rt-overlay--end" data-end hidden>
        <span class="rt-overlay__wheel rt-overlay__wheel--gold" aria-hidden="true"><?= icon('trophy') ?></span>
        <h2 class="rt-overlay__title">¡Sorteo completo!</h2>
        <p class="rt-overlay__text">Ya están los equipos y los cruces. Que empiece el juego.</p>
        <div class="actions">
            <a class="btn btn--primary btn--lg" href="<?= e($base) ?>"><?= icon('bracket') ?> Ver el cuadro</a>
            <button type="button" class="btn btn--secondary btn--lg" data-replay><?= icon('refresh') ?> Ver de nuevo</button>
        </div>
    </div>

    <div class="rt-confetti" data-confetti aria-hidden="true"></div>
    <p class="sr-only" aria-live="polite" aria-atomic="true" data-live></p>
</section>

<script type="application/json" id="fiesta-guion"><?= $payload ?></script>
<script src="<?= asset('assets/js/ruleta.js') ?>" defer></script>
