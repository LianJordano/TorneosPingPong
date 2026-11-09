<?php
/** @var array|null $authUser */
/** @var \PingPong\Domain\Rol|null $rol */
/** @var array $flashes */
/** @var string $content */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$appName = $appName ?? 'Torneos Ping Pong';
$isAuth = !empty($authUser);

$navGroups = [
    'Competencia' => [
        ['href' => '/', 'label' => 'Inicio', 'icon' => 'home'],
        ['href' => '/torneos', 'label' => 'Torneos', 'icon' => 'trophy'],
        ['href' => '/ranking', 'label' => 'Ranking', 'icon' => 'ranking'],
        ['href' => '/personas', 'label' => 'Jugadores', 'icon' => 'users'],
    ],
];
if ($puedeGestionar) {
    $navGroups['Administración'] = [
        ['href' => '/configuracion', 'label' => 'Configuración', 'icon' => 'settings'],
    ];
}
$navGroups['Ayuda'] = [
    ['href' => '/glosario', 'label' => 'Glosario y reglas', 'icon' => 'book'],
    ['href' => '/cuenta/password', 'label' => 'Mi cuenta', 'icon' => 'key'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="<?= $isAuth ? 'is-auth has-tabbar' : 'is-guest' ?>">
    <?php require __DIR__ . '/partials/icons.php'; ?>
    <a class="skip-link" href="#main">Saltar al contenido</a>

<?php if ($isAuth): ?>
    <div class="shell">
        <aside class="sidebar" id="nav-principal" aria-label="Menú principal">
            <a class="brand" href="/">
                <span class="brand__mark"><?= icon('paddle') ?></span>
                <span class="brand__text">
                    <strong><?= e($appName) ?></strong>
                    <small>Gestión de torneos</small>
                </span>
            </a>

            <nav class="side-nav" aria-label="Secciones">
                <?php foreach ($navGroups as $groupTitle => $links): ?>
                    <div class="side-nav__group">
                        <p class="side-nav__title"><?= e($groupTitle) ?></p>
                        <?php foreach ($links as $link): ?>
                            <a class="side-nav__link" href="<?= e($link['href']) ?>"<?= nav_current($link['href']) ?>>
                                <?= icon($link['icon']) ?>
                                <span><?= e($link['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar__footer">
                <button id="btn-instalar" class="btn btn--secondary btn--sm btn--block" type="button" hidden>
                    <?= icon('download') ?> Instalar app
                </button>
                <button class="btn btn--ghost btn--sm btn--block" type="button" data-theme-toggle aria-pressed="false">
                    <?= icon('moon') ?> <span data-theme-label>Tema oscuro</span>
                </button>
                <div class="user-card">
                    <span class="avatar" aria-hidden="true"><?= e(initials($authUser['nombre'] ?? '')) ?></span>
                    <span class="user-card__text">
                        <span class="user-card__name"><?= e($authUser['nombre'] ?? '') ?></span>
                        <span class="user-card__role"><?= e($rol?->label() ?? '') ?></span>
                    </span>
                    <form method="post" action="/logout" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--icon btn--sm" title="Cerrar sesión" aria-label="Cerrar sesión">
                            <?= icon('logout') ?>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        <div id="nav-overlay" class="nav-overlay" hidden></div>

        <div class="shell__main">
            <header class="topbar">
                <button id="nav-toggle" class="nav-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav-principal">
                    <?= icon('menu', 'icon--lg') ?>
                </button>
                <a class="brand" href="/">
                    <span class="brand__mark"><?= icon('paddle') ?></span>
                    <span class="brand__text"><strong><?= e($appName) ?></strong></span>
                </a>
                <span class="topbar__spacer"></span>
                <span class="avatar" title="<?= e($authUser['nombre'] ?? '') ?>" aria-hidden="true"><?= e(initials($authUser['nombre'] ?? '')) ?></span>
            </header>

            <main id="main" class="page" tabindex="-1">
                <?php require __DIR__ . '/partials/flashes.php'; ?>
                <?= $content ?? '' ?>
            </main>

            <footer class="footer">
                <?= e($appName) ?> · <?= date('Y') ?>
            </footer>
        </div>
    </div>

    <nav class="tabbar" aria-label="Accesos rápidos">
        <a class="tabbar__link" href="/"<?= nav_current('/') ?>><?= icon('home') ?><span>Inicio</span></a>
        <a class="tabbar__link" href="/torneos"<?= nav_current('/torneos') ?>><?= icon('trophy') ?><span>Torneos</span></a>
        <a class="tabbar__link" href="/ranking"<?= nav_current('/ranking') ?>><?= icon('ranking') ?><span>Ranking</span></a>
        <a class="tabbar__link" href="/personas"<?= nav_current('/personas') ?>><?= icon('users') ?><span>Jugadores</span></a>
    </nav>
<?php else: ?>
    <header class="guest-header">
        <a class="brand" href="/">
            <span class="brand__mark"><?= icon('paddle') ?></span>
            <span class="brand__text">
                <strong><?= e($appName) ?></strong>
                <small>Gestión de torneos</small>
            </span>
        </a>
        <button class="btn btn--ghost btn--icon" type="button" data-theme-toggle aria-pressed="false" aria-label="Cambiar tema">
            <?= icon('moon') ?>
        </button>
    </header>
    <main id="main" class="guest-main" tabindex="-1">
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $content ?? '' ?>
    </main>
<?php endif; ?>
</body>
</html>
