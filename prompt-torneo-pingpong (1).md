# Prompt para Claude Code — Sistema de Gestión de Torneos de Tenis de Mesa (Ping Pong)

> **Cómo usar este prompt:** pégalo completo en Claude Code al iniciar el proyecto. Está escrito para que el agente entienda el dominio, no asuma de más y entregue por fases. Antes de generar código masivo, pídele que confirme el stack y el modelo de datos contigo.

---

## 1. Rol y objetivo

Actúa como un **ingeniero de software senior full-stack** especializado en aplicaciones de gestión deportiva. Vamos a construir, de cero, un **software profesional para administrar torneos internos de tenis de mesa (ping pong)** dentro de una empresa.

El sistema debe ser **production-ready**: código limpio, tipado, probado, documentado, con migraciones de base de datos versionadas y una interfaz clara tanto para administradores como para espectadores.

**Importante:** antes de escribir código, hazme un resumen del plan, confirma conmigo el stack tecnológico y el modelo de datos, y avísame de cualquier ambigüedad. No asumas reglas de negocio críticas sin preguntar.

---

## 2. Concepto del torneo

El formato es **liga única de todos contra todos (round-robin simple) seguida de eliminación directa (bracket)**. **No hay grupos ni divisiones:** hay una sola tabla con todos los inscritos.

1. **Fase de liga (round-robin):** **cada jugador inscrito se enfrenta una vez contra cada uno de los demás** jugadores del torneo. Cada partido otorga puntos de clasificación.
2. **Clasificación:** al terminar **todos** los partidos de la liga se genera una **única tabla de posiciones** ordenada por puntos acumulados y reglas de desempate (ver sección 5).
3. **Fase eliminatoria:** los **N mejores** de la tabla pasan a un **bracket de eliminación directa** hasta definir al campeón.
4. **Premiación:** según la configuración del torneo se reparte el premio entre 1, 2 o 3 posiciones (o las que se definan).

El sistema debe soportar **múltiples torneos** coexistiendo, cada uno con su propia configuración, jugadores y estado.

---

## 3. Requisitos funcionales

### 3.1 Gestión de torneos
- Crear, editar, duplicar, archivar y eliminar torneos.
- Cada torneo tiene un **ciclo de vida con estados**: `BORRADOR → INSCRIPCIÓN_ABIERTA → INSCRIPCIÓN_CERRADA → FASE_GRUPOS → FASE_ELIMINATORIA → FINALIZADO` (y `CANCELADO`). Las acciones permitidas dependen del estado (p. ej., no se pueden editar partidos de un torneo finalizado).
- Configuración por torneo:
  - Nombre, descripción, fechas de inicio/fin, lugar.
  - **Formato de sets por partido:** 1 set, mejor de 3, mejor de 5, mejor de 7 (configurable, con posibilidad de override por fase: la liga puede ser a 1 set y el bracket a mejor de 5).
  - **Puntos por set** (default 11, configurable) y **diferencia mínima para ganar** (default 2, configurable).
  - **Número de clasificados** que pasan de la liga al bracket (los N mejores de la tabla única).
  - **Puntos por partido ganado** para la tabla de la liga (default: 1 punto por victoria; configurable si se quisiera otro esquema).
  - **Cantidad de posiciones premiadas** (1, 2, 3 o más) y **distribución del premio** (monto total del pozo y porcentaje o monto por posición; validar que la suma cuadre).
  - **Cuota de inscripción** opcional (para calcular el pozo automáticamente).

### 3.2 Gestión de jugadores
- Registrar personas con: nombre, apellido, identificador interno/empleado, área o departamento, correo, teléfono, foto opcional, y nivel/categoría opcional.
- **Editar y dar de baja** jugadores.
- Un mismo jugador (persona) puede participar en varios torneos: separa la entidad **Persona** (datos maestros) de **Inscripción** (participación en un torneo concreto).
- **Inscribir / desinscribir** jugadores a un torneo mientras esté en estado de inscripción.
- **Sembrado (seeding) opcional:** el orden de siembra no es necesario para la liga (todos juegan contra todos igual), pero puede usarse para ordenar los cruces del bracket. En la práctica, la siembra del bracket sale de la posición final en la tabla de la liga.

### 3.3 Generación de calendario y partidos
- Al cerrar inscripciones, generar automáticamente el **fixture round-robin completo**: cada jugador contra cada uno de los demás **exactamente una vez** (sin enfrentamientos repetidos ni faltantes). Para K jugadores deben generarse K·(K-1)/2 partidos.
- Generar automáticamente el **bracket de eliminación directa** a partir de los **N mejores de la tabla**, manejando correctamente:
  - Cantidades que **no son potencia de 2** mediante **byes** (descansos) para los mejores sembrados.
  - Cruces estándar (1º vs último, etc.) para no enfrentar a los dos mejores en primera ronda.
- Soportar **asignación de mesa/cancha** y, opcionalmente, horario por partido.

