<?php

use PingPong\Domain\EstadoTorneo;

$descripcionEstados = [
    'BORRADOR'            => 'El torneo se está configurando y todavía no acepta inscripciones.',
    'INSCRIPCION_ABIERTA' => 'Se pueden inscribir jugadores o parejas.',
    'INSCRIPCION_CERRADA' => 'Ya no entran inscritos; se puede reabrir o generar el fixture para pasar a la liga.',
    'FASE_LIGA'           => 'Se juega el todos contra todos y se carga cada resultado.',
    'FASE_ELIMINATORIA'   => 'Se juega el bracket de eliminación directa hasta definir campeón.',
    'FINALIZADO'          => 'El torneo terminó: quedan el podio, la tabla y los premios.',
    'CANCELADO'           => 'El torneo se suspendió y no admite más cambios.',
];

$secciones = [
    'marcador'   => ['Los dos niveles de marcador', 'scoreboard'],
    'tabla'      => ['Columnas de la tabla', 'table'],
    'desempate'  => ['Criterios de desempate', 'sort'],
    'formato'    => ['Formato del torneo', 'bracket'],
    'resultados' => ['Tipos de resultado', 'flag'],
    'estados'    => ['Estados del torneo', 'layers'],
];
?>
<header class="page-head">
    <div>
        <span class="eyebrow">Ayuda</span>
        <h1>Glosario y reglas</h1>
        <p>Qué significa cada término del sistema y cómo se ordena la tabla de posiciones.</p>
    </div>
</header>

<div class="glossary">
    <nav class="glossary__toc card card--muted" aria-labelledby="glosario-indice">
        <h2 class="glossary__toc-title" id="glosario-indice">En esta página</h2>
        <ol>
            <?php foreach ($secciones as $ancla => [$tituloSeccion, $iconoSeccion]): ?>
                <li><a href="#<?= e($ancla) ?>"><?= icon($iconoSeccion, 'icon--sm') ?> <?= e($tituloSeccion) ?></a></li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div class="glossary__content">
        <section class="card glossary-section" id="marcador" aria-labelledby="marcador-titulo">
            <h2 id="marcador-titulo"><?= icon('scoreboard') ?> Los dos niveles de marcador</h2>
            <p>En tenis de mesa el marcador tiene <strong>dos niveles</strong>, y conviene tenerlos claros porque la tabla usa ambos.</p>
            <dl class="glossary-terms">
                <div><dt>Punto</dt><dd>Cada tanto dentro de un set. Por defecto, un set se juega a 11 puntos con diferencia de 2.</dd></div>
                <div><dt>Set</dt><dd>Cada parcial del partido. El partido se gana al ganar la mayoría de los sets según el formato.</dd></div>
                <div><dt>Partido</dt><dd>El enfrentamiento completo entre dos jugadores o parejas.</dd></div>
            </dl>
        </section>

        <section class="card glossary-section" id="tabla" aria-labelledby="tabla-titulo">
            <h2 id="tabla-titulo"><?= icon('table') ?> Columnas de la tabla</h2>
            <dl class="glossary-terms">
                <div><dt>PJ</dt><dd>Partidos jugados.</dd></div>
                <div><dt>PG / PP</dt><dd>Partidos ganados / perdidos.</dd></div>
                <div><dt>Pts</dt><dd>Define la posición: <code>partidos ganados × puntos por victoria</code>.</dd></div>
                <div><dt>Sets / R.Sets</dt><dd>Sets a favor y en contra, y su ratio, para medir el dominio por parcial.</dd></div>
                <div><dt>Puntos / R.Pts</dt><dd>Puntos hechos y recibidos, y su ratio, para el detalle fino del rendimiento.</dd></div>
            </dl>
            <p class="hint mb-0"><?= icon('info') ?> <span>Un ratio mayor a 1 significa que ganaste más de lo que perdiste; cuanto más alto, más dominante fuiste.</span></p>
        </section>

        <section class="card glossary-section" id="desempate" aria-labelledby="desempate-titulo">
            <h2 id="desempate-titulo"><?= icon('sort') ?> Criterios de desempate</h2>
            <p>Cuando dos o más jugadores empatan en <strong>puntos de clasificación</strong>, el sistema aplica estos criterios en orden:</p>
            <ol class="glossary-steps">
                <li><strong>Enfrentamiento directo.</strong> ¿Quién le ganó a quién cuando jugaron entre ellos? Va primero porque es el criterio más justo.</li>
                <li><strong>Ratio de sets entre empatados.</strong> Si el directo no alcanza, se mira solo la mini-liga entre los implicados.</li>
                <li><strong>Ratio de puntos entre empatados.</strong> Si todavía siguen iguales, se baja al nivel de los puntos.</li>
                <li><strong>Ratios globales.</strong> Si persiste, se miran sets y puntos contra todo el torneo.</li>
                <li><strong>Sorteo.</strong> Último recurso, resuelto manualmente por la administración.</li>
            </ol>
            <div class="hint mb-0">
                <?= icon('help') ?>
                <span><strong>¿Por qué «entre empatados»?</strong> Porque el empate se resuelve entre los protagonistas. En empates de 3 o más, los criterios se recalculan como una mini-liga entre los implicados.</span>
            </div>
        </section>

        <section class="card glossary-section" id="formato" aria-labelledby="formato-titulo">
            <h2 id="formato-titulo"><?= icon('bracket') ?> Formato del torneo</h2>
            <dl class="glossary-terms">
                <div><dt>Liga</dt><dd>Todos contra todos, una sola vez.</dd></div>
                <div><dt>Fixture</dt><dd>Calendario de partidos que se genera al cerrar las inscripciones.</dd></div>
                <div><dt>Bracket</dt><dd>Eliminación directa hasta definir al campeón.</dd></div>
                <div><dt>Siembra</dt><dd>Orden del cuadro que evita que los mejores clasificados se crucen temprano.</dd></div>
                <div><dt>Bye</dt><dd>Pase automático a la siguiente ronda cuando la cantidad de jugadores no es potencia de 2.</dd></div>
            </dl>
        </section>

        <section class="card glossary-section" id="resultados" aria-labelledby="resultados-titulo">
            <h2 id="resultados-titulo"><?= icon('flag') ?> Tipos de resultado</h2>
            <dl class="glossary-terms">
                <div><dt>Normal</dt><dd>Se cargan los sets y el ganador se calcula solo.</dd></div>
                <div><dt>Walkover</dt><dd>Un jugador no se presenta y el rival gana sin jugar.</dd></div>
                <div><dt>Retiro</dt><dd>Abandono durante el partido.</dd></div>
                <div><dt>Descalificación</dt><dd>Gana el rival por sanción.</dd></div>
                <div><dt>No presentado</dt><dd>Ausencia al partido.</dd></div>
            </dl>
        </section>

        <section class="card glossary-section" id="estados" aria-labelledby="estados-titulo">
            <h2 id="estados-titulo"><?= icon('layers') ?> Estados del torneo</h2>
            <p>Cada estado habilita distintas acciones. Así se ven en los listados, en el orden en que avanza un torneo:</p>
            <dl class="glossary-states">
                <?php foreach (EstadoTorneo::cases() as $estado): ?>
                    <div>
                        <dt><span class="badge badge--dot <?= e($estado->badge()) ?>"><?= e($estado->label()) ?></span></dt>
                        <dd><?= e($descripcionEstados[$estado->value] ?? '') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    </div>
</div>
