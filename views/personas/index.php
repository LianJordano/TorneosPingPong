<?php
/** @var list<array<string,mixed>> $personas */
/** @var bool $incluirInactivas */
/** @var string $q */
/** @var int $total */
/** @var int $pagina */
/** @var int $totalPaginas */
/** @var int $desde */
/** @var int $hasta */
/** @var string $orden */
/** @var string $dir */
$puedeGestionar = $rol?->puedeGestionar() ?? false;

/** Parámetros base de la URL (sin pagina), conservando filtros y orden. */
$baseParams = static function () use ($q, $incluirInactivas, $orden, $dir): array {
    $p = [];
    if ($q !== '') { $p['q'] = $q; }
    if ($incluirInactivas) { $p['inactivas'] = 1; }
    $p['orden'] = $orden;
    $p['dir'] = $dir;
    return $p;
};
$urlPagina = static fn (int $n): string => '/personas?' . http_build_query($baseParams() + ['pagina' => $n]);

/** Encabezado ordenable: alterna dirección si ya es la columna activa. */
$th = static function (string $col, string $etiqueta) use ($q, $incluirInactivas, $orden, $dir): string {
    $nuevaDir = ($orden === $col && $dir === 'asc') ? 'desc' : 'asc';
    $params = [];
    if ($q !== '') { $params['q'] = $q; }
    if ($incluirInactivas) { $params['inactivas'] = 1; }
    $params['orden'] = $col;
    $params['dir'] = $nuevaDir;
    $flecha = $orden === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $cls = $orden === $col ? ' class="th-activo"' : '';
    return '<th' . $cls . '><a href="/personas?' . e(http_build_query($params)) . '">' . e($etiqueta) . e($flecha) . '</a></th>';
};
?>
<section class="page-head">
    <h1>Jugadores <span class="badge badge--gray"><?= e((string) $total) ?></span></h1>
    <div class="actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--ghost btn--sm" href="/areas">Áreas</a>
            <a class="btn btn--ghost btn--sm" href="/niveles">Niveles</a>
            <a class="btn btn--primary" href="/personas/nueva">Nuevo jugador</a>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <form method="get" action="/personas" class="filtros">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, apellido, área, nivel, identificador o correo…" class="filtros__q">
        <label class="filtros__check">
            <input type="checkbox" name="inactivas" value="1" <?= $incluirInactivas ? 'checked' : '' ?>>
            Incluir inactivos
        </label>
        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
        <?php if ($q !== '' || $incluirInactivas): ?>
            <a class="btn btn--ghost btn--sm" href="/personas">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if ($total === 0): ?>
        <p class="muted"><?= $q !== '' ? 'No hay jugadores que coincidan con la búsqueda.' : 'No hay jugadores cargados.' ?></p>
    <?php else: ?>
        <p class="muted small">Mostrando <?= e((string) $desde) ?>–<?= e((string) $hasta) ?> de <?= e((string) $total) ?> jugador<?= $total === 1 ? '' : 'es' ?>.</p>

        <table class="table table--sortable">
            <thead>
                <tr>
                    <?= $th('nombre', 'Apellido y nombre') ?>
                    <?= $th('area', 'Área') ?>
                    <?= $th('nivel', 'Nivel') ?>
                    <?= $th('identificador', 'Identificador') ?>
                    <?= $th('estado', 'Estado') ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($personas as $p): ?>
                <tr class="<?= (int) $p['activo'] === 1 ? '' : 'row--muted' ?>">
                    <td><strong><?= e($p['apellido']) ?>, <?= e($p['nombre']) ?></strong></td>
                    <td><?= e($p['area'] ?? '—') ?></td>
                    <td><?= e($p['nivel'] ?? '—') ?></td>
                    <td><?= e($p['identificador_interno'] ?? '—') ?></td>
                    <td>
                        <?php if ((int) $p['activo'] === 1): ?>
                            <span class="badge badge--green">Activo</span>
                        <?php else: ?>
                            <span class="badge badge--red">Baja</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <?php if ($puedeGestionar): ?>
                            <a class="link" href="/personas/<?= e((string) $p['id']) ?>/editar">Editar</a>
                            <?php if ((int) $p['activo'] === 1): ?>
                                <form method="post" action="/personas/<?= e((string) $p['id']) ?>/desactivar" class="inline"
                                      onsubmit="return confirm('¿Dar de baja a este jugador?');">
                                    <?= csrf_field() ?>
                                    <button class="link link--danger" type="submit">Baja</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="/personas/<?= e((string) $p['id']) ?>/activar" class="inline">
                                    <?= csrf_field() ?>
                                    <button class="link" type="submit">Reactivar</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPaginas > 1): ?>
            <nav class="paginador">
                <?php if ($pagina > 1): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e($urlPagina($pagina - 1)) ?>">← Anterior</a>
                <?php else: ?>
                    <span class="btn btn--ghost btn--sm is-disabled">← Anterior</span>
                <?php endif; ?>

                <span class="paginador__info">Página <?= e((string) $pagina) ?> de <?= e((string) $totalPaginas) ?></span>

                <?php if ($pagina < $totalPaginas): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e($urlPagina($pagina + 1)) ?>">Siguiente →</a>
                <?php else: ?>
                    <span class="btn btn--ghost btn--sm is-disabled">Siguiente →</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
