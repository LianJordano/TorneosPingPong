-- =====================================================================
--  Migración: Formato LIGA (todos contra todos + eliminatoria) en fiesta
--  Aplica sobre una base existente:
--    mysql -u root pingpong < database/migrations/2026_11_23_fiesta_liga.sql
-- =====================================================================

ALTER TABLE fiesta MODIFY COLUMN formato ENUM('ELIMINACION','GRUPOS','LIGA') NOT NULL DEFAULT 'ELIMINACION';
