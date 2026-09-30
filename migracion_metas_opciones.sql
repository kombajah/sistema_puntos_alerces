-- Ejecutar si ya tenías la base creada (agrega metas semanales y opciones de canje).
USE sistema_nfc;

CREATE TABLE IF NOT EXISTS metas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  semana_inicio DATE NOT NULL,
  puntos_objetivo INT NOT NULL,
  creado_por INT NOT NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_curso_semana (curso_id, semana_inicio),
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
  FOREIGN KEY (creado_por) REFERENCES maestros(id)
);

CREATE TABLE IF NOT EXISTS opciones_canje (
  id INT AUTO_INCREMENT PRIMARY KEY,
  docente_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  costo_puntos INT NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE
);

ALTER TABLE canjes
  ADD COLUMN IF NOT EXISTS opcion_id INT NULL,
  ADD COLUMN IF NOT EXISTS nombre_opcion VARCHAR(100) NULL;

-- Si tu versión de MySQL/MariaDB no soporta "ADD COLUMN IF NOT EXISTS" (Aiven/MySQL 8 sí lo soporta),
-- quita el "IF NOT EXISTS" de las dos líneas anteriores y ejecútalas solo si las columnas no existen.

ALTER TABLE canjes
  ADD CONSTRAINT fk_canjes_opcion FOREIGN KEY (opcion_id) REFERENCES opciones_canje(id) ON DELETE SET NULL;
