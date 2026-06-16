-- =====================================================================
--  Migración: soporte de torneos de dobles
--  Aplica sobre una base existente (no destructiva).
--    mysql -u root pingpong < database/migrations/2026_06_16_dobles.sql
-- =====================================================================

-- torneo: modalidad + estrategia de emparejamiento
ALTER TABLE torneo
    ADD COLUMN modalidad      ENUM('INDIVIDUAL','DOBLES') NOT NULL DEFAULT 'INDIVIDUAL' AFTER estado,
    ADD COLUMN emparejamiento ENUM('MANUAL','ALEATORIO')  NOT NULL DEFAULT 'MANUAL'     AFTER modalidad;

-- inscripcion: segundo integrante de la pareja (NULL = individual)
ALTER TABLE inscripcion
    ADD COLUMN persona_id_2 INT UNSIGNED NULL AFTER persona_id,
    ADD UNIQUE KEY uq_inscripcion_torneo_persona2 (torneo_id, persona_id_2),
    ADD CONSTRAINT fk_inscripcion_persona2 FOREIGN KEY (persona_id_2) REFERENCES persona(id) ON DELETE RESTRICT,
    ADD CONSTRAINT chk_inscripcion_distintos CHECK (persona_id_2 IS NULL OR persona_id_2 <> persona_id);
