CREATE DATABASE IF NOT EXISTS sistema_nfc CHARACTER SET utf8mb4;
USE sistema_nfc;

CREATE TABLE maestros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol ENUM('admin','docente') NOT NULL DEFAULT 'docente'
);
-- El admin inicial se crea con instalar.php (no lo insertes a mano: necesita password_hash de PHP).

CREATE TABLE asignaturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  docente_id INT NOT NULL,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE
);

-- El mismo nombre de curso puede repetirse: cada fila es "un curso dictado por
-- un docente, en una asignatura", con sus propios alumnos y puntajes.
CREATE TABLE cursos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  docente_id INT NOT NULL,
  asignatura_id INT NOT NULL,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE,
  FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE
);

CREATE TABLE alumnos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  nfc_uid VARCHAR(100) UNIQUE,
  qr_code VARCHAR(40) UNIQUE,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL
);
INSERT INTO categorias (nombre) VALUES ('Excelente actitud'),('Participación'),('Tarea completada');
-- Las categorías/motivos son compartidas por todos los docentes. Si prefieres que
-- cada docente maneje las suyas, se puede agregar una columna docente_id aquí también.

CREATE TABLE config (clave VARCHAR(50) PRIMARY KEY, valor VARCHAR(100) NOT NULL);
INSERT INTO config (clave, valor) VALUES ('tasa_canje', '10');
-- Tasa de canje global. Si cada docente necesita su propia tasa, se puede
-- convertir en (docente_id, valor) más adelante.

CREATE TABLE registro_puntos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  categoria_id INT NOT NULL,
  puntos TINYINT NOT NULL,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

CREATE TABLE canjes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  puntos_virtuales INT NOT NULL,
  puntos_base INT NOT NULL,
  observacion VARCHAR(200) DEFAULT '',
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
);

-- Sesiones persistentes en base de datos (necesario en hosting serverless como Vercel,
-- donde el sistema de archivos no se comparte entre invocaciones).
CREATE TABLE IF NOT EXISTS sesiones (
  id VARCHAR(128) PRIMARY KEY,
  datos MEDIUMTEXT NOT NULL,
  expira DATETIME NOT NULL
);

-- Metas semanales de puntos por curso.
CREATE TABLE IF NOT EXISTS metas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  semana_inicio DATE NOT NULL,       -- lunes de la semana de la meta
  puntos_objetivo INT NOT NULL,
  creado_por INT NOT NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_curso_semana (curso_id, semana_inicio),
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
  FOREIGN KEY (creado_por) REFERENCES maestros(id)
);

-- Opciones de canje (premios/ítems), además del canje por puntos base existente.
CREATE TABLE IF NOT EXISTS opciones_canje (
  id INT AUTO_INCREMENT PRIMARY KEY,
  docente_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  costo_puntos INT NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE
);

ALTER TABLE canjes
  ADD COLUMN opcion_id INT NULL,
  ADD COLUMN nombre_opcion VARCHAR(100) NULL,
  ADD FOREIGN KEY (opcion_id) REFERENCES opciones_canje(id) ON DELETE SET NULL;

-- Columna usada por reporte_apoderado.php (detectada en el proyecto; se agrega aquí
-- para que una instalación nueva desde este schema.sql quede completa).
ALTER TABLE alumnos ADD COLUMN qr_apoderado VARCHAR(40) UNIQUE NULL;
