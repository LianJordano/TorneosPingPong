<?php
/**
 * Shared <head> content for every layout.
 * @var string|null $title
 * @var string|null $appName
 */
$styles = [
    'assets/css/core/tokens.css',
    'assets/css/core/base.css',
    'assets/css/core/layout.css',
    'assets/css/core/components.css',
    'assets/css/pages/domain.css',
    'assets/css/pages/torneos.css',
    'assets/css/pages/partidos.css',
    'assets/css/pages/jugadores.css',
    'assets/css/pages/admin.css',
    'assets/css/pages/publico.css',
    'assets/css/core/utilities.css',
];
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= e($title ?? 'Inicio') ?> · <?= e($appName ?? 'Torneos Ping Pong') ?></title>
<meta name="description" content="Gestión de torneos de tenis de mesa: ligas, llaves, ranking y vista pública.">

<script>
    // Apply the saved theme before first paint (avoids a light/dark flash).
    (function () {
        try {
            var t = localStorage.getItem('pp-theme');
            if (t === 'light' || t === 'dark') { document.documentElement.setAttribute('data-theme', t); }
        } catch (e) {}
    })();
</script>

<!-- PWA -->
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1120" media="(prefers-color-scheme: dark)">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Ping Pong">
<meta name="mobile-web-app-capable" content="yes">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<?php foreach ($styles as $style): ?>
<link rel="stylesheet" href="<?= asset($style) ?>">
<?php endforeach; ?>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
