-- =====================================================================
--  Migración: vínculo usuario ↔ persona (jugadores como espectadores)
--  Aplica sobre una base existente (no destructiva).
--    mysql -u root pingpong < database/migrations/2026_06_19_usuario_persona.sql
-- =====================================================================
--  Permite convertir un jugador (persona) en usuario de la plataforma con
--  rol ESPECTADOR. La relación es 1:1 y opcional: un usuario puede no estar
--  vinculado a ninguna persona (admins/anotadores) y una persona puede no
--  tener usuario. ON DELETE SET NULL: si se borra la persona, el login
--  sobrevive pero queda desvinculado.
-- =====================================================================

ALTER TABLE usuario
    ADD COLUMN persona_id INT UNSIGNED NULL AFTER rol,
    ADD UNIQUE KEY uq_usuario_persona (persona_id),
    ADD CONSTRAINT fk_usuario_persona FOREIGN KEY (persona_id) REFERENCES persona(id) ON DELETE SET NULL;
