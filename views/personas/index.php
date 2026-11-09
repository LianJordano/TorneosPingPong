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
/** @var list<int> $personasConUsuario */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$conUsuario = array_flip($personasConUsuario ?? []);
$hayFiltros = $q !== '' || $incluirInactivas;

/** Base URL params (without page), keeping filters and sort. */
$baseParams = static function () use ($q, $incluirInactivas, $orden, $dir): array {
    $p = [];
    if ($q !== '') { $p['q'] = $q; }
    if ($incluirInactivas) { $p['inactivas'] = 1; }
    $p['orden'] = $orden;
    $p['dir'] = $dir;
    return $p;
};
$urlPagina = static fn (int $n): string => '/personas?' . http_build_query($baseParams() + ['pagina' => $n]);

/** Current view URL: bulk/single conversion returns here (keeps filters and page). */
$urlActual = $urlPagina($pagina);

/** Sortable header: toggles direction when it is already the active column. */
$th = static function (string $col, string $etiqueta) use ($q, $incluirInactivas, $orden, $dir): string {
    $activa = $orden === $col;
    $nuevaDir = ($activa && $dir === 'asc') ? 'desc' : 'asc';
    $params = [];
    if ($q !== '') { $params['q'] = $q; }
    if ($incluirInactivas) { $params['inactivas'] = 1; }
    $params['orden'] = $col;
    $params['dir'] = $nuevaDir;
    $ariaSort = $activa ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    $icono = $activa ? ($dir === 'asc' ? 'chevron-up' : 'chevron-down') : 'sort';
    $accion = $nuevaDir === 'asc' ? 'ordenar ascendente' : 'ordenar descendente';
    return '<th scope="col" aria-sort="' . $ariaSort . '"' . ($activa ? ' class="is-sorted"' : '') . '>'
        . '<a class="sort-link" href="/personas?' . e(http_build_query($params)) . '">'
        . '<span>' . e($etiqueta) . '</span><span class="sr-only">, ' . e($accion) . '</span>'
        . icon($icono) . '</a></th>';
};

$etiquetasOrden = [
    'nombre' => 'jugador',
    'area' => 'área',
    'nivel' => 'nivel',
    'identificador' => 'identificador',
    'estado' => 'estado',
];

/** Compact page window for the pagination. */
$ventana = 2;
$pagInicio = max(1, $pagina - $ventana);
$pagFin = min($totalPaginas, $pagina + $ventana);
?>
<header class="page-head">
    <div>
        <span class="eyebrow"><?= icon('users') ?> Competencia</span>
        <h1>Jugadores</h1>
        <p>Busca, filtra y gestiona la base de jugadores. Abre un jugador para ver su perfil y estadísticas.</p>
    </div>
    <?php if ($puedeGestionar): ?>
        <div class="actions">
            <a class="btn btn--primary" href="/personas/nueva"><?= icon('user-plus') ?> Nuevo jugador</a>
        </div>
    <?php endif; ?>
</header>

