<?php
/**
 * Tournament sub-navigation shared by the hub and every tournament sub-page.
 *
 * Expects in scope:
 * @var array $torneo                      with 'id' and 'estado'
 * @var string $tabActual                  resumen|partidos|tabla|llaves|podio|premios|auditoria
 * @var \PingPong\Domain\Rol|null $rol
 *
 * Tabs not yet available in the current state are shown disabled, so users
 * can see what comes next in the tournament flow.
 */

use PingPong\Domain\EstadoTorneo;

$tabEstado = EstadoTorneo::tryFrom((string) ($torneo['estado'] ?? ''));
$tabBase = '/torneos/' . (int) $torneo['id'];
$tabConLiga = in_array($tabEstado, [EstadoTorneo::FASE_LIGA, EstadoTorneo::FASE_ELIMINATORIA, EstadoTorneo::FINALIZADO], true);
$tabConLlaves = in_array($tabEstado, [EstadoTorneo::FASE_ELIMINATORIA, EstadoTorneo::FINALIZADO], true);

$tabItems = [
    'resumen'  => ['href' => $tabBase, 'label' => 'Resumen', 'icon' => 'home', 'ok' => true],
    'partidos' => ['href' => $tabBase . '/partidos', 'label' => 'Partidos', 'icon' => 'paddle', 'ok' => $tabConLiga],
    'tabla'    => ['href' => $tabBase . '/tabla', 'label' => 'Tabla', 'icon' => 'table', 'ok' => $tabConLiga],
    'llaves'   => ['href' => $tabBase . '/bracket', 'label' => 'Llaves', 'icon' => 'bracket', 'ok' => $tabConLlaves],
    'podio'    => ['href' => $tabBase . '/podio', 'label' => 'Podio', 'icon' => 'podium', 'ok' => $tabConLlaves],
];
if ($rol?->puedeGestionar() ?? false) {
    $tabItems['premios'] = ['href' => $tabBase . '/premios', 'label' => 'Premios', 'icon' => 'gift', 'ok' => true];
    $tabItems['auditoria'] = ['href' => $tabBase . '/auditoria', 'label' => 'Auditoría', 'icon' => 'history', 'ok' => true];
}
?>
<nav class="tabs no-print" aria-label="Secciones del torneo">
    <?php foreach ($tabItems as $tabKey => $tab): ?>
        <?php if ($tabKey === ($tabActual ?? '')): ?>
            <a class="tabs__link" href="<?= e($tab['href']) ?>" aria-current="page"><?= icon($tab['icon']) ?> <?= e($tab['label']) ?></a>
        <?php elseif ($tab['ok']): ?>
            <a class="tabs__link" href="<?= e($tab['href']) ?>"><?= icon($tab['icon']) ?> <?= e($tab['label']) ?></a>
        <?php else: ?>
            <span class="tabs__link is-disabled" aria-disabled="true" title="Disponible más adelante"><?= icon($tab['icon']) ?> <?= e($tab['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