### 3.4 Registro de marcadores
- Registrar el resultado **set por set** (no solo el global), respetando el formato configurado.
- Validar marcadores según reglas (puntos mínimos, diferencia, número máximo de sets).
- Calcular automáticamente el ganador del partido cuando se alcanza la mayoría de sets.
- Permitir **editar/corregir** un marcador ya cargado (con registro de quién y cuándo lo cambió → ver auditoría).
- Soportar resultados especiales: **walkover (W.O.), abandono/retiro, descalificación y no presentado**, reflejándolos correctamente en la tabla y el bracket.

### 3.5 Tabla de posiciones y avance
- **Tabla de posiciones única** (todos los inscritos) calculada en tiempo real: partidos jugados, ganados, perdidos, puntos de clasificación, sets a favor/en contra, ratio de sets, puntos a favor/en contra, ratio de puntos.
- Aplicar reglas de **desempate** (sección 5) de forma transparente y mostrar el criterio que rompió cada empate.
- Botón/acción para **promover** a los N mejores de la tabla al bracket cuando la liga termina (es decir, cuando **todos** los partidos de la liga están cargados).

### 3.6 Premiación y resultados finales
- Al finalizar, mostrar **podio** (1º, 2º, 3º…) y la **distribución del premio** según configuración.
- Generar un **resumen del torneo** exportable.

### 3.7 Vistas y exportación
- **Panel de administración** (gestión completa).
- **Vista pública / espectador** de solo lectura: bracket en vivo, tabla de posiciones y próximos partidos (ideal para proyectar en una pantalla en la oficina).
- **Exportar** tabla de posiciones, fixture y bracket a **PDF y CSV**. Bracket imprimible.

### 3.8 Roles y permisos
- **Administrador:** todo.
- **Anotador (scorekeeper):** registrar y editar marcadores.
- **Espectador:** solo lectura.
- Autenticación segura (hash de contraseñas, sesiones/JWT).

### 3.9 Auditoría
- **Bitácora de cambios** en marcadores y en datos sensibles del torneo (quién, qué, cuándo, valor anterior y nuevo).

---

## 4. Requisitos no funcionales
- **Idioma de la interfaz: español.**
- Diseño **responsive** (escritorio y móvil).
- Validación de datos en backend y frontend.
- Manejo de errores claro y mensajes útiles al usuario.
- Esquema de base de datos en **scripts SQL versionados** (`schema.sql`) y **datos de prueba (`seed.sql`)** para poder probar el flujo completo.
- Variables sensibles en `.env` (no hardcodear credenciales).
- Código tipado, formateado y con linter.

---

## 5. Reglas de dominio del tenis de mesa (¡críticas, respétalas!)

- Un **set** se gana al llegar a los puntos configurados (default **11**) con **diferencia de 2**; si hay empate al límite (10-10), se continúa hasta que un jugador saque 2 de ventaja.
- Un **partido** se gana al ganar la mayoría de los sets según el formato (mejor de 3 → 2 sets; mejor de 5 → 3 sets; etc.). En partidos a **1 set**, gana quien gane ese set.
- **Desempate en la tabla de la liga** (orden de prioridad, configurable pero con este default tipo ITTF):
  1. Mayor número de **puntos de clasificación** (partidos ganados).
  2. **Resultado directo (head-to-head)** entre los empatados.
  3. **Ratio de sets** (sets ganados / sets perdidos) entre los empatados.
  4. **Ratio de puntos** entre los empatados.
  5. Si persiste, ratio de sets/puntos global y, en último caso, sorteo manual marcado por el admin.
- Maneja correctamente empates de **3 o más** jugadores recalculando los criterios solo entre los implicados.

> Si alguna regla de desempate o de puntuación debe ajustarse a la normativa interna de la empresa, **pregúntame antes de fijarla**.

---

## 6. Modelo de datos (propuesta inicial — confírmalo conmigo)

Entidades principales:
- **Persona** (datos maestros del empleado/jugador).
- **Torneo** (configuración, estado, fechas, premio).
- **Inscripción** (relación Persona ↔ Torneo, con siembra opcional).
- **Partido** (torneo, fase [LIGA/ELIMINATORIA], ronda, jugador A, jugador B, mesa, horario, estado, ganador, tipo de resultado [NORMAL/WALKOVER/RETIRO/etc.]).
- **Set** (partido, número de set, puntos jugador A, puntos jugador B).
- **Configuración de premio** (torneo, posición, monto/porcentaje).
- **Usuario** (auth, rol).
- **RegistroAuditoría** (entidad, acción, usuario, fecha, antes/después).

Diseña el esquema con integridad referencial, índices adecuados y restricciones que impidan estados inválidos (p. ej., un partido no puede tener más sets que el máximo del formato).

---

