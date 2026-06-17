<?php
/** @var array|null $authUser */
/** @var \PingPong\Domain\Rol|null $rol */
/** @var array $flashes */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Inicio') ?> · <?= e($appName ?? 'Torneos Ping Pong') ?></title>

    <!-- PWA: instalable como app en mobile -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Ping Pong">
    <meta name="mobile-web-app-capable" content="yes">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="app-bg app-bg--one"></div>
    <div class="app-bg app-bg--two"></div>

    <header class="topbar">
        <div class="container topbar__inner">
            <a class="brand" href="/">
                <span class="brand__mark">🏓</span>
                <span>
                    <strong><?= e($appName ?? 'Torneos Ping Pong') ?></strong>
                    <small>Centro de control competitivo</small>
                </span>
            </a>

            <?php if (!empty($authUser)): ?>
                <nav class="nav">
                    <button id="btn-instalar" class="btn btn--primary btn--sm" type="button" hidden>⬇️ Instalar app</button>
                    <a href="/torneos">Torneos</a>
                    <a href="/ranking">Ranking</a>
                    <a href="/personas">Jugadores</a>
                    <?php if ($puedeGestionar): ?>
                        <a href="/configuracion">Configuración</a>
                    <?php endif; ?>
                    <a href="/glosario">Glosario</a>
                    <span class="nav__user">
                        <span class="nav__user-name"><?= e($authUser['nombre']) ?></span>
                        <small class="badge badge--gray"><?= e($rol?->label() ?? '') ?></small>
                    </span>
                    <form method="post" action="/logout" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Salir</button>
                    </form>
                </nav>
            <?php endif; ?>
        </div>
    </header>

    <main class="container page-shell">
        <?php foreach ($flashes ?? [] as $flash): ?>
            <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

        <?= $content ?? '' ?>
    </main>

    <footer class="footer">
        <div class="container footer__inner">
            <div>
                <strong><?= e($appName ?? 'Torneos Ping Pong') ?></strong>
                <p>Gestión premium para ligas, bracket y vista pública en tiempo real.</p>
            </div>
            <div class="footer__meta">
                <span>PHP <?= e($phpVersion ?? PHP_VERSION) ?></span>
                <span>Pure MVC</span>
                <span>Ready to compete</span>
            </div>
        </div>
    </footer>

    <script>
        // Registro del Service Worker (necesario para instalar la PWA).
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
            });
        }

        // Botón "Instalar app" (Android / Chromium). Solo aparece si el navegador
        // considera la app instalable y todavía no está instalada.
        (function () {
            var promptInstalar = null;
            var btn = document.getElementById('btn-instalar');

            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                promptInstalar = e;
                if (btn) {
                    btn.hidden = false;
                }
            });

            if (btn) {
                btn.addEventListener('click', function () {
                    if (!promptInstalar) {
                        return;
                    }
                    promptInstalar.prompt();
                    promptInstalar.userChoice.finally(function () {
                        promptInstalar = null;
                        btn.hidden = true;
                    });
                });
            }

            window.addEventListener('appinstalled', function () {
                promptInstalar = null;
                if (btn) {
                    btn.hidden = true;
                }
            });
        })();
    </script>
</body>
</html>
