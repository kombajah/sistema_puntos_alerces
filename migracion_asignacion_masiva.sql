-- Ejecutar UNA vez si ya aplicaste migracion_v2026_modificaciones.sql (o ya tienes la base en producción).
-- Marca en el histórico los puntos asignados a todo el curso de una sola vez.
-- Los registros anteriores quedan como NO masivos (no hay forma confiable de reconstruirlos).
USE sistema_nfc;
ALTER TABLE registro_puntos ADD COLUMN masivo TINYINT(1) NOT NULL DEFAULT 0;
