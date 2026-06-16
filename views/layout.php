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
                    <small>Competition Control Center</small>
                </span>
            </a>

            <?php if (!empty($authUser)): ?>
                <nav class="nav">
                    <a href="/torneos">Torneos</a>
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
</body>
</html>