## 7. Stack tecnológico (PHP puro)
- **Backend:** **PHP puro (vanilla PHP), sin framework** (nada de Laravel, Symfony, etc.). PHP 8.1 o superior.
- **Acceso a datos:** **PDO** con sentencias preparadas (consultas parametrizadas siempre, para evitar inyección SQL). Nada de `mysql_*` ni concatenar SQL con datos del usuario.
- **Base de datos:** **MySQL 8** (o MariaDB).
- **Migraciones/esquema:** scripts SQL versionados en una carpeta `database/` (`schema.sql` para crear tablas + `seed.sql` con datos de prueba). Numerar los scripts para poder aplicarlos en orden.
- **Frontend:** HTML + CSS + un poco de JavaScript vainilla. Las vistas se renderizan desde PHP (plantillas `.php` simples). Puede usarse una hoja de estilos propia o un CSS ligero; sin obligación de framework de JS.
- **Estructura del proyecto:** separar responsabilidades aunque sea PHP puro, por ejemplo:
  - `public/` → único punto de entrada web (`index.php` como front controller que enruta las peticiones). El resto del código **no** debe ser accesible directamente por el navegador.
  - `src/` → lógica de dominio (clases para Torneo, Partido, Liga, Bracket, Desempate, etc.).
  - `src/db/` → conexión PDO y repositorios (consultas).
  - `views/` → plantillas PHP.
  - `config/` → configuración leída desde variables de entorno / archivo `.env` (no credenciales hardcodeadas).
  - `database/` → SQL de esquema y seed.
  - `tests/` → pruebas.
- **Auth:** sesiones de PHP (`$_SESSION`) con contraseñas hasheadas con `password_hash()` / `password_verify()`. Protección CSRF en los formularios y escape de salida (`htmlspecialchars`) para evitar XSS.
- **Testing:** **PHPUnit** para la lógica de negocio (desempates, generación del fixture round-robin, generación del bracket con byes, validación de marcadores).
- **Dependencias:** **Composer** para autoload (PSR-4) y para PHPUnit. Mantenerlo al mínimo: el núcleo de la app es PHP puro.
- **Entorno local:** instrucciones para levantar con el **servidor embebido de PHP** (`php -S localhost:8000 -t public`) apuntando a una base MySQL local. Opcionalmente un `docker-compose.yml` con PHP + MySQL para quien lo prefiera.

No introduzcas un framework MVC completo: mantén el código PHP puro, ordenado y legible. Si propones alguna librería de Composer puntual (por ejemplo para generar PDF), justifícalo brevemente antes de añadirla.

---

## 8. Entrega por fases (no lo hagas todo de golpe)

Trabaja incrementalmente y, al terminar cada fase, muéstrame el avance y espera mi visto bueno:

1. **Fase 0 — Setup:** estructura de carpetas (`public/`, `src/`, `views/`, `config/`, `database/`, `tests/`), front controller `public/index.php`, conexión PDO, Composer con autoload PSR-4 y PHPUnit, `database/schema.sql` + `database/seed.sql`, y un `README` con cómo crear la base, configurar el `.env` y arrancar con `php -S localhost:8000 -t public`.
2. **Fase 1 — CRUD base:** torneos, personas, inscripciones, autenticación y roles.
3. **Fase 2 — Round-robin:** generación del fixture completo (todos contra todos), registro de marcadores set por set, validaciones y tabla de posiciones única con desempates.
4. **Fase 3 — Eliminatoria:** promoción de clasificados, generación de bracket con byes, registro de resultados y campeón.
5. **Fase 4 — Premiación, exportación y vista pública:** podio, distribución de premio, export PDF/CSV, vista de solo lectura/proyección.
6. **Fase 5 — Pulido:** auditoría, manejo de casos especiales (W.O./retiro), pruebas, documentación final.

---

## 9. Criterios de aceptación
- Puedo crear un torneo, inscribir jugadores, cerrar inscripciones y el sistema genera el fixture round-robin completo: cada jugador contra cada uno exactamente una vez (K·(K-1)/2 partidos), sin repetidos ni faltantes.
- Puedo cargar marcadores set por set; el sistema valida según el formato y declara ganador automáticamente.
- La tabla de posiciones se actualiza sola y los desempates se resuelven y se explican.
- Al cargarse **todos** los partidos de la liga, los N mejores de la tabla única pasan a un bracket correcto (incluyendo byes si no son potencia de 2).
- Al finalizar, se muestra el podio y la distribución del premio configurada.
- Existe vista pública de solo lectura y exportación a PDF/CSV.
- Hay pruebas que cubren la lógica de fixture, bracket, desempates y validación de marcadores, y todas pasan.

---

## 10. Instrucciones finales para el agente
- Pregunta antes de asumir reglas de negocio ambiguas.
- Entrega por fases, con commits claros y un README que explique cómo levantar y probar el sistema.
- Prioriza correctitud de la lógica deportiva (desempates, bracket, validaciones) por encima de adornos visuales.
- Mantén todo el texto de la interfaz en español.

Comienza confirmando el plan, el stack y el modelo de datos, y luego procede con la **Fase 0**.
