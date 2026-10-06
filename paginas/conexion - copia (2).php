<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Credenciales: se leen de variables de entorno (Vercel > Settings > Environment
// Variables). Nunca dejes la contraseña real escrita en este archivo ni en git. ---
// Para pruebas locales, copia config.local.example.php a config.local.php (no se sube a git)
// y completa ahí tus datos; se carga automáticamente si existe.
if (file_exists(__DIR__ . '/../config.local.php')) require_once __DIR__ . '/../config.local.php';

$DB_HOST = getenv('DB_HOST') ?: '';
$DB_PORT = (int)(getenv('DB_PORT') ?: 18347);
$DB_NAME = getenv('DB_NAME') ?: '';
$DB_USER = getenv('DB_USER') ?: '';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_CA   = __DIR__ . '/../certs/ca.pem';

try {
  $conn = mysqli_init();
  $conn->ssl_set(null, null, $DB_CA, null, null);
  $conn->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, null, MYSQLI_CLIENT_SSL);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  die("No se pudo conectar a la base de datos. Revisa las variables de entorno DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME en Vercel y que certs/ca.pem esté presente en el repositorio.");
}

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// --- Sesiones guardadas en la base de datos (no en archivos): en Vercel cada solicitud
// puede atenderla un contenedor distinto y perdería una sesión basada en archivos. ---
if (!class_exists('SesionBD')) {
  class SesionBD implements SessionHandlerInterface {
    private $conn;
    function __construct($conn){ $this->conn = $conn; }
    function open($path, $name): bool { return true; }
    function close(): bool { return true; }
    function read($id): string|false {
      $s = $this->conn->prepare("SELECT datos FROM sesiones WHERE id=? AND expira > NOW()");
      $s->bind_param("s", $id); $s->execute();
      $r = $s->get_result()->fetch_assoc();
      return $r ? $r['datos'] : '';
    }
    function write($id, $datos): bool {
      $exp = date('Y-m-d H:i:s', time() + 7200); // 2 horas de inactividad
      $s = $this->conn->prepare("INSERT INTO sesiones (id,datos,expira) VALUES (?,?,?) ON DUPLICATE KEY UPDATE datos=VALUES(datos), expira=VALUES(expira)");
      $s->bind_param("sss", $id, $datos, $exp);
      return $s->execute();
    }
    function destroy($id): bool {
      $s = $this->conn->prepare("DELETE FROM sesiones WHERE id=?"); $s->bind_param("s", $id);
      return $s->execute();
    }
    function gc($max_lifetime): int|false {
      $this->conn->query("DELETE FROM sesiones WHERE expira < NOW()");
      return 0;
    }
  }
}

if (!function_exists('iniciar_sesion')) {
  function iniciar_sesion(){
    global $conn;
    if (session_status() === PHP_SESSION_NONE) {
      session_set_cookie_params(['lifetime'=>7200,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
      session_set_save_handler(new SesionBD($conn), true);
      session_start();
    }
  }
}

if (!function_exists('requiere_login')) {
  function requiere_login(){
    iniciar_sesion();
    if (empty($_SESSION['maestro'])) { header("Location: index.php"); exit; }
  }
}
if (!function_exists('es_admin')) {
  function es_admin(){ return ($_SESSION['rol'] ?? '') === 'admin'; }
}
if (!function_exists('docente_id')) {
  function docente_id(){ return (int)($_SESSION['id'] ?? 0); }
}
// Ya no se usa para cursos/alumnos/metas (son compartidos por todos los docentes);
// solo filtra las opciones de canje, que siguen siendo propias de cada docente.
if (!function_exists('filtro_docente')) {
  function filtro_docente(&$sql, &$types, &$vals, $alias='c'){
    if (!es_admin()) { $sql .= " AND $alias.docente_id = ?"; $types .= 'i'; $vals[] = docente_id(); }
  }
}

// Expresión SQL con "Nombre Apellido" del maestro (si no tiene nombre cargado, usa el usuario).
if (!function_exists('sql_nombre_maestro')) {
  function sql_nombre_maestro($alias='m'){
    return "COALESCE(NULLIF(TRIM(CONCAT($alias.nombre,' ',$alias.apellido)),''), $alias.usuario)";
  }
}
// Registra un inicio de sesión exitoso (hora de Chile). Nunca debe impedir el login: si la
// tabla log_sesiones aún no existe o falla el INSERT, se ignora el error.
if (!function_exists('registrar_login')) {
  function registrar_login($conn, $maestroId, $usuario){
    try {
      $fecha = (new DateTime('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s');
      $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))[0]);
      if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = null;
      $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
      $s = $conn->prepare("INSERT INTO log_sesiones (maestro_id, usuario, fecha, ip, user_agent) VALUES (?,?,?,?,?)");
      $s->bind_param("issss", $maestroId, $usuario, $fecha, $ip, $ua);
      $s->execute();
    } catch (Throwable $e) { /* sin registro, pero el usuario entra igual */ }
  }
}
// Asignatura del maestro con sesión iniciada (null si no tiene). Se guarda en cada movimiento.
if (!function_exists('asignatura_docente')) {
  function asignatura_docente($conn){
    static $cache = false;
    if ($cache === false) {
      $id = docente_id();
      $s = $conn->prepare("SELECT asignatura_id FROM maestros WHERE id=?");
      $s->bind_param("i", $id); $s->execute();
      $r = $s->get_result()->fetch_assoc();
      $cache = ($r && $r['asignatura_id'] !== null) ? (int)$r['asignatura_id'] : null;
    }
    return $cache;
  }
}
