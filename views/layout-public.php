<?php
/**
 * Minimal shell for the public spectator board (/publico/*): no login,
 * no sidebar or tabbar. Built for a TV/projector and for spectators' phones.
 *
 * @var string $content
 * @var array|null $torneo
 * @var \PingPong\Domain\EstadoTorneo|null $estado
 */
$hasBoard = isset($torneo, $estado);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="is-public">
    <?php require __DIR__ . '/partials/icons.php'; ?>
    <a class="skip-link" href="#main">Saltar al contenido</a>

    <header class="public-header">
        <div class="brand">
            <span class="brand__mark"><?= icon('paddle') ?></span>
            <span class="brand__text">
                <strong>Torneos Ping Pong</strong>
                <small>Pantalla pública</small>
            </span>
        </div>

        <?php if ($hasBoard): ?>
            <div class="public-header__context">
                <span class="public-header__name"><?= e($torneo['nombre']) ?></span>
                <span id="public-status" data-live>
                    <span class="badge badge--dot <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span>
                </span>
            </div>
        <?php endif; ?>

        <div class="public-header__tools">
            <?php if ($hasBoard): ?>
                <p class="public-live" data-live-indicator>
                    <span class="public-live__dot" aria-hidden="true"></span>
                    <span>
                        <span class="public-live__label" data-live-stamp aria-live="polite">Actualizado a las <?= e(date('H:i')) ?></span>
                        <span class="public-live__ago" data-live-ago>Se actualiza cada minuto</span>
                    </span>
                </p>
                <button class="btn btn--ghost btn--icon hide-mobile" type="button" data-fullscreen aria-label="Pantalla completa" title="Pantalla completa" hidden>
                    <?= icon('tv') ?>
                </button>
            <?php endif; ?>
            <button class="btn btn--ghost btn--icon" type="button" data-theme-toggle aria-pressed="false" aria-label="Cambiar tema">
                <?= icon('moon') ?>
            </button>
        </div>
    </header>

    <main id="main" class="public-main" tabindex="-1">
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $content ?? '' ?>
    </main>

<?php if ($hasBoard): ?>
    <script>
    // Live board: refetch this page every minute and swap only the live parts
    // ([data-live-region] and [data-live] elements, matched by id) so the
    // screen never flashes. Pauses while the tab is hidden.
    (function () {
        var INTERVAL = 60000;
        var stamp = document.querySelector('[data-live-stamp]');
        var ago = document.querySelector('[data-live-ago]');
        var indicator = document.querySelector('[data-live-indicator]');
        var last = Date.now();
        var failures = 0;
        var busy = false;

        function hhmm(d) {
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }

        function renderAgo() {
            if (!ago || failures > 0) { return; }
            var min = Math.floor((Date.now() - last) / 60000);
            ago.textContent = min < 1 ? 'Hace instantes' : 'Hace ' + min + ' min';
        }

        function swap(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var targets = document.querySelectorAll('[data-live-region], [data-live]');
            if (!doc.querySelector('[data-live-region]')) { throw new Error('missing live region'); }
            var x = window.scrollX, y = window.scrollY;
            var scrollers = Array.prototype.map.call(document.querySelectorAll('.bracket-shell'), function (el) { return el.scrollLeft; });
            targets.forEach(function (el) {
                var fresh = el.id ? doc.getElementById(el.id) : doc.querySelector('[data-live-region]');
                if (fresh) { el.innerHTML = fresh.innerHTML; }
            });
            document.querySelectorAll('.bracket-shell').forEach(function (el, i) { el.scrollLeft = scrollers[i] || 0; });
            window.scrollTo(x, y);
        }

        function refresh() {
            if (busy || document.hidden) { return; }
            busy = true;
            fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    return res.text();
                })
                .then(function (html) {
                    swap(html);
                    failures = 0;
                    last = Date.now();
                    indicator && indicator.classList.remove('is-offline');
                    if (stamp) { stamp.textContent = 'Actualizado a las ' + hhmm(new Date(last)); }
                    renderAgo();
                })
                .catch(function () {
                    failures++;
                    // After repeated failures fall back to a full reload.
                    if (failures >= 3) { location.reload(); return; }
                    indicator && indicator.classList.add('is-offline');
                    if (ago) { ago.textContent = 'Sin conexión, reintentando'; }
                })
                .then(function () { busy = false; });
        }

        setInterval(function () {
            if (Date.now() - last >= INTERVAL - 1000) { refresh(); }
            renderAgo();
        }, 15000);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && Date.now() - last >= INTERVAL) { refresh(); }
        });

        // Optional full-screen button for TVs and projectors.
        var fs = document.querySelector('[data-fullscreen]');
        if (fs && document.fullscreenEnabled) {
            fs.hidden = false;
            fs.addEventListener('click', function () {
                if (document.fullscreenElement) { document.exitFullscreen(); }
                else { document.documentElement.requestFullscreen().catch(function () {}); }
            });
        }
    })();
    </script>
<?php endif; ?>
</body>
</html>
