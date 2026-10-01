-- =====================================================================
-- Migración v2026 (modificaciones): asignatura pasa a ser del MAESTRO.
-- Ejecutar UNA sola vez sobre una base creada con la versión anterior.
-- ¡HAZ RESPALDO ANTES! (Aiven > Backups, o mysqldump).
-- Si tu base no se llama sistema_nfc, cambia la línea USE.
-- =====================================================================
USE sistema_nfc;

-- 1) MAESTROS: nombre, apellido y asignatura ---------------------------
ALTER TABLE maestros
  ADD COLUMN nombre VARCHAR(100) NOT NULL DEFAULT '' AFTER id,
  ADD COLUMN apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre,
  ADD COLUMN asignatura_id INT NULL;

-- Cada maestro hereda UNA de sus asignaturas actuales (la primera que creó).
UPDATE maestros m
   SET m.asignatura_id = (SELECT MIN(a.id) FROM asignaturas a WHERE a.docente_id = m.id);

ALTER TABLE maestros
  ADD CONSTRAINT fk_maestros_asignatura FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE SET NULL;

-- 2) HISTÓRICO: quién registró cada movimiento y con qué asignatura -----
ALTER TABLE registro_puntos
  ADD COLUMN maestro_id INT NULL,
  ADD COLUMN asignatura_id INT NULL;
ALTER TABLE canjes
  ADD COLUMN maestro_id INT NULL,
  ADD COLUMN asignatura_id INT NULL;

-- Se rellena con el dueño y la asignatura del curso (datos antiguos).
UPDATE registro_puntos r
  JOIN alumnos a ON a.id = r.alumno_id
  JOIN cursos c ON c.id = a.curso_id
   SET r.maestro_id = c.docente_id, r.asignatura_id = c.asignatura_id;
UPDATE canjes x
  JOIN alumnos a ON a.id = x.alumno_id
  JOIN cursos c ON c.id = a.curso_id
   SET x.maestro_id = c.docente_id, x.asignatura_id = c.asignatura_id;

ALTER TABLE registro_puntos
  ADD CONSTRAINT fk_rp_maestro FOREIGN KEY (maestro_id) REFERENCES maestros(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_rp_asignatura FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE SET NULL;
ALTER TABLE canjes
  ADD CONSTRAINT fk_canjes_maestro FOREIGN KEY (maestro_id) REFERENCES maestros(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_canjes_asignatura FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE SET NULL;

-- 3) METAS: solo por curso; el profesor que la crea se conserva --------
-- 3a) unicidad por (curso, semana, profesor) en vez de (curso, semana)
ALTER TABLE metas ADD UNIQUE KEY uq_curso_semana_docente (curso_id, semana_inicio, creado_por);
ALTER TABLE metas DROP INDEX uq_curso_semana;

-- 3b) creado_por ahora puede quedar NULL si se elimina al maestro (la meta se conserva)
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'metas' AND COLUMN_NAME = 'creado_por'
               AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @q := IF(@fk IS NULL, 'DO 0', CONCAT('ALTER TABLE metas DROP FOREIGN KEY `', @fk, '`'));
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
ALTER TABLE metas MODIFY creado_por INT NULL;
ALTER TABLE metas
  ADD CONSTRAINT fk_metas_creado_por FOREIGN KEY (creado_por) REFERENCES maestros(id) ON DELETE SET NULL;

-- 4) CURSOS: ya no dependen de docente ni de asignatura ---------------
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos' AND COLUMN_NAME = 'asignatura_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @q := IF(@fk IS NULL, 'DO 0', CONCAT('ALTER TABLE cursos DROP FOREIGN KEY `', @fk, '`'));
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos' AND COLUMN_NAME = 'docente_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @q := IF(@fk IS NULL, 'DO 0', CONCAT('ALTER TABLE cursos DROP FOREIGN KEY `', @fk, '`'));
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

ALTER TABLE cursos DROP COLUMN asignatura_id, DROP COLUMN docente_id;

-- 5) ASIGNATURAS: catálogo global (ya no pertenecen a un docente) ------
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asignaturas' AND COLUMN_NAME = 'docente_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @q := IF(@fk IS NULL, 'DO 0', CONCAT('ALTER TABLE asignaturas DROP FOREIGN KEY `', @fk, '`'));
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
ALTER TABLE asignaturas DROP COLUMN docente_id;

-- ---------------------------------------------------------------------
-- REVISIÓN MANUAL tras migrar: si antes tenías el mismo curso repetido
-- (ej. "1ro Básico A" bajo Lenguaje y bajo Matemática), ahora aparecerá
-- dos veces con los mismos nombres. Revisa con:
--   SELECT nombre, COUNT(*) FROM cursos GROUP BY nombre HAVING COUNT(*) > 1;
-- y unifica alumnos/cursos antes de seguir usando el sistema.
-- ---------------------------------------------------------------------
