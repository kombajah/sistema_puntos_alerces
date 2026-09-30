-- Ejecutar si ya tenías la base creada.
USE sistema_nfc;
ALTER TABLE metas ADD COLUMN descripcion VARCHAR(255) NULL;
CREATE TABLE IF NOT EXISTS frases_refuerzo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT NOT NULL,
  frase VARCHAR(255) NOT NULL,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);
