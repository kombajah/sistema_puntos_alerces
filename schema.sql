CREATE DATABASE IF NOT EXISTS sistema_nfc CHARACTER SET utf8mb4;
USE sistema_nfc;

-- Catálogo de asignaturas. Se administra desde el módulo MAESTROS.
-- Cada maestro tiene UNA asignatura (maestros.asignatura_id). Los cursos NO dependen de la asignatura.
CREATE TABLE asignaturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
);

CREATE TABLE maestros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL DEFAULT '',
  apellido VARCHAR(100) NOT NULL DEFAULT '',
  usuario VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol ENUM('admin','docente') NOT NULL DEFAULT 'docente',
  asignatura_id INT NULL,
  FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE SET NULL
);
-- El admin inicial se crea con instalar.php (no lo insertes a mano: necesita password_hash de PHP).

-- Los cursos son compartidos por todos los docentes (no pertenecen a un docente ni a una asignatura).
CREATE TABLE cursos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
);

CREATE TABLE alumnos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  nfc_uid VARCHAR(100) UNIQUE,
  qr_code VARCHAR(40) UNIQUE,
  qr_apoderado VARCHAR(40) UNIQUE NULL,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL
);
INSERT INTO categorias (nombre) VALUES ('Excelente actitud'),('Participación'),('Tarea completada');
-- Las categorías/motivos son compartidas por todos los docentes.

CREATE TABLE config (clave VARCHAR(50) PRIMARY KEY, valor VARCHAR(100) NOT NULL);
INSERT INTO config (clave, valor) VALUES ('tasa_canje', '10');
-- Tasa de canje global.

-- Cada movimiento guarda qué maestro lo registró y con qué asignatura (la del maestro en ese momento).
CREATE TABLE registro_puntos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  categoria_id INT NOT NULL,
  puntos TINYINT NOT NULL,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  maestro_id INT NULL,
  asignatura_id INT NULL,
  masivo TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = asignado a todo el curso de una sola vez
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  FOREIGN KEY (maestro_id) REFERENCES maestros(id) ON DELETE SET NULL,
  FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE SET NULL
);

-- Opciones de canje (premios/ítems), además del canje por puntos base.
CREATE TABLE opciones_canje (
  id INT AUTO_INCREMENT PRIMARY KEY,
  docente_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  costo_puntos INT NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (docente_id) REFERENCES maestros(id) ON DELETE CASCADE
);

CREATE TABLE canjes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  puntos_virtuales INT NOT NULL,
  puntos_base INT NOT NULL,
  observacion VARCHAR(200) DEFAULT '',
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  opcion_id INT NULL,
  nombre_opcion VARCHAR(100) NULL,
  maestro_id INT NULL,
  asignatura_id INT NULL,
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
  FOREIGN KEY (opcion_id) REFERENCES opciones_canje(id) ON DELETE SET NULL,
  FOREIGN KEY (maestro_id) REFERENCES maestros(id) ON DELETE SET NULL,
  FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE SET NULL
);

-- Sesiones persistentes en base de datos (necesario en hosting serverless como Vercel).
CREATE TABLE IF NOT EXISTS sesiones (
  id VARCHAR(128) PRIMARY KEY,
  datos MEDIUMTEXT NOT NULL,
  expira DATETIME NOT NULL
);

-- Metas semanales de puntos por curso (solo asociadas al curso).
-- creado_por = profesor que asigna la meta; cada profesor puede tener una meta por curso y semana.
CREATE TABLE IF NOT EXISTS metas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  semana_inicio DATE NOT NULL,       -- lunes de la semana de la meta
  puntos_objetivo INT NOT NULL,
  creado_por INT NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  descripcion VARCHAR(255) NULL,
  UNIQUE KEY uq_curso_semana_docente (curso_id, semana_inicio, creado_por),
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
  FOREIGN KEY (creado_por) REFERENCES maestros(id) ON DELETE SET NULL
);

-- Frases de refuerzo positivo que muestra nfc.php al asignar puntos.
CREATE TABLE IF NOT EXISTS frases_refuerzo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT NOT NULL,
  frase VARCHAR(255) NOT NULL,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);
