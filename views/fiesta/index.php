<?php
/** @var list<array<string,mixed>> $fiestas */
$puedeGestionar = $rol?->puedeGestionar() ?? false;
$estados = [
    'INSCRIPCION'  => ['Inscribiendo', 'badge--green'],
    'GRUPOS'       => ['Fase de grupos', 'badge--blue'],
    'ELIMINATORIA' => ['Eliminatoria', 'badge--purple'],
    'FINALIZADO'   => ['Finalizada', 'badge--dark'],
];
?>
<header class="page-head fiesta-hero">
    <div>
        <span class="eyebrow">Competencia</span>
        <h1>Torneo fiesta</h1>
        <p>Elegí a los jugadores registrados, girá la ruleta y dejá que el azar arme las parejas y los cruces uno por uno, en vivo y con suspenso.</p>
    </div>
    <?php if ($puedeGestionar): ?>
        <div class="actions">
            <a class="btn btn--primary btn--lg" href="/fiesta/nueva"><?= icon('plus') ?> Nueva fiesta</a>
        </div>
    <?php endif; ?>
</header>

<ol class="fiesta-how" aria-label="Cómo funciona">
    <li><span class="fiesta-how__num">1</span><span><strong>Elegí</strong> a los jugadores registrados que van a participar.</span></li>
    <li><span class="fiesta-how__num">2</span><span><strong>Girá la ruleta:</strong> salen las parejas y los cruces de a uno.</span></li>
    <li><span class="fiesta-how__num">3</span><span><strong>Jugá</strong> los grupos o la eliminación directa hasta el campeón.</span></li>
</ol>

<?php if ($fiestas === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('shuffle') ?></span>
        <p class="empty-state__title">Todavía no hay fiestas</p>
        <p>Armá la primera: en dos minutos tenés las parejas sorteadas y el cuadro listo para jugar.</p>
        <?php if ($puedeGestionar): ?>
            <div class="actions"><a class="btn btn--primary" href="/fiesta/nueva"><?= icon('plus') ?> Nueva fiesta</a></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="fiesta-grid">
        <?php foreach ($fiestas as $f): ?>
            <?php
            [$estadoLabel, $estadoBadge] = $estados[$f['estado']] ?? [$f['estado'], 'badge--gray'];
            $dobles = $f['modalidad'] === 'DOBLES';
            $url = '/fiesta/' . (string) $f['id'];
            ?>
            <a class="card card--interactive fiesta-card" href="<?= e($url) ?>">
                <span class="fiesta-card__icon" aria-hidden="true"><?= icon($dobles ? 'users' : 'user') ?></span>
                <span class="fiesta-card__body">
                    <span class="fiesta-card__title"><?= e((string) $f['nombre']) ?></span>
                    <span class="fiesta-card__meta">
                        <?= $dobles ? 'Dobles' : 'Individual' ?> ·
                        <?= $f['formato'] === 'GRUPOS' ? 'Grupos + eliminatoria' : 'Eliminación directa' ?> ·
                        <?= e((string) $f['participantes']) ?> participantes
                    </span>
                </span>
                <span class="badge badge--dot <?= e($estadoBadge) ?>"><?= e($estadoLabel) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
