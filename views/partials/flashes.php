<?php
/** @var array $flashes list of ['type' => success|error|info|warning, 'message' => string] */
$flashIcons = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert', 'info' => 'info'];
foreach ($flashes ?? [] as $flash):
    $type = (string) ($flash['type'] ?? 'info');
    $isError = $type === 'error' || $type === 'warning';
?>
    <div class="flash flash--<?= e($type) ?>" role="<?= $isError ? 'alert' : 'status' ?>">
        <?= icon($flashIcons[$type] ?? 'info') ?>
        <div class="flash__body"><?= e($flash['message'] ?? '') ?></div>
        <button type="button" class="flash__close" data-dismiss="flash" aria-label="Cerrar mensaje"><?= icon('x', 'icon--sm') ?></button>
    </div>
<?php endforeach; ?>
