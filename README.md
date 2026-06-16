# 🏓 Sistema de Gestión de Torneos de Tenis de Mesa (Ping Pong)

Software para administrar torneos internos de ping pong en una empresa: **liga round-robin
(todos contra todos) + eliminación directa (bracket)**. Construido en **PHP puro** (sin
framework), con **PDO**, **MySQL/MariaDB** y **PHPUnit**.

> **Estado actual:** ✅ Completo — las 6 fases (0 a 5) implementadas, probadas y verificadas de punta a punta.

---

## Requisitos

- PHP **8.1+** (probado con 8.3) con extensión `pdo_mysql`.
- **MariaDB 10.3+** o **MySQL 8** (en Laragon viene MariaDB).
- **Composer** 2.x.

---

## Puesta en marcha (local)

### 1. Instalar dependencias

```bash
composer install
```

### 2. Configurar el entorno

Copiá `.env.example` a `.env` y ajustá las credenciales si hace falta.
Los valores por defecto apuntan a la base local de Laragon:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pingpong
DB_USER=root
DB_PASS=
```

### 3. Crear la base de datos y cargar el esquema

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS pingpong CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root pingpong < database/schema.sql
mysql -u root pingpong < database/seed.sql
```

> En Laragon, `mysql` está en `C:\laragon\bin\mysql\...\bin`. Si no está en el PATH,
> usá la terminal de Laragon (Menú → Terminal).

### 4. Levantar el servidor

```bash
php -S localhost:8000 -t public public/index.php
```

Abrí **http://localhost:8000** — deberías ver la página de estado con la
conexión a la base en verde.

> El parámetro `public/index.php` actúa como *router script*: sirve los archivos
> estáticos reales (CSS) y enruta el resto al front controller. Es la pieza que
> hace funcionar las rutas amigables (`/torneos`, etc.) en fases siguientes.

---

## Pruebas

```bash
composer test
# o directamente:
./vendor/bin/phpunit
```

---

## Usuarios de prueba (seed)

Contraseña en todos los casos: **`admin123`**

| Email                       | Rol         |
|-----------------------------|-------------|
| `admin@pingpong.local`      | ADMIN       |
| `anotador@pingpong.local`   | ANOTADOR    |
| `espectador@pingpong.local` | ESPECTADOR  |

---

## Estructura del proyecto

```
public/            Único punto de entrada web (front controller + assets)
  index.php        Router script / front controller
  assets/css/      Hojas de estilo
src/               Lógica de dominio y soporte (PSR-4: PingPong\)
  Database/        Conexión PDO y, más adelante, repositorios
  Http/            Router
  Support/         Env, View, Csrf, helpers
config/            bootstrap.php (carga .env, autoload, errores)
views/             Plantillas PHP (layout + vistas)
database/          schema.sql (esquema versionado) + seed.sql (datos de prueba)
tests/             PHPUnit
```

El código fuera de `public/` **no** es accesible directamente por el navegador.

---

## Modelo de datos (resumen)

| Entidad              | Rol                                                                 |
|----------------------|---------------------------------------------------------------------|
| `usuario`            | Autenticación y roles (ADMIN / ANOTADOR / ESPECTADOR).              |
| `persona`            | Datos maestros del jugador (independiente del torneo).             |
| `torneo`             | Configuración, ciclo de vida (estados) y parámetros de premio.     |
| `inscripcion`        | Participación de una persona en un torneo (con siembra opcional).  |
| `partido`            | Enfrentamiento de liga o eliminatoria (referencia a inscripciones).|
| `partido_set`        | Marcador set por set de un partido.                                |
| `premio_config`      | Distribución del premio por posición.                              |
| `registro_auditoria` | Bitácora de cambios sensibles (quién / qué / cuándo / antes-después).|

**Ciclo de vida del torneo:**
`BORRADOR → INSCRIPCION_ABIERTA → INSCRIPCION_CERRADA → FASE_LIGA → FASE_ELIMINATORIA → FINALIZADO`
(y `CANCELADO`).

> Nota: el estado se llama **`FASE_LIGA`** (no `FASE_GRUPOS`), porque el formato es
> liga round-robin **sin grupos**.

---

## Plan de fases

- [x] **Fase 0 — Setup:** estructura, front controller, PDO, Composer + PHPUnit, esquema + seed, README.
- [x] **Fase 1 — CRUD base:** login/logout con sesiones + CSRF, roles (ADMIN/ANOTADOR/ESPECTADOR), CRUD de personas (con baja lógica), CRUD de torneos (con máquina de estados, duplicar y archivar) e inscripciones.
- [x] **Fase 2 — Round-robin:** generación del fixture (algoritmo del círculo, K·(K-1)/2 partidos), carga de marcadores set por set con validación según formato y ganador automático, y tabla de posiciones única con desempates (head-to-head → ratio de sets → ratio de puntos, recalculados entre empatados).
- [x] **Fase 3 — Eliminatoria:** promoción de los N mejores, bracket con siembra estándar y byes, avance automático de ganadores, formato de sets propio del bracket, y campeón con cierre del torneo.
- [x] **Fase 4 — Premiación, exportación y vista pública:** podio con distribución del premio (porcentajes validados a 100%, pozo = total fijado o cuota × inscritos), vista pública de solo lectura sin login (tabla + bracket + próximos, autorefresco), exportación a CSV (tabla y fixture) y a PDF vía impresión del navegador.
- [x] **Fase 5 — Pulido:** auditoría (bitácora de quién/qué/cuándo + antes/después en marcadores y cambios de estado), casos especiales (walkover, retiro, descalificación, no presentado) reflejados en tabla y bracket, y documentación final.

---

## Funcionalidades y rutas principales

| Ruta | Método | Descripción |
|------|--------|-------------|
| `/login`, `/logout` | GET/POST | Autenticación por sesión |
| `/` | GET | Panel (dashboard) |
| `/personas`, `/personas/{id}/editar` | GET/POST | CRUD de jugadores (baja lógica) |
| `/torneos`, `/torneos/{id}` | GET | Listado y detalle de torneos |
| `/torneos/{id}/estado` | POST | Transición de estado (máquina de estados) |
| `/torneos/{id}/inscripciones` | POST | Inscribir / desinscribir |
| `/torneos/{id}/fixture` | POST | Generar fixture round-robin |
| `/torneos/{id}/partidos` | GET | Partidos de liga por jornada |
| `/torneos/{id}/partidos/{pid}` | GET/POST | Cargar marcador (set a set o resultado especial) |
| `/torneos/{id}/tabla` | GET | Tabla de posiciones con desempates |
| `/torneos/{id}/bracket` | GET/POST | Promoción y cuadro de eliminación |
| `/torneos/{id}/finalizar` | POST | Cerrar torneo (campeón) |
| `/torneos/{id}/premios`, `/podio` | GET/POST | Distribución del premio y podio |
| `/torneos/{id}/auditoria` | GET | Bitácora de cambios (solo admin) |
| `/torneos/{id}/tabla.csv`, `/partidos.csv` | GET | Exportación CSV |
| `/publico/torneos/{id}` | GET | **Vista pública** (sin login, proyección) |

> **PDF:** desde la tabla, el bracket o el podio, el botón **🖨️ PDF** usa la impresión del
> navegador con una hoja de estilos `@media print` (cero dependencias).

## Pruebas

La lógica deportiva crítica está cubierta por **PHPUnit** (44 tests):
fixture round-robin, validación de marcadores (incluido deuce y formato a 1 set),
desempates de la tabla (head-to-head, ratios, triple empate), bracket con byes,
cálculo del podio y tipos de resultado especiales.

```bash
composer test
```