<section class="card players-card" aria-labelledby="players-list-title">
    <h2 id="players-list-title" class="sr-only">Listado de jugadores</h2>

    <form method="get" action="/personas" class="toolbar players-toolbar" role="search">
        <div class="search toolbar__grow">
            <label class="sr-only" for="personas-q">Buscar jugadores</label>
            <?= icon('search') ?>
            <input type="search" id="personas-q" name="q" value="<?= e($q) ?>"
                   placeholder="Nombre, apellido, área, nivel, identificador o correo">
        </div>
        <label class="check">
            <input type="checkbox" name="inactivas" value="1" <?= $incluirInactivas ? 'checked' : '' ?>>
            Incluir inactivos
        </label>
        <div class="actions">
            <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Filtrar</button>
            <?php if ($hayFiltros): ?>
                <a class="btn btn--ghost" href="/personas"><?= icon('x') ?> Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($total === 0): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon($q !== '' ? 'search' : 'users') ?></span>
            <?php if ($q !== ''): ?>
                <p class="empty-state__title">Sin resultados</p>
                <p>No hay jugadores que coincidan con «<?= e($q) ?>».</p>
                <div class="actions"><a class="btn btn--secondary" href="/personas">Ver todos los jugadores</a></div>
            <?php else: ?>
                <p class="empty-state__title">Todavía no hay jugadores</p>
                <p>Cuando se carguen jugadores aparecerán aquí.</p>
                <?php if ($puedeGestionar): ?>
                    <div class="actions"><a class="btn btn--primary" href="/personas/nueva"><?= icon('user-plus') ?> Nuevo jugador</a></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="players-summary muted small">
            Mostrando <strong class="num"><?= e((string) $desde) ?>–<?= e((string) $hasta) ?></strong>
            de <strong class="num"><?= e((string) $total) ?></strong> jugador<?= $total === 1 ? '' : 'es' ?><?= $incluirInactivas ? ' (incluye inactivos)' : '' ?>.
        </p>

        <?php if ($puedeGestionar): ?>
            <?php /* Bulk form OUTSIDE the table: row checkboxes reference it via HTML5 form="". */ ?>
            <form id="form-lote" method="post" action="/personas/convertir-usuarios" class="bulk-bar"
                  data-confirm="Los jugadores seleccionados podrán entrar como espectadores. La contraseña inicial será Jugador123."
                  data-confirm-title="¿Convertir en usuarios?"
                  data-confirm-ok="Convertir"
                  data-confirm-tone="info">
                <?= csrf_field() ?>
                <input type="hidden" name="volver" value="<?= e($urlActual) ?>">
                <span class="bulk-bar__icon" aria-hidden="true"><?= icon('check-circle') ?></span>
                <div class="bulk-bar__text">
                    <span class="bulk-bar__count" data-bulk-count aria-live="polite">Selecciona jugadores en la tabla</span>
                    <span class="muted xsmall">Solo jugadores sin usuario. Se crea acceso de espectador con contraseña inicial <code>Jugador123</code>.</span>
                </div>
                <button type="submit" class="btn btn--primary" data-bulk-submit>
                    <?= icon('user-plus') ?> Convertir en usuarios
                </button>
            </form>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="table table--stack players-table">
                <caption class="sr-only">Jugadores ordenados por <?= e($etiquetasOrden[$orden] ?? $orden) ?>, <?= $dir === 'asc' ? 'ascendente' : 'descendente' ?></caption>
                <thead>
                    <tr>
                        <?php if ($puedeGestionar): ?>
                            <th scope="col" class="col-check">
                                <input type="checkbox" id="check-todos" aria-label="Seleccionar todos los jugadores de esta página">
                            </th>
                        <?php endif; ?>
                        <?= $th('nombre', 'Jugador') ?>
                        <?= $th('area', 'Área') ?>
                        <?= $th('nivel', 'Nivel') ?>
                        <?= $th('identificador', 'Identificador') ?>
                        <th scope="col">Usuario</th>
                        <?= $th('estado', 'Estado') ?>
                        <?php if ($puedeGestionar): ?>
                            <th scope="col" class="text-right">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($personas as $p): ?>
                    <?php
                    $id = (string) $p['id'];
                    $esUsuario = isset($conUsuario[(int) $p['id']]);
                    $activo = (int) $p['activo'] === 1;
                    $nombreCompleto = $p['apellido'] . ', ' . $p['nombre'];
                    ?>
                    <tr class="<?= $activo ? '' : 'row--muted' ?>">
                        <?php if ($puedeGestionar): ?>
                            <td class="col-check"<?= $esUsuario ? '' : ' data-label="Seleccionar"' ?>><?php if (!$esUsuario): ?><input type="checkbox" class="check-jugador" name="persona_ids[]" value="<?= e($id) ?>" form="form-lote" aria-label="Seleccionar a <?= e($nombreCompleto) ?>"><?php endif; ?></td>
                        <?php endif; ?>
                        <td class="cell--primary">
                            <a class="player-cell" href="/personas/<?= e($id) ?>">
                                <span class="avatar" aria-hidden="true"><?= e(initials($p['nombre'] . ' ' . $p['apellido'])) ?></span>
                                <span class="player-cell__name"><?= e($p['apellido']) ?>, <?= e($p['nombre']) ?></span>
                            </a>
                        </td>
                        <td data-label="Área"><?php if (!empty($p['area'])): ?><?= e($p['area']) ?><?php else: ?><span class="muted">Sin área</span><?php endif; ?></td>
                        <td data-label="Nivel"><?php if (!empty($p['nivel'])): ?><?= e($p['nivel']) ?><?php else: ?><span class="muted">Sin nivel</span><?php endif; ?></td>
                        <td data-label="Identificador"><?php if (!empty($p['identificador_interno'])): ?><span class="num"><?= e($p['identificador_interno']) ?></span><?php else: ?><span class="muted" aria-hidden="true">—</span><span class="sr-only">Sin identificador</span><?php endif; ?></td>
                        <td data-label="Usuario">
                            <?php if ($esUsuario): ?>
                                <span class="badge badge--blue"><?= icon('check') ?> Usuario</span>
                            <?php else: ?>
                                <span class="badge badge--gray">Sin usuario</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <?php if ($activo): ?>
                                <span class="badge badge--green badge--dot">Activo</span>
                            <?php else: ?>
                                <span class="badge badge--red badge--dot">Baja</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="actions-cell">
                                <div class="actions">
                                    <a class="btn btn--ghost btn--icon" href="/personas/<?= e($id) ?>/editar"
                                       title="Editar" aria-label="Editar a <?= e($nombreCompleto) ?>"><?= icon('edit') ?></a>
                                    <?php if (!$esUsuario): ?>
                                        <form method="post" action="/personas/<?= e($id) ?>/convertir-usuario" class="inline"
                                              data-confirm="Se creará un usuario espectador para <?= e($nombreCompleto) ?>. Contraseña inicial: Jugador123."
                                              data-confirm-title="¿Crear usuario?"
                                              data-confirm-ok="Crear usuario"
                                              data-confirm-tone="info">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="volver" value="<?= e($urlActual) ?>">
                                            <button class="btn btn--ghost btn--icon" type="submit"
                                                    title="Hacer usuario" aria-label="Crear usuario para <?= e($nombreCompleto) ?>"><?= icon('user-plus') ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($activo): ?>
                                        <form method="post" action="/personas/<?= e($id) ?>/desactivar" class="inline"
                                              data-confirm="<?= e($nombreCompleto) ?> dejará de estar activo. Podrás reactivarlo después."
                                              data-confirm-title="¿Dar de baja al jugador?"
                                              data-confirm-ok="Dar de baja">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--danger-soft btn--icon" type="submit"
                                                    title="Dar de baja" aria-label="Dar de baja a <?= e($nombreCompleto) ?>"><?= icon('archive') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="/personas/<?= e($id) ?>/activar" class="inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--secondary" type="submit"
                                                    aria-label="Reactivar a <?= e($nombreCompleto) ?>"><?= icon('refresh') ?> Reactivar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="pagination" aria-label="Paginación de jugadores">
                <span>Página <strong class="num"><?= e((string) $pagina) ?></strong> de <strong class="num"><?= e((string) $totalPaginas) ?></strong></span>
                <div class="pagination__pages">
                    <?php if ($pagina > 1): ?>
                        <a class="pagination__page" href="<?= e($urlPagina($pagina - 1)) ?>" aria-label="Página anterior" rel="prev"><?= icon('chevron-left') ?></a>
                    <?php else: ?>
                        <span class="pagination__page is-disabled" aria-hidden="true"><?= icon('chevron-left') ?></span>
                    <?php endif; ?>

                    <?php if ($pagInicio > 1): ?>
                        <a class="pagination__page" href="<?= e($urlPagina(1)) ?>" aria-label="Página 1">1</a>
                        <?php if ($pagInicio > 2): ?><span class="pagination__page is-disabled" aria-hidden="true">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($n = $pagInicio; $n <= $pagFin; $n++): ?>
                        <?php if ($n === $pagina): ?>
                            <span class="pagination__page" aria-current="page"><?= e((string) $n) ?></span>
                        <?php else: ?>
                            <a class="pagination__page" href="<?= e($urlPagina($n)) ?>" aria-label="Página <?= e((string) $n) ?>"><?= e((string) $n) ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagFin < $totalPaginas): ?>
                        <?php if ($pagFin < $totalPaginas - 1): ?><span class="pagination__page is-disabled" aria-hidden="true">…</span><?php endif; ?>
                        <a class="pagination__page" href="<?= e($urlPagina($totalPaginas)) ?>" aria-label="Página <?= e((string) $totalPaginas) ?>"><?= e((string) $totalPaginas) ?></a>
                    <?php endif; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a class="pagination__page" href="<?= e($urlPagina($pagina + 1)) ?>" aria-label="Página siguiente" rel="next"><?= icon('chevron-right') ?></a>
                    <?php else: ?>
                        <span class="pagination__page is-disabled" aria-hidden="true"><?= icon('chevron-right') ?></span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($puedeGestionar): ?>
