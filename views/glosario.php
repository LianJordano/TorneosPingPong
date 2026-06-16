<section class="page-head">
    <h1>Glosario y reglas</h1>
    <a class="btn btn--ghost btn--sm" href="/">Volver al panel</a>
</section>

<section class="card">
    <h2>Los dos niveles de marcador</h2>
    <p>En tenis de mesa el marcador tiene <strong>dos niveles</strong>, y conviene tenerlos claros porque la tabla usa los dos:</p>
    <dl class="glosario">
        <dt>Punto</dt><dd>Cada tanto dentro de un set. Un set se juega, por defecto, a 11 puntos con diferencia de 2.</dd>
        <dt>Set</dt><dd>Cada parcial del partido. El partido se gana al ganar la mayoría de los sets según el formato (mejor de 3 → 2 sets; mejor de 5 → 3 sets).</dd>
        <dt>Partido</dt><dd>El enfrentamiento completo entre dos jugadores.</dd>
    </dl>
</section>

<section class="card">
    <h2>Columnas de la tabla de posiciones</h2>
    <dl class="glosario">
        <dt>PJ</dt><dd><strong>Partidos jugados.</strong></dd>
        <dt>PG / PP</dt><dd><strong>Partidos ganados / perdidos.</strong></dd>
        <dt>Pts (puntos de clasificación)</dt><dd>Lo que define el ranking: <code>partidos ganados × puntos por victoria</code> (por defecto 1 por victoria).</dd>
        <dt>Sets (favor-contra)</dt><dd>Sets ganados y perdidos sumando <em>todos</em> los partidos.</dd>
        <dt>R.Sets (ratio de sets)</dt><dd><code>sets a favor ÷ sets en contra</code>. Cuánto más ganaste de lo que perdiste, a nivel sets.</dd>
        <dt>Puntos (favor-contra)</dt><dd>Puntos (de los 11) hechos y recibidos en total.</dd>
        <dt>R.Pts (ratio de puntos)</dt><dd><code>puntos a favor ÷ puntos en contra</code>. Lo mismo, pero a nivel punto a punto.</dd>
    </dl>
    <p class="muted small">Un ratio mayor a 1 significa que ganaste más de lo que perdiste; cuanto más alto, más dominante fuiste.</p>
</section>

<section class="card">
    <h2>Criterios de desempate (en orden)</h2>
    <p>Cuando dos o más jugadores empatan en <strong>puntos de clasificación</strong>, el sistema baja criterio por criterio hasta que uno los separe:</p>
    <ol class="desempates">
        <li>
            <strong>Head-to-head (resultado directo).</strong>
            ¿Quién le ganó a quién <em>cuando jugaron entre ellos</em>? Es el más justo y por eso va primero: es mano a mano, sin importar cómo les fue contra el resto.
        </li>
        <li>
            <strong>Ratio de sets (entre empatados).</strong>
            Si el directo no alcanza, se compara el ratio de sets, pero <em>solo</em> mirando los partidos entre los empatados.
        </li>
        <li>
            <strong>Ratio de puntos (entre empatados).</strong>
            Si todavía siguen iguales, se baja al nivel de los puntos, también solo entre los empatados.
        </li>
        <li>
            <strong>Ratios globales.</strong>
            Si persiste, se miran los ratios de sets y puntos contra todo el torneo.
        </li>
        <li>
            <strong>Sorteo.</strong>
            Último recurso, marcado manualmente por el administrador.
        </li>
    </ol>
    <div class="hint">
        <strong>¿Por qué "entre empatados"?</strong> Porque el empate se resuelve entre los protagonistas, con lo que pasó <em>entre ellos</em>. Sería injusto que alguien suba por haberle ganado holgado a un jugador que ni está en el empate. En empates de 3 o más, los criterios se recalculan como una mini-liga entre los implicados. La tabla muestra, en la columna <strong>Desempate</strong>, qué criterio definió cada posición.
    </div>
</section>

<section class="card">
    <h2>Formato del torneo</h2>
    <dl class="glosario">
        <dt>Liga (round-robin)</dt><dd>Todos contra todos una sola vez. Para K jugadores se generan K·(K-1)/2 partidos.</dd>
        <dt>Fixture</dt><dd>El calendario de partidos generado automáticamente al cerrar inscripciones.</dd>
        <dt>Bracket (eliminación directa)</dt><dd>Los N mejores de la tabla se cruzan en llaves: el que pierde queda afuera, hasta el campeón.</dd>
        <dt>Siembra estándar</dt><dd>Orden de los cruces para que el 1° y el 2° solo puedan enfrentarse en la final (1° vs último, 2° vs anteúltimo, etc.).</dd>
        <dt>Bye (descanso)</dt><dd>Cuando los clasificados no son potencia de 2 (4, 8, 16…), los mejores sembrados pasan de ronda sin jugar la primera.</dd>
    </dl>
</section>

<section class="card">
    <h2>Tipos de resultado</h2>
    <dl class="glosario">
        <dt>Normal</dt><dd>Se cargan los sets y el ganador se calcula solo.</dd>
        <dt>Walkover (W.O.)</dt><dd>Un jugador no se presenta; el otro gana sin jugar.</dd>
        <dt>Retiro / abandono</dt><dd>Un jugador abandona durante el partido; gana el otro (pueden quedar los sets ya jugados).</dd>
        <dt>Descalificación</dt><dd>Un jugador es descalificado; gana el rival.</dd>
        <dt>No presentado</dt><dd>El jugador no asistió al partido.</dd>
    </dl>
</section>

<section class="card">
    <h2>Estados del torneo</h2>
    <p class="muted">El torneo avanza por estados, y cada uno habilita distintas acciones:</p>
    <p>
        <span class="badge badge--gray">Borrador</span> →
        <span class="badge badge--green">Inscripción abierta</span> →
        <span class="badge badge--blue">Inscripción cerrada</span> →
        <span class="badge badge--blue">Fase de liga</span> →
        <span class="badge badge--purple">Fase eliminatoria</span> →
        <span class="badge badge--dark">Finalizado</span>
        <span class="badge badge--red">Cancelado</span>
    </p>
</section>
