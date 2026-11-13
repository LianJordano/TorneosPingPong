-- =====================================================================
--  Migración: Torneo fiesta (sistema paralelo, tablas nuevas)
--  No modifica tablas existentes. Aplica sobre una base existente:
--    mysql -u root pingpong < database/migrations/2026_11_13_torneo_fiesta.sql
-- =====================================================================

SET NAMES utf8mb4;

-- fiesta — torneo rápido con sorteo animado (ruleta)
CREATE TABLE IF NOT EXISTS fiesta (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre                  VARCHAR(150) NOT NULL,
    modalidad               ENUM('INDIVIDUAL','DOBLES') NOT NULL DEFAULT 'DOBLES',
    formato                 ENUM('ELIMINACION','GRUPOS') NOT NULL DEFAULT 'ELIMINACION',
    num_grupos              TINYINT UNSIGNED NOT NULL DEFAULT 2,
    clasificados_por_grupo  TINYINT UNSIGNED NOT NULL DEFAULT 2,
    -- INSCRIPCION → (sorteo) → GRUPOS | ELIMINATORIA → FINALIZADO
    estado                  ENUM('INSCRIPCION','GRUPOS','ELIMINATORIA','FINALIZADO')
                                NOT NULL DEFAULT 'INSCRIPCION',
    -- Guion del sorteo (pasos de la ruleta) para reproducirlo cuantas veces se quiera.
    sorteo_json             LONGTEXT NULL,
    sorteado_at             DATETIME NULL,
    created_by              INT UNSIGNED NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fiesta_estado (estado),
    CONSTRAINT fk_fiesta_usuario FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL,
    CONSTRAINT chk_fiesta_grupos      CHECK (num_grupos BETWEEN 1 AND 16),
    CONSTRAINT chk_fiesta_clasificados CHECK (clasificados_por_grupo BETWEEN 1 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fiesta_participante — se registra solo con el nombre
CREATE TABLE IF NOT EXISTS fiesta_participante (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fiesta_id   INT UNSIGNED NOT NULL,
    nombre      VARCHAR(80) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fiesta_participante_nombre (fiesta_id, nombre),
    CONSTRAINT fk_fiesta_participante_fiesta FOREIGN KEY (fiesta_id) REFERENCES fiesta(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fiesta_equipo — resultado del sorteo: 1 persona (individual) o pareja (dobles)
CREATE TABLE IF NOT EXISTS fiesta_equipo (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fiesta_id         INT UNSIGNED NOT NULL,
    numero            SMALLINT UNSIGNED NOT NULL,           -- orden de salida en la ruleta (1..N)
    participante_1_id INT UNSIGNED NOT NULL,
    participante_2_id INT UNSIGNED NULL,
    grupo             TINYINT UNSIGNED NULL,                -- 1..G (solo formato GRUPOS)
    PRIMARY KEY (id),
    UNIQUE KEY uq_fiesta_equipo_numero (fiesta_id, numero),
    CONSTRAINT fk_fiesta_equipo_fiesta FOREIGN KEY (fiesta_id) REFERENCES fiesta(id) ON DELETE CASCADE,
    CONSTRAINT fk_fiesta_equipo_p1 FOREIGN KEY (participante_1_id) REFERENCES fiesta_participante(id) ON DELETE CASCADE,
    CONSTRAINT fk_fiesta_equipo_p2 FOREIGN KEY (participante_2_id) REFERENCES fiesta_participante(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fiesta_partido — partidos de grupo y de eliminatoria (NULL en equipo = por definir / bye)
CREATE TABLE IF NOT EXISTS fiesta_partido (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fiesta_id          INT UNSIGNED NOT NULL,
    fase               ENUM('GRUPOS','ELIMINATORIA') NOT NULL,
    grupo              TINYINT UNSIGNED NULL,
    ronda              SMALLINT UNSIGNED NOT NULL,
    orden              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    equipo_a_id        INT UNSIGNED NULL,
    equipo_b_id        INT UNSIGNED NULL,
    marcador_a         SMALLINT UNSIGNED NULL,
    marcador_b         SMALLINT UNSIGNED NULL,
    ganador_id         INT UNSIGNED NULL,
    es_bye             TINYINT(1) NOT NULL DEFAULT 0,
    siguiente_id       INT UNSIGNED NULL,
    estado             ENUM('PENDIENTE','FINALIZADO') NOT NULL DEFAULT 'PENDIENTE',
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fiesta_partido_fase (fiesta_id, fase),
    CONSTRAINT fk_fiesta_partido_fiesta FOREIGN KEY (fiesta_id) REFERENCES fiesta(id) ON DELETE CASCADE,
    CONSTRAINT fk_fiesta_partido_a FOREIGN KEY (equipo_a_id) REFERENCES fiesta_equipo(id) ON DELETE CASCADE,
    CONSTRAINT fk_fiesta_partido_b FOREIGN KEY (equipo_b_id) REFERENCES fiesta_equipo(id) ON DELETE CASCADE,
    CONSTRAINT fk_fiesta_partido_ganador FOREIGN KEY (ganador_id) REFERENCES fiesta_equipo(id) ON DELETE CASCADE,
    CONSTRAINT fk_fiesta_partido_siguiente FOREIGN KEY (siguiente_id) REFERENCES fiesta_partido(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