<script>
    // Select all: checks/unchecks only the visible players that are not users yet,
    // and keeps the bulk bar (count + submit state) in sync with the selection.
    (function () {
        var maestro = document.getElementById('check-todos');
        var lote = document.getElementById('form-lote');
        if (!maestro || !lote) { return; }
        var contador = lote.querySelector('[data-bulk-count]');
        var enviar = lote.querySelector('[data-bulk-submit]');
        var checks = function () { return document.querySelectorAll('.check-jugador'); };

        var sync = function () {
            var total = checks().length;
            var marcados = document.querySelectorAll('.check-jugador:checked').length;
            maestro.checked = total > 0 && marcados === total;
            maestro.indeterminate = marcados > 0 && marcados < total;
            maestro.disabled = total === 0;
            lote.classList.toggle('is-active', marcados > 0);
            if (enviar) { enviar.disabled = marcados === 0; }
            if (contador) {
                contador.textContent = marcados === 0
                    ? 'Selecciona jugadores en la tabla'
                    : marcados + (marcados === 1 ? ' jugador seleccionado' : ' jugadores seleccionados');
            }
        };

        maestro.addEventListener('change', function () {
            checks().forEach(function (cb) { cb.checked = maestro.checked; });
            sync();
        });
        checks().forEach(function (cb) { cb.addEventListener('change', sync); });
        sync();
    })();
</script>
<?php endif; ?>
