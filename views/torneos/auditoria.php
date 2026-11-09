<?php
/** @var array<string,mixed> $torneo */
/** @var list<array<string,mixed>> $registros */


$tid = (string) $torneo['id'];
$base = '/torneos/' . $tid;
$total = count($registros);

$accionLabel = static fn (string $a): string => match ($a) {
    'CARGAR_MARCADOR' => 'Carga de marcador',
    'EDITAR_MARCADOR' => 'Edición de marcador',
    'CAMBIO_ESTADO'   => 'Cambio de estado',
    'GENERAR_FIXTURE' => 'Generación de fixture',
    'GENERAR_BRACKET' => 'Generación de bracket',
    'ELIMINAR'        => 'Eliminación',
    default           => $a,
};
$accionBadge = static fn (string $a): string => match ($a) {
    'EDITAR_MARCADOR' => 'badge--amber',
    'CAMBIO_ESTADO'   => 'badge--purple',
    'GENERAR_FIXTURE', 'GENERAR_BRACKET' => 'badge--cyan',
    'ELIMINAR'        => 'badge--red',
    default           => 'badge--blue',
};
$accionIcono = static fn (string $a): string => match ($a) {
    'CARGAR_MARCADOR' => 'scoreboard',
    'EDITAR_MARCADOR' => 'edit',
    'CAMBIO_ESTADO'   => 'refresh',
    'GENERAR_FIXTURE' => 'shuffle',
    'GENERAR_BRACKET' => 'bracket',
    'ELIMINAR'        => 'trash',
    default           => 'history',
};

$resumenJson = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '—';
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return '—';
    }
    $partes = [];
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $v = count($v) . ' ítem(s)';
        }
        $partes[] = $k . ': ' . (is_scalar($v) ? (string) $v : '—');
    }
    return implode(' · ', $partes);
};
$fechaHora = static function (?string $valor): string {
    $ts = $valor ? strtotime($valor) : false;
    return $ts === false ? (string) $valor : date('d/m/Y H:i', $ts);
};

?>
<nav aria-label="Ruta">
    <ol class="breadcrumbs">
        <li><a href="/torneos">Torneos</a></li>
        <li><a href="<?= e($base) ?>"><?= e($torneo['nombre']) ?></a></li>
        <li><span aria-current="page">Auditoría</span></li>
    </ol>
</nav>

<header class="page-head">
    <div>
        <span class="eyebrow"><?= e($torneo['nombre']) ?></span>
        <h1>Auditoría</h1>
        <p>Historial de cambios sensibles del torneo: quién hizo qué, cuándo y con qué valores antes y después.</p>
    </div>
    <span class="badge badge--gray"><?= icon('history') ?> <?= e((string) $total) ?> <?= $total === 1 ? 'registro' : 'registros' ?></span>
</header>

<?php $tabActual = 'auditoria'; require __DIR__ . '/../partials/torneo-tabs.php'; ?>

<?php if ($registros === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('history') ?></span>
        <p class="empty-state__title">Sin registros todavía</p>
        <p>Acá vas a ver las cargas y ediciones de marcadores, los cambios de estado y la generación del fixture y del bracket.</p>
    </div>
<?php else: ?>
    <ol class="audit-list">
        <?php foreach ($registros as $r): ?>
            <li class="audit-item">
                <span class="audit-item__icon"><?= icon($accionIcono($r['accion'])) ?></span>
                <article class="card audit-item__card">
                    <header class="audit-item__head">
                        <span class="badge badge--dot <?= e($accionBadge($r['accion'])) ?>"><?= e($accionLabel($r['accion'])) ?></span>
                        <time class="muted small" datetime="<?= e((string) $r['created_at']) ?>"><?= e($fechaHora($r['created_at'])) ?></time>
                    </header>
                    <p class="audit-item__who">
                        <span class="strong"><?= e($r['usuario']) ?></span>
                        <span class="muted small">· <?= e($r['entidad']) ?> #<?= e((string) $r['entidad_id']) ?></span>
                    </p>
                    <dl class="audit-diff">
                        <div class="audit-diff__box">
                            <dt>Antes</dt>
                            <dd class="muted"><?= e($resumenJson($r['valor_anterior'])) ?></dd>
                        </div>
                        <div class="audit-diff__box audit-diff__box--new">
                            <dt>Después</dt>
                            <dd><?= e($resumenJson($r['valor_nuevo'])) ?></dd>
                        </div>
                    </dl>
                </article>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
