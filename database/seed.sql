-- =====================================================================
--  seed.sql — Datos de prueba para probar el flujo completo (Fase 0)
--  Ejecutar DESPUÉS de schema.sql:
--      mysql -u root pingpong < database/seed.sql
-- =====================================================================
--  Credenciales de prueba (contraseña en texto plano = "admin123"):
--      admin@pingpong.local      → ADMIN
--      anotador@pingpong.local   → ANOTADOR
--      espectador@pingpong.local → ESPECTADOR
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  Usuarios (el hash corresponde a la contraseña "admin123")
-- ---------------------------------------------------------------------
INSERT INTO usuario (nombre, email, password_hash, rol) VALUES
('Administrador',   'admin@pingpong.local',      '$2y$10$fLpYf606oq0l7BMRsOauROqiE0HaynNL0mioGJY235jPSiYFhJOFy', 'ADMIN'),
('Anotador Mesa 1', 'anotador@pingpong.local',   '$2y$10$fLpYf606oq0l7BMRsOauROqiE0HaynNL0mioGJY235jPSiYFhJOFy', 'ANOTADOR'),
('Espectador',      'espectador@pingpong.local', '$2y$10$fLpYf606oq0l7BMRsOauROqiE0HaynNL0mioGJY235jPSiYFhJOFy', 'ESPECTADOR');

-- ---------------------------------------------------------------------
--  Catálogos: áreas y niveles
-- ---------------------------------------------------------------------
INSERT INTO area (nombre) VALUES
('Sistemas'), ('Ventas'), ('RRHH'), ('Operaciones'), ('Marketing'), ('Finanzas'), ('Soporte');

INSERT INTO nivel (nombre) VALUES
('Principiante'), ('Intermedio'), ('Avanzado');

-- ---------------------------------------------------------------------
--  Personas (jugadores) — area_id / nivel_id referencian los catálogos
-- ---------------------------------------------------------------------
INSERT INTO persona (nombre, apellido, identificador_interno, area_id, email, nivel_id) VALUES
('Lucía',   'Gómez',     'EMP-001', (SELECT id FROM area WHERE nombre='Sistemas'),    'lucia.gomez@empresa.local',    (SELECT id FROM nivel WHERE nombre='Avanzado')),
('Martín',  'Pereira',   'EMP-002', (SELECT id FROM area WHERE nombre='Ventas'),      'martin.pereira@empresa.local', (SELECT id FROM nivel WHERE nombre='Intermedio')),
('Sofía',   'Rodríguez', 'EMP-003', (SELECT id FROM area WHERE nombre='RRHH'),        'sofia.rodriguez@empresa.local',(SELECT id FROM nivel WHERE nombre='Intermedio')),
('Diego',   'Fernández', 'EMP-004', (SELECT id FROM area WHERE nombre='Operaciones'), 'diego.fernandez@empresa.local',(SELECT id FROM nivel WHERE nombre='Avanzado')),
('Valentina','Castro',   'EMP-005', (SELECT id FROM area WHERE nombre='Marketing'),   'valentina.castro@empresa.local',(SELECT id FROM nivel WHERE nombre='Principiante')),
('Tomás',   'Acosta',    'EMP-006', (SELECT id FROM area WHERE nombre='Finanzas'),    'tomas.acosta@empresa.local',   (SELECT id FROM nivel WHERE nombre='Intermedio')),
('Camila',  'Sosa',      'EMP-007', (SELECT id FROM area WHERE nombre='Sistemas'),    'camila.sosa@empresa.local',    (SELECT id FROM nivel WHERE nombre='Avanzado')),
('Joaquín', 'Méndez',    'EMP-008', (SELECT id FROM area WHERE nombre='Soporte'),     'joaquin.mendez@empresa.local', (SELECT id FROM nivel WHERE nombre='Principiante'));

-- ---------------------------------------------------------------------
--  Torneo de ejemplo (en inscripción abierta)
-- ---------------------------------------------------------------------
INSERT INTO torneo
    (nombre, descripcion, lugar, fecha_inicio, fecha_fin, estado,
     formato_sets_liga, formato_sets_bracket, puntos_por_set, diferencia_minima,
     puntos_por_victoria, num_clasificados, posiciones_premiadas,
     cuota_inscripcion, pozo_total)
VALUES
    ('Torneo Interno 2026', 'Torneo anual de ping pong de la empresa.', 'Sala de descanso — Piso 3',
     '2026-07-01', '2026-07-31', 'INSCRIPCION_ABIERTA',
     3, 5, 11, 2,
     1, 4, 3,
     10000.00, NULL);

-- ---------------------------------------------------------------------
--  Inscripciones (6 de las 8 personas anotadas al torneo)
-- ---------------------------------------------------------------------
INSERT INTO inscripcion (torneo_id, persona_id, pago_realizado) VALUES
(1, 1, 1),
(1, 2, 1),
(1, 3, 1),
(1, 4, 0),
(1, 5, 1),
(1, 6, 1);

-- ---------------------------------------------------------------------
--  Distribución del premio (50% / 30% / 20%)
-- ---------------------------------------------------------------------
INSERT INTO premio_config (torneo_id, posicion, porcentaje) VALUES
(1, 1, 50.00),
(1, 2, 30.00),
(1, 3, 20.00);
