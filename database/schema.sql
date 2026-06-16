-- =====================================================================
--  Sistema de Gestión de Torneos de Tenis de Mesa (Ping Pong)
--  schema.sql — Esquema versionado (v1 · Fase 0)
--  Motor objetivo: MariaDB 10.3+ / MySQL 8 · InnoDB · utf8mb4
-- =====================================================================
--  Ejecutar:  mysql -u root pingpong < database/schema.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS registro_auditoria;
DROP TABLE IF EXISTS premio_config;
DROP TABLE IF EXISTS partido_set;
DROP TABLE IF EXISTS partido;
DROP TABLE IF EXISTS inscripcion;
DROP TABLE IF EXISTS torneo;
DROP TABLE IF EXISTS persona;
DROP TABLE IF EXISTS area;
DROP TABLE IF EXISTS nivel;
DROP TABLE IF EXISTS usuario;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  usuario — autenticación y roles del sistema
-- ---------------------------------------------------------------------
CREATE TABLE usuario (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(120)  NOT NULL,
    email           VARCHAR(150)  NOT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    rol             ENUM('ADMIN','ANOTADOR','ESPECTADOR') NOT NULL DEFAULT 'ESPECTADOR',
    activo          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  area — catálogo de áreas / departamentos
-- ---------------------------------------------------------------------
CREATE TABLE area (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_area_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  nivel — catálogo de niveles / categorías
-- ---------------------------------------------------------------------
CREATE TABLE nivel (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(50)  NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nivel_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  persona — datos maestros del jugador (independiente del torneo)
--  area y nivel referencian sus catálogos (ON DELETE SET NULL: borrar un
--  área/nivel no borra al jugador, solo lo deja sin asignar).
-- ---------------------------------------------------------------------
CREATE TABLE persona (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre                 VARCHAR(80)  NOT NULL,
    apellido               VARCHAR(80)  NOT NULL,
    identificador_interno  VARCHAR(50)  NULL,            -- legajo / nro de empleado
    area_id                INT UNSIGNED NULL,
    nivel_id               INT UNSIGNED NULL,
    email                  VARCHAR(150) NULL,
    telefono               VARCHAR(40)  NULL,
    foto_path              VARCHAR(255) NULL,
    activo                 TINYINT(1)   NOT NULL DEFAULT 1,  -- baja lógica
    created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_persona_identificador (identificador_interno),
    KEY idx_persona_area (area_id),
    KEY idx_persona_nivel (nivel_id),
    CONSTRAINT fk_persona_area  FOREIGN KEY (area_id)  REFERENCES area(id)  ON DELETE SET NULL,
    CONSTRAINT fk_persona_nivel FOREIGN KEY (nivel_id) REFERENCES nivel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  torneo — configuración, ciclo de vida y parámetros de premio
--  NOTA: el estado FASE_LIGA reemplaza al FASE_GRUPOS del doc original,
--        porque el formato es liga round-robin sin grupos.
-- ---------------------------------------------------------------------
CREATE TABLE torneo (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre                 VARCHAR(150) NOT NULL,
    descripcion            TEXT NULL,
    lugar                  VARCHAR(150) NULL,
    fecha_inicio           DATE NULL,
    fecha_fin              DATE NULL,
    estado                 ENUM('BORRADOR','INSCRIPCION_ABIERTA','INSCRIPCION_CERRADA',
                                'FASE_LIGA','FASE_ELIMINATORIA','FINALIZADO','CANCELADO')
                               NOT NULL DEFAULT 'BORRADOR',

    -- Modalidad: INDIVIDUAL (una persona por inscripción) o DOBLES (pareja).
    -- emparejamiento solo aplica a DOBLES: MANUAL = elegidas al inscribir;
    -- ALEATORIO = sorteadas por el sistema al cerrar la inscripción.
    modalidad              ENUM('INDIVIDUAL','DOBLES')  NOT NULL DEFAULT 'INDIVIDUAL',
    emparejamiento         ENUM('MANUAL','ALEATORIO')   NOT NULL DEFAULT 'MANUAL',

    -- Formato de juego (mejor de N sets). Override por fase: liga vs bracket.
    formato_sets_liga      TINYINT UNSIGNED NOT NULL DEFAULT 3,
    formato_sets_bracket   TINYINT UNSIGNED NOT NULL DEFAULT 5,
    puntos_por_set         SMALLINT UNSIGNED NOT NULL DEFAULT 11,
    diferencia_minima      TINYINT UNSIGNED  NOT NULL DEFAULT 2,

    -- Reglas de la tabla y clasificación
    puntos_por_victoria    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    num_clasificados       SMALLINT UNSIGNED NOT NULL DEFAULT 8,  -- N mejores al bracket

    -- Premiación
    posiciones_premiadas   TINYINT UNSIGNED  NOT NULL DEFAULT 3,
    cuota_inscripcion      DECIMAL(10,2) NULL,
    pozo_total             DECIMAL(12,2) NULL,

    archivado              TINYINT(1) NOT NULL DEFAULT 0,  -- ocultar de listados sin borrar

    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_torneo_formato_liga    CHECK (formato_sets_liga    IN (1,3,5,7)),
    CONSTRAINT chk_torneo_formato_bracket CHECK (formato_sets_bracket IN (1,3,5,7)),
    CONSTRAINT chk_torneo_puntos_set      CHECK (puntos_por_set     >= 1),
    CONSTRAINT chk_torneo_diferencia      CHECK (diferencia_minima  >= 1),
    CONSTRAINT chk_torneo_clasificados    CHECK (num_clasificados   >= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  inscripcion — participación de una persona en un torneo concreto
--  (separa datos maestros [persona] de participación [inscripcion])
-- ---------------------------------------------------------------------
CREATE TABLE inscripcion (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    torneo_id       INT UNSIGNED NOT NULL,
    persona_id      INT UNSIGNED NOT NULL,
    persona_id_2    INT UNSIGNED NULL,             -- compañero/a en dobles (NULL = individual)
    siembra         SMALLINT UNSIGNED NULL,        -- seeding opcional
    pago_realizado  TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inscripcion_torneo_persona (torneo_id, persona_id),
    UNIQUE KEY uq_inscripcion_torneo_persona2 (torneo_id, persona_id_2),
    KEY idx_inscripcion_torneo (torneo_id),
    CONSTRAINT fk_inscripcion_torneo    FOREIGN KEY (torneo_id)    REFERENCES torneo(id)  ON DELETE CASCADE,
    CONSTRAINT fk_inscripcion_persona   FOREIGN KEY (persona_id)   REFERENCES persona(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inscripcion_persona2  FOREIGN KEY (persona_id_2) REFERENCES persona(id) ON DELETE RESTRICT,
    -- En dobles, la pareja no puede ser la misma persona dos veces.
    CONSTRAINT chk_inscripcion_distintos CHECK (persona_id_2 IS NULL OR persona_id_2 <> persona_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  partido — un enfrentamiento (liga o eliminatoria)
--  Se referencia a INSCRIPCION (no a persona) porque el partido vive
--  dentro de un torneo. inscripcion_*_id NULL = bye (descanso).
-- ---------------------------------------------------------------------
CREATE TABLE partido (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    torneo_id                INT UNSIGNED NOT NULL,
    fase                     ENUM('LIGA','ELIMINATORIA') NOT NULL,
    ronda                    SMALLINT UNSIGNED NULL,   -- liga: jornada; bracket: nro de ronda
    orden_en_ronda           SMALLINT UNSIGNED NULL,   -- posición del cruce dentro de la ronda
    inscripcion_a_id         INT UNSIGNED NULL,
    inscripcion_b_id         INT UNSIGNED NULL,
    mesa                     VARCHAR(50) NULL,
    horario                  DATETIME NULL,
    estado                   ENUM('PENDIENTE','EN_JUEGO','FINALIZADO','CANCELADO')
                                 NOT NULL DEFAULT 'PENDIENTE',
    tipo_resultado           ENUM('NORMAL','WALKOVER','RETIRO','DESCALIFICACION','NO_PRESENTADO')
                                 NOT NULL DEFAULT 'NORMAL',
    ganador_inscripcion_id   INT UNSIGNED NULL,
    partido_siguiente_id     INT UNSIGNED NULL,        -- a dónde avanza el ganador (bracket)
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_partido_torneo_fase (torneo_id, fase),
    KEY idx_partido_a (inscripcion_a_id),
    KEY idx_partido_b (inscripcion_b_id),
    CONSTRAINT fk_partido_torneo    FOREIGN KEY (torneo_id)              REFERENCES torneo(id)      ON DELETE CASCADE,
    CONSTRAINT fk_partido_a         FOREIGN KEY (inscripcion_a_id)       REFERENCES inscripcion(id) ON DELETE RESTRICT,
    CONSTRAINT fk_partido_b         FOREIGN KEY (inscripcion_b_id)       REFERENCES inscripcion(id) ON DELETE RESTRICT,
    CONSTRAINT fk_partido_ganador   FOREIGN KEY (ganador_inscripcion_id) REFERENCES inscripcion(id) ON DELETE RESTRICT,
    CONSTRAINT fk_partido_siguiente FOREIGN KEY (partido_siguiente_id)   REFERENCES partido(id)     ON DELETE SET NULL,
    -- Un jugador no puede enfrentarse a sí mismo
    CONSTRAINT chk_partido_distintos CHECK (inscripcion_a_id IS NULL
                                            OR inscripcion_b_id IS NULL
                                            OR inscripcion_a_id <> inscripcion_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  partido_set — marcador set por set ('set' es palabra reservada)
-- ---------------------------------------------------------------------
CREATE TABLE partido_set (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    partido_id   INT UNSIGNED NOT NULL,
    numero_set   TINYINT UNSIGNED NOT NULL,
    puntos_a     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    puntos_b     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partido_set (partido_id, numero_set),
    CONSTRAINT fk_set_partido FOREIGN KEY (partido_id) REFERENCES partido(id) ON DELETE CASCADE,
    CONSTRAINT chk_set_numero CHECK (numero_set BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  premio_config — distribución del premio por posición
-- ---------------------------------------------------------------------
CREATE TABLE premio_config (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    torneo_id    INT UNSIGNED NOT NULL,
    posicion     TINYINT UNSIGNED NOT NULL,        -- 1 = campeón, 2 = subcampeón, ...
    monto        DECIMAL(12,2) NULL,
    porcentaje   DECIMAL(5,2)  NULL,               -- 0..100
    PRIMARY KEY (id),
    UNIQUE KEY uq_premio_torneo_posicion (torneo_id, posicion),
    CONSTRAINT fk_premio_torneo FOREIGN KEY (torneo_id) REFERENCES torneo(id) ON DELETE CASCADE,
    CONSTRAINT chk_premio_posicion   CHECK (posicion >= 1),
    CONSTRAINT chk_premio_porcentaje CHECK (porcentaje IS NULL OR (porcentaje >= 0 AND porcentaje <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  registro_auditoria — bitácora de cambios sensibles (quién/qué/cuándo)
-- ---------------------------------------------------------------------
CREATE TABLE registro_auditoria (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entidad         VARCHAR(60)  NOT NULL,   -- 'partido', 'torneo', ...
    entidad_id      INT UNSIGNED NULL,
    accion          VARCHAR(40)  NOT NULL,   -- 'CREAR','EDITAR','ELIMINAR', ...
    usuario_id      INT UNSIGNED NULL,
    valor_anterior  JSON NULL,
    valor_nuevo     JSON NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_entidad (entidad, entidad_id),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
