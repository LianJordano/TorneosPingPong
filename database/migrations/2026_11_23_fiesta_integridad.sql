-- =====================================================================
--  Migración: Integridad de slots en partidos del torneo fiesta
--  Evita duplicados por doble envío en sorteo o generación de eliminatoria.
--  Aplica sobre una base existente:
--    mysql -u root pingpong < database/migrations/2026_11_23_fiesta_integridad.sql
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE fiesta_partido
    ADD UNIQUE KEY uq_fiesta_partido_slot (fiesta_id, fase, ronda, orden);
