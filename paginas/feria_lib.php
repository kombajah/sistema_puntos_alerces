<?php
// Ferias del canje: creación automática de tablas (también en bases ya instaladas) y utilidades compartidas.
// Este archivo es una librería: no está en $permitidas de index.php, solo lo incluyen feria.php, ferias.php e historico.php.

// --- CSRF: este sistema no traía protección, se define aquí (con guarda por si luego se mueve a conexion.php) ---
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

// Versión del esquema de ferias: súbela si en el futuro se agregan columnas/tablas nuevas.
const FERIA_SCHEMA_VERSION = 'v1';

function feria_asegurar($conn){
  iniciar_sesion();
  if (($_SESSION['feria_schema'] ?? '') === FERIA_SCHEMA_VERSION) return;      // ya verificado en esta sesión
  $conn->query("CREATE TABLE IF NOT EXISTS ferias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    fecha DATE NULL,
    estado VARCHAR(10) NOT NULL DEFAULT 'abierta',
    creada DATETIME NULL,
    creada_por INT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $conn->query("CREATE TABLE IF NOT EXISTS feria_productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feria_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    costo_puntos INT NOT NULL,
    stock INT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (feria_id) REFERENCES ferias(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  foreach (['feria_id' => 'INT NULL', 'feria_producto_id' => 'INT NULL', 'ticket' => 'VARCHAR(16) NULL'] as $col => $def) {
    $q = $conn->prepare("SELECT COUNT(*) t FROM information_schema.COLUMNS WHERE table_schema=DATABASE() AND table_name='canjes' AND column_name=?");
    $q->bind_param("s", $col); $q->execute();
    if (!(int)$q->get_result()->fetch_assoc()['t']) $conn->query("ALTER TABLE canjes ADD COLUMN $col $def");
  }
  $_SESSION['feria_schema'] = FERIA_SCHEMA_VERSION;
}

function feria_abiertas($conn){
  return $conn->query("SELECT id, nombre, fecha FROM ferias WHERE estado='abierta' ORDER BY fecha DESC, id DESC")->fetch_all(MYSQLI_ASSOC);
}

// Productos de una feria con unidades usadas y disponibles (disp = null cuando no hay límite de stock).
function feria_productos($conn, $feriaId, $soloActivos = false){
  $sql = "SELECT p.id, p.nombre, p.costo_puntos costo, p.stock, p.activo,
          (SELECT COUNT(*) FROM canjes c WHERE c.feria_producto_id=p.id) usados
          FROM feria_productos p WHERE p.feria_id=?" . ($soloActivos ? " AND p.activo=1" : "") . " ORDER BY p.costo_puntos, p.nombre";
  $s = $conn->prepare($sql); $s->bind_param("i", $feriaId); $s->execute();
  $r = $s->get_result()->fetch_all(MYSQLI_ASSOC);
  foreach ($r as &$p) {
    $p['id'] = (int)$p['id']; $p['costo'] = (int)$p['costo']; $p['usados'] = (int)$p['usados']; $p['activo'] = (int)$p['activo'];
    $p['stock'] = $p['stock'] === null ? null : (int)$p['stock'];
    $p['disp'] = $p['stock'] === null ? null : max(0, $p['stock'] - $p['usados']);
  }
  unset($p);
  return $r;
}

function feria_saldo($conn, $alumnoId){
  $s = $conn->prepare("SELECT COALESCE((SELECT SUM(puntos) FROM registro_puntos WHERE alumno_id=a.id),0)
                            - COALESCE((SELECT SUM(puntos_virtuales) FROM canjes WHERE alumno_id=a.id),0) saldo
                       FROM alumnos a WHERE a.id=?");
  $s->bind_param("i", $alumnoId); $s->execute();
  $r = $s->get_result()->fetch_assoc();
  return $r ? (int)$r['saldo'] : null;
}

// Registra el canje de uno o más premios de una feria para un alumno, todo o nada.
// $items: [producto_id => cantidad]. Devuelve [ok, mensaje, datos].
function feria_canjear($conn, $feriaId, $alumnoId, array $items){
  $it = [];
  foreach ($items as $pid => $q) { $pid = (int)$pid; $q = (int)$q; if ($pid > 0 && $q > 0) $it[$pid] = $q; }
  if (!$it) return [false, 'Selecciona al menos un premio.', null];
  if (array_sum($it) > 20) return [false, 'Máximo 20 premios por canje.', null];
  try {
    $conn->begin_transaction();
    $s = $conn->prepare("SELECT id, nombre, estado FROM ferias WHERE id=? FOR UPDATE"); $s->bind_param("i", $feriaId); $s->execute();
    $f = $s->get_result()->fetch_assoc();
    if (!$f || $f['estado'] !== 'abierta') { $conn->rollback(); return [false, 'Esa feria está cerrada o ya no existe.', null]; }
    $s = $conn->prepare("SELECT id FROM alumnos WHERE id=? FOR UPDATE"); $s->bind_param("i", $alumnoId); $s->execute();
    if (!$s->get_result()->fetch_assoc()) { $conn->rollback(); return [false, 'Alumno no válido.', null]; }

    $ids = array_keys($it);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $s = $conn->prepare("SELECT p.id, p.nombre, p.costo_puntos, p.stock, p.activo,
                         (SELECT COUNT(*) FROM canjes c WHERE c.feria_producto_id=p.id) usados
                         FROM feria_productos p WHERE p.feria_id=? AND p.id IN ($in) FOR UPDATE");
    $params = array_merge([$feriaId], $ids);
    $s->bind_param(str_repeat('i', count($params)), ...$params); $s->execute();
    $prods = [];
    foreach ($s->get_result() as $p) $prods[(int)$p['id']] = $p;

    $total = 0; $nombres = [];
    foreach ($it as $pid => $q) {
      $p = $prods[$pid] ?? null;
      if (!$p || !$p['activo']) { $conn->rollback(); return [false, 'Un premio ya no está disponible en esta feria.', null]; }
      if ($p['stock'] !== null && (int)$p['usados'] + $q > (int)$p['stock']) { $conn->rollback(); return [false, 'No queda stock suficiente de «' . $p['nombre'] . '».', null]; }
      $total += (int)$p['costo_puntos'] * $q;
      $nombres[] = $p['nombre'] . ($q > 1 ? ' ×' . $q : '');
    }
    $saldo = feria_saldo($conn, $alumnoId);
    if ($saldo < $total) { $conn->rollback(); return [false, "Saldo insuficiente: se necesitan $total pts y el alumno tiene $saldo.", null]; }

    $ticket = bin2hex(random_bytes(6)); $mid = docente_id(); $asig = asignatura_docente($conn);
    $fecha = ahora_chile(); $obs = mb_substr($f['nombre'], 0, 200);
    $ins = $conn->prepare("INSERT INTO canjes (alumno_id, puntos_virtuales, puntos_base, observacion, opcion_id, nombre_opcion, maestro_id, asignatura_id, fecha, feria_id, feria_producto_id, ticket)
                           VALUES (?,?,0,?,NULL,?,?,?,?,?,?,?)");
    foreach ($it as $pid => $q) {
      $p = $prods[$pid]; $costo = (int)$p['costo_puntos']; $nom = $p['nombre'];
      for ($i = 0; $i < $q; $i++) {
        $ins->bind_param("iissiisiis", $alumnoId, $costo, $obs, $nom, $mid, $asig, $fecha, $feriaId, $pid, $ticket);
        $ins->execute();
      }
    }
    $conn->commit();
    return [true, 'Canje registrado', ['saldo' => $saldo - $total, 'total' => $total, 'premios' => implode(', ', $nombres), 'feria' => $f['nombre']]];
  } catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $e2) {}
    return [false, 'No se pudo registrar el canje. Inténtalo de nuevo.', null];
  }
}
