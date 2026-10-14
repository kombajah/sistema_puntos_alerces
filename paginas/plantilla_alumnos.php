<?php
require_once 'conexion.php';
requiere_login();
require_once 'xlsx_lib.php';

$cab = ['curso', 'alumno', 'nfc_uid'];
$ejemplos = [
  ['1ro Básico A', 'Cristofer Morales', ''],
  ['1ro Básico A', 'Ana Pérez', '04:AA:BB:CC'],
  ['1ro Básico B', 'Luis Soto', ''],
];

// Plantilla Excel (.xlsx). La columna nfc_uid va con formato Texto para que Excel no convierta los códigos en números.
if (class_exists('ZipArchive')) {
  xlsx_enviar($cab, $ejemplos, [22, 32, 22], 'plantilla_alumnos.xlsx', 'Alumnos', [2]);
}

// Respaldo si el servidor no tiene ZipArchive: CSV en Windows-1252 (ANSI) para que Excel lea bien tildes y ñ.
header('Content-Type: text/csv; charset=Windows-1252');
header('Content-Disposition: attachment; filename="plantilla_alumnos.csv"');
$lineas = ['sep=;', implode(';', $cab)];
foreach ($ejemplos as $e) $lineas[] = '"' . $e[0] . '";"' . $e[1] . '";' . $e[2];
echo mb_convert_encoding(implode("\n", $lineas), 'Windows-1252', 'UTF-8');
exit;
