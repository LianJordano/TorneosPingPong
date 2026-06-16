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
<section class="page-head">
    <div>
        <h1>Auditoría · <?= e($torneo['nombre']) ?></h1>
        <p class="muted">Bitácora de cambios sensibles (marcadores y estado del torneo).</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="/torneos/<?= e($tid) ?>">Volver</a>
</section>

<section class="card">
    <?php if ($registros === []): ?>
        <p class="muted">Todavía no hay registros de auditoría para este torneo.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Fecha</th><th>Usuario</th><th>Entidad</th><th>Acción</th><th>Antes</th><th>Después</th></tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
                <tr>
                    <td class="small"><?= e($r['created_at']) ?></td>
                    <td><?= e($r['usuario']) ?></td>
                    <td><?= e($r['entidad']) ?> <span class="muted">#<?= e((string) $r['entidad_id']) ?></span></td>
                    <td><span class="badge badge--blue"><?= e($accionLabel($r['accion'])) ?></span></td>
                    <td class="small muted"><?= e($resumenJson($r['valor_anterior'])) ?></td>
                    <td class="small"><?= e($resumenJson($r['valor_nuevo'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
