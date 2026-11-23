-- =====================================================================
--  Migración: Sets configurables (1, Mejor de 3, Mejor de 5) en Torneo Fiesta
--  Aplica sobre una base existente:
--    mysql -u root pingpong < database/migrations/2026_11_23_fiesta_sets.sql
-- =====================================================================

ALTER TABLE fiesta
    ADD COLUMN mejor_de TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER clasificados_por_grupo,
    ADD COLUMN sets_rondas_json TEXT NULL AFTER mejor_de,
    ADD CONSTRAINT chk_fiesta_mejor_de CHECK (mejor_de IN (1, 3, 5));

ALTER TABLE fiesta_partido
    ADD COLUMN sets_json TEXT NULL AFTER marcador_b;
