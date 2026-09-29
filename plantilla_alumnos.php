<?php
require_once 'conexion.php'; requiere_login();
if (!es_admin()) { http_response_code(403); die("Solo el administrador puede descargar la plantilla."); }
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="plantilla_alumnos.csv"');
echo "\xEF\xBB\xBF"; // BOM para que Excel detecte UTF-8 correctamente
$out = fopen('php://output', 'w');
fputcsv($out, ['docente_usuario', 'asignatura', 'curso', 'alumno', 'nfc_uid']);
fputcsv($out, ['jperez', 'Lenguaje', '1ro Básico A', 'Cristofer Morales', '']);
fputcsv($out, ['jperez', 'Lenguaje', '1ro Básico A', 'Ana Pérez', '04:AA:BB:CC']);
fputcsv($out, ['jperez', 'Matemática', '1ro Básico A', 'Cristofer Morales', '']);
fclose($out);
