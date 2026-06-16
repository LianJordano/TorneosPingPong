<section class="page-head page-head--hero">
    <div class="hero-grid">
        <div>
            <span class="eyebrow">Rules & glossary</span>
            <h1>Glosario y reglas</h1>
            <p class="muted">Una guía visual más clara para entender el lenguaje del sistema, los criterios deportivos y la lógica competitiva.</p>
        </div>
        <div class="hero-side">
            <div class="actions">
                <a class="btn btn--ghost btn--sm" href="/">Volver al panel</a>
            </div>
        </div>
    </div>
    <div class="glossary-hero-stats">
        <div class="glossary-stat"><span>Conceptos clave</span><strong>6</strong><small>bloques ordenados para consulta rápida</small></div>
        <div class="glossary-stat"><span>Lectura</span><strong>Simple</strong><small>jerarquía visual para entender mejor</small></div>
        <div class="glossary-stat"><span>Uso</span><strong>Operativo</strong><small>pensado para admins, anotadores y soporte</small></div>
    </div>
</section>

<div class="glossary-shell">
    <div class="glossary-grid">
        <div class="glossary-stack">
            <section class="card glossary-card">
                <h2>Los dos niveles de marcador</h2>
                <p class="glossary-card__intro">En tenis de mesa el marcador tiene <strong>dos niveles</strong>, y conviene tenerlos claros porque la tabla usa ambos.</p>
                <div class="glossary-list">
                    <dl class="glossary-item"><dt>Punto</dt><dd>Cada tanto dentro de un set. Un set se juega, por defecto, a 11 puntos con diferencia de 2.</dd></dl>
                    <dl class="glossary-item"><dt>Set</dt><dd>Cada parcial del partido. El partido se gana al ganar la mayoría de los sets según el formato.</dd></dl>
                    <dl class="glossary-item"><dt>Partido</dt><dd>El enfrentamiento completo entre dos jugadores.</dd></dl>
                </div>
            </section>

            <section class="card glossary-card">
                <h2>Columnas de la tabla de posiciones</h2>
                <div class="glossary-list">
                    <dl class="glossary-item"><dt>PJ</dt><dd><strong>Partidos jugados.</strong></dd></dl>
                    <dl class="glossary-item"><dt>PG / PP</dt><dd><strong>Partidos ganados / perdidos.</strong></dd></dl>
                    <dl class="glossary-item"><dt>Pts</dt><dd>Define el ranking: <code>partidos ganados × puntos por victoria</code>.</dd></dl>
                    <dl class="glossary-item"><dt>Sets / R.Sets</dt><dd>Sets favor-contra y su ratio para medir dominio a nivel parcial.</dd></dl>
                    <dl class="glossary-item"><dt>Puntos / R.Pts</dt><dd>Puntos hechos/recibidos y su ratio para bajar al detalle fino del rendimiento.</dd></dl>
                </div>
                <p class="muted small">Un ratio mayor a 1 significa que ganaste más de lo que perdiste; cuanto más alto, más dominante fuiste.</p>
            </section>

            <section class="card glossary-card">
                <h2>Criterios de desempate</h2>
                <p class="glossary-card__intro">Cuando dos o más jugadores empatan en <strong>puntos de clasificación</strong>, el sistema baja criterio por criterio.</p>
                <ol class="glossary-steps">
                    <li><div><strong>Head-to-head.</strong> ¿Quién le ganó a quién cuando jugaron entre ellos? Va primero porque es el criterio más justo.</div></li>
                    <li><div><strong>Ratio de sets entre empatados.</strong> Si el directo no alcanza, se mira solo la mini-liga entre los implicados.</div></li>
                    <li><div><strong>Ratio de puntos entre empatados.</strong> Si todavía siguen iguales, se baja al nivel de los puntos.</div></li>
                    <li><div><strong>Ratios globales.</strong> Si persiste, se miran sets y puntos contra todo el torneo.</div></li>
                    <li><div><strong>Sorteo.</strong> Último recurso, resuelto manualmente por administración.</div></li>
                </ol>
                <div class="hint">
                    <strong>¿Por qué “entre empatados”?</strong> Porque el empate se resuelve entre los protagonistas. En empates de 3 o más, los criterios se recalculan como una mini-liga entre los implicados.
                </div>
            </section>
        </div>

        <aside class="glossary-sidebar">
            <section class="glossary-note">
                <h3>Formato del torneo</h3>
                <ul>
                    <li><strong>Liga:</strong> todos contra todos una sola vez.</li>
                    <li><strong>Fixture:</strong> calendario generado al cerrar inscripciones.</li>
                    <li><strong>Bracket:</strong> eliminación directa hasta definir campeón.</li>
                    <li><strong>Siembra:</strong> orden para proteger a los mejores sembrados.</li>
                    <li><strong>Bye:</strong> pase automático cuando no hay potencia de 2.</li>
                </ul>
            </section>

            <section class="glossary-note">
                <h3>Tipos de resultado</h3>
                <ul>
                    <li><strong>Normal:</strong> se cargan sets y el ganador se calcula solo.</li>
                    <li><strong>Walkover:</strong> un jugador no se presenta.</li>
                    <li><strong>Retiro:</strong> abandono durante el partido.</li>
                    <li><strong>Descalificación:</strong> gana el rival por sanción.</li>
                    <li><strong>No presentado:</strong> ausencia al partido.</li>
                </ul>
            </section>

            <section class="glossary-note">
                <h3>Estados del torneo</h3>
                <p class="muted">Cada estado habilita distintas acciones del flujo competitivo:</p>
                <div class="glossary-flow">
                    <span class="badge badge--gray">Borrador</span>
                    <span class="badge badge--green">Inscripción abierta</span>
                    <span class="badge badge--blue">Inscripción cerrada</span>
                    <span class="badge badge--blue">Fase de liga</span>
                    <span class="badge badge--purple">Fase eliminatoria</span>
                    <span class="badge badge--dark">Finalizado</span>
                    <span class="badge badge--red">Cancelado</span>
                </div>
            </section>
        </aside>
    </div>
</div>
