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
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar__inner">
            <a class="brand" href="/">🏓 <?= e($appName ?? 'Torneos Ping Pong') ?></a>
            <?php if (!empty($authUser)): ?>
                <nav class="nav">
                    <a href="/torneos">Torneos</a>
                    <a href="/personas">Jugadores</a>
                    <a href="/glosario">Glosario</a>
                    <span class="nav__user">
                        <?= e($authUser['nombre']) ?>
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

    <main class="container">
        <?php foreach ($flashes ?? [] as $flash): ?>
            <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

        <?= $content ?? '' ?>
    </main>

    <footer class="footer">
        <div class="container">
            Sistema de torneos de ping pong · PHP <?= e($phpVersion ?? PHP_VERSION) ?>
        </div>
    </footer>
</body>
</html>
