<?php
require_once 'conexion.php'; 
requiere_login();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="plantilla_alumnos.csv"');

// 1. Marca UTF-8 BOM para caracteres especiales (tildes, ñ)
echo "\xEF\xBB\xBF";

// 2. Instrucción explícita para que Excel aplique las columnas automáticamente
echo "sep=;\n";

// 3. Encabezados de la plantilla
echo "docente_usuario;asignatura;curso;alumno;nfc_uid\n";

// 4. Filas de ejemplo
echo 'jperez;Lenguaje;"1ro Básico A";"Cristofer Morales";' . "\n";
echo 'jperez;Lenguaje;"1ro Básico A";"Ana Pérez";04:AA:BB:CC' . "\n";
echo 'jperez;Matemática;"1ro Básico A";"Cristofer Morales";' . "\n";
exit;