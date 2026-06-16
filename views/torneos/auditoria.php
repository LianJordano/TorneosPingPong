<?php
/** @var array<string,mixed> $torneo */
/** @var list<array<string,mixed>> $registros */
$tid = (string) $torneo['id'];

$accionLabel = static fn (string $a): string => match ($a) {
    'CARGAR_MARCADOR' => 'Carga de marcador',
    'EDITAR_MARCADOR' => 'Edición de marcador',
    'CAMBIO_ESTADO'   => 'Cambio de estado',
    'GENERAR_FIXTURE' => 'Generación de fixture',
    'GENERAR_BRACKET' => 'Generación de bracket',
    'ELIMINAR'        => 'Eliminación',
    default           => $a,
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
?>
<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Audit trail</span>
            <h1>Auditoría · <?= e($torneo['nombre']) ?></h1>
            <p class="muted">Historial sensible de cambios con mejor lectura para revisión y control.</p>
        </div>
        <div class="hero-side">
            <div class="hero-card">
                <span class="hero-card__label">Registros</span>
                <strong><?= e((string) count($registros)) ?></strong>
                <small>Eventos de operación y trazabilidad.</small>
            </div>
            <div class="actions"><a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a></div>
        </div>
    </div>
</section>

<section class="card">
    <?php if ($registros === []): ?>
        <p class="muted">Todavía no hay registros de auditoría para este torneo.</p>
    <?php else: ?>
        <div class="audit-list">
            <?php foreach ($registros as $r): ?>
                <article class="audit-item">
                    <div class="audit-item__head">
                        <div>
                            <strong><?= e($r['usuario']) ?></strong>
                            <div class="audit-item__meta"><?= e($r['entidad']) ?> #<?= e((string) $r['entidad_id']) ?> · <?= e($r['created_at']) ?></div>
                        </div>
                        <span class="badge badge--blue"><?= e($accionLabel($r['accion'])) ?></span>
                    </div>
                    <div class="audit-item__grid">
                        <div class="audit-box"><strong>Antes</strong><span class="small muted"><?= e($resumenJson($r['valor_anterior'])) ?></span></div>
                        <div class="audit-box"><strong>Después</strong><span class="small"><?= e($resumenJson($r['valor_nuevo'])) ?></span></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
