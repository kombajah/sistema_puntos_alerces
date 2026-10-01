<?php
// Ejecutar UNA vez y luego borrar. Crea el administrador inicial.
require_once 'conexion.php';
$n = $conn->query("SELECT COUNT(*) c FROM maestros")->fetch_assoc()['c'];
if ($n > 0) die("Ya existe al menos un maestro. Borra este archivo.");
$u = "admin"; $p = password_hash("1234", PASSWORD_DEFAULT); $nom = "Administrador"; $ape = "";
$s = $conn->prepare("INSERT INTO maestros (nombre,apellido,usuario,password,rol) VALUES (?,?,?,?,'admin')");
$s->bind_param("ssss", $nom, $ape, $u, $p); $s->execute();
echo "Administrador creado: admin / 1234. Cambia la clave y borra instalar.php.";
