<?php
require_once 'conexion.php'; 
requiere_login();

// Encabezados HTTP indicando codificación Windows-1252 (ANSI)
header('Content-Type: text/csv; charset=Windows-1252');
header('Content-Disposition: attachment; filename="plantilla_alumnos.csv"');

// 1. Definir el contenido del CSV con las instrucciones para Excel
$lineas = [];
$lineas[] = "sep=;";
$lineas[] = "docente_usuario;asignatura;curso;alumno;nfc_uid";
$lineas[] = 'jperez;Lenguaje;"1ro Básico A";"Cristofer Morales";';
$lineas[] = 'jperez;Lenguaje;"1ro Básico A";"Ana Pérez";04:AA:BB:CC';
$lineas[] = 'jperez;Matemática;"1ro Básico A";"Cristofer Morales";';

$contenido = implode("\n", $lineas);

// 2. Convertir la codificación de UTF-8 a Windows-1252 (ANSI) para Excel
echo mb_convert_encoding($contenido, 'Windows-1252', 'UTF-8');
exit;