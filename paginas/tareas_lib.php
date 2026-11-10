<?php
// Tareas y actividades por curso: creación automática de la tabla (también en bases ya instaladas) y utilidades compartidas.
// Es una librería: no va en $permitidas de index.php, solo la incluyen tareas.php y reporte_apoderado.php.


// --- CSRF: se define con guardas por si ya lo declara otra librería (feria_lib.php) o conexion.php ---
if (!function_exists('csrf_token')) {
  function csrf_token(){
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
  }
}
if (!function_exists('csrf_campo')) {
  function csrf_campo(){ return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
}
if (!function_exists('csrf_validar')) {
  function csrf_validar(){
    iniciar_sesion();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
      http_response_code(403);
      die('La página caducó. Vuelve atrás, recárgala e inténtalo de nuevo.');
    }
  }
}

function tareas_asegurar($conn){
  iniciar_sesion();
  $k = 'tareas1';
  if (($_SESSION['tareas_schema'] ?? '') === $k) return;      // ya verificado en esta sesión
  $conn->query("CREATE TABLE IF NOT EXISTS tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    fecha_entrega DATE NOT NULL,
    asignatura_id INT NULL,
    creado_por INT NULL,
    creado_en DATETIME NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NULL,
    INDEX idx_tarea_curso (curso_id, fecha_entrega),
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $_SESSION['tareas_schema'] = $k;
}

// «Vie 14/11»
if (!function_exists('fecha_corta')) {
  function fecha_corta(string $fecha): string {
    $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $t = strtotime($fecha);
    return $dias[(int)date('w', $t)] . ' ' . date('d/m', $t);
  }
}

// Cuánto falta para la entrega: [dias, texto, clase de la etiqueta]. $hoy y $fecha vienen como Y-m-d.
if (!function_exists('tarea_etiqueta_fecha')) {
  function tarea_etiqueta_fecha(string $fecha, string $hoy): array {
    $d = (int)(new DateTime($hoy))->diff(new DateTime($fecha))->format('%r%a');
    if ($d < 0)  return ['dias' => $d, 'texto' => 'Vencida', 'clase' => 'text-bg-secondary'];
    if ($d === 0) return ['dias' => $d, 'texto' => 'Hoy', 'clase' => 'text-bg-danger'];
    if ($d === 1) return ['dias' => $d, 'texto' => 'Mañana', 'clase' => 'text-bg-warning'];
    return ['dias' => $d, 'texto' => 'En ' . $d . ' días', 'clase' => $d <= 3 ? 'text-bg-warning' : 'text-bg-primary'];
  }
}
