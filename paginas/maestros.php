<?php
require_once 'conexion.php'; requiere_login();
if (!es_admin()) { http_response_code(403); die("Solo el administrador puede gestionar maestros."); }
$mensaje=''; $error='';
$ed  = (int)($_GET['editar'] ?? 0);       // maestro en edición
$eda = (int)($_GET['editar_asig'] ?? 0);  // asignatura en edición

function asignatura_existe($conn,$id){
  if ($id <= 0) return false;
  $s=$conn->prepare("SELECT id FROM asignaturas WHERE id=?"); $s->bind_param("i",$id); $s->execute();
  return (bool)$s->get_result()->fetch_assoc();
}
function nombre_asignatura_repetido($conn,$nombre,$excluir=0){
  $s=$conn->prepare("SELECT id FROM asignaturas WHERE nombre=? AND id<>?"); $s->bind_param("si",$nombre,$excluir); $s->execute();
  return (bool)$s->get_result()->fetch_assoc();
}

if ($_SERVER["REQUEST_METHOD"]=="POST") {
  try {
    // ---------- MANTENEDOR DE ASIGNATURAS (movido desde Contenido) ----------
    if (isset($_POST['crear_asignatura'])) {
      $n = mb_substr(trim($_POST['nombre_asignatura'] ?? ''), 0, 100);
      if ($n === '') $error = "Escribe el nombre de la asignatura.";
      elseif (nombre_asignatura_repetido($conn,$n)) $error = "Esa asignatura ya existe.";
      else { $s=$conn->prepare("INSERT INTO asignaturas (nombre) VALUES (?)"); $s->bind_param("s",$n); $s->execute(); $mensaje = "Asignatura creada."; }

    } elseif (isset($_POST['editar_asignatura'])) {
      $id=(int)$_POST['id']; $n = mb_substr(trim($_POST['nombre_asignatura'] ?? ''), 0, 100);
      if ($n === '') $error = "Escribe el nombre de la asignatura.";
      elseif (!asignatura_existe($conn,$id)) $error = "Asignatura no válida.";
      elseif (nombre_asignatura_repetido($conn,$n,$id)) $error = "Ya existe otra asignatura con ese nombre.";
      else { $s=$conn->prepare("UPDATE asignaturas SET nombre=? WHERE id=?"); $s->bind_param("si",$n,$id); $s->execute(); $mensaje = "Asignatura actualizada."; $eda = 0; }

    } elseif (isset($_POST['borrar_asignatura'])) {
      $id=(int)$_POST['id'];
      $s=$conn->prepare("SELECT
          (SELECT COUNT(*) FROM maestros WHERE asignatura_id=?) maestros,
          (SELECT COUNT(*) FROM registro_puntos WHERE asignatura_id=?) puntos,
          (SELECT COUNT(*) FROM canjes WHERE asignatura_id=?) canjes");
      $s->bind_param("iii",$id,$id,$id); $s->execute(); $u=$s->get_result()->fetch_assoc();
      if (!asignatura_existe($conn,$id)) $error = "Asignatura no válida.";
      elseif ((int)$u['maestros'] > 0) $error = "No se puede eliminar: hay ".(int)$u['maestros']." maestro(s) con esta asignatura. Cámbialos de asignatura primero.";
      elseif ((int)$u['puntos'] + (int)$u['canjes'] > 0) $error = "No se puede eliminar: tiene movimientos en el histórico. Puedes renombrarla en su lugar.";
      else { $s=$conn->prepare("DELETE FROM asignaturas WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $mensaje = "Asignatura eliminada."; }

    // ---------- MAESTROS ----------
    } elseif (isset($_POST['crear'])) {
      $nom = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100);
      $ape = mb_substr(trim($_POST['apellido'] ?? ''), 0, 100);
      $u   = trim($_POST['usuario'] ?? ''); $p = $_POST['password'] ?? '';
      $rol = ($_POST['rol'] ?? '')==='admin' ? 'admin' : 'docente';
      $aid = (int)($_POST['asignatura_id'] ?? 0);
      if ($nom === '' || $ape === '' || $u === '') $error = "Nombre, apellido y usuario son obligatorios.";
      elseif (strlen($p)<6) $error = "La contraseña debe tener al menos 6 caracteres.";
      elseif ($aid > 0 && !asignatura_existe($conn,$aid)) $error = "Asignatura no válida.";
      elseif ($rol==='docente' && $aid <= 0) $error = "Elige la asignatura del docente (si no existe, créala en el cuadro Asignaturas).";
      else {
        $aidVal = $aid > 0 ? $aid : null;
        $h=password_hash($p,PASSWORD_DEFAULT);
        $s=$conn->prepare("INSERT INTO maestros (nombre,apellido,usuario,password,rol,asignatura_id) VALUES (?,?,?,?,?,?)");
        $s->bind_param("sssssi",$nom,$ape,$u,$h,$rol,$aidVal); $s->execute(); $mensaje="Maestro creado.";
      }

    } elseif (isset($_POST['editar_maestro'])) {
      $id=(int)$_POST['id'];
      $nom = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100);
      $ape = mb_substr(trim($_POST['apellido'] ?? ''), 0, 100);
      $aid = (int)($_POST['asignatura_id'] ?? 0);
      $s=$conn->prepare("SELECT rol FROM maestros WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $m=$s->get_result()->fetch_assoc();
      if (!$m) $error = "Maestro no válido.";
      elseif ($nom === '' || $ape === '') $error = "Nombre y apellido son obligatorios.";
      elseif ($aid > 0 && !asignatura_existe($conn,$aid)) $error = "Asignatura no válida.";
      elseif ($m['rol']==='docente' && $aid <= 0) $error = "Un docente debe tener asignatura.";
      else {
        $aidVal = $aid > 0 ? $aid : null;
        $s=$conn->prepare("UPDATE maestros SET nombre=?, apellido=?, asignatura_id=? WHERE id=?");
        $s->bind_param("ssii",$nom,$ape,$aidVal,$id); $s->execute(); $mensaje="Maestro actualizado."; $ed = 0;
      }

    } elseif (isset($_POST['clave'])) {
      $id=(int)$_POST['id']; $p=$_POST['password'];
      if (strlen($p)<6) $error="La contraseña debe tener al menos 6 caracteres.";
      else { $h=password_hash($p,PASSWORD_DEFAULT); $s=$conn->prepare("UPDATE maestros SET password=? WHERE id=?"); $s->bind_param("si",$h,$id); $s->execute(); $mensaje="Contraseña actualizada."; }

    } elseif (isset($_POST['borrar'])) {
      $id=(int)$_POST['id'];
      $s=$conn->prepare("SELECT usuario FROM maestros WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $r=$s->get_result()->fetch_assoc();
      if (($r['usuario']??'')===$_SESSION['maestro']) $error="No puedes eliminar tu propio usuario.";
      else { $s=$conn->prepare("DELETE FROM maestros WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $mensaje="Maestro eliminado (sus opciones de canje se eliminan; el histórico y las metas se conservan)."; }
    }
  } catch (mysqli_sql_exception $e) {
    $error = ((int)$e->getCode() === 1062) ? "Ese usuario o nombre ya existe." : "No se pudo completar la operación.";
  }
}

$asignaturas = $conn->query("SELECT ag.id, ag.nombre, (SELECT COUNT(*) FROM maestros WHERE asignatura_id=ag.id) tot FROM asignaturas ag ORDER BY ag.nombre")->fetch_all(MYSQLI_ASSOC);
$m = $conn->query("SELECT m.id, m.nombre, m.apellido, m.usuario, m.rol, m.asignatura_id, ag.nombre asignatura
                   FROM maestros m LEFT JOIN asignaturas ag ON ag.id=m.asignatura_id
                   ORDER BY m.rol DESC, m.apellido, m.nombre, m.usuario")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head><title>Maestros</title><?php include 'head.php'; ?>
<style>
  .tabla-maestros thead th{background:#e8f2e4;color:#38452f;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #cfe0c8;white-space:nowrap;padding:.65rem .75rem}
  .tabla-maestros td{padding:.55rem .75rem}
  .tabla-maestros .fila-edicion td{background:#fff8e6}
</style></head>
<body class="bg-light"><?php include 'menu.php'; ?>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>

  <div class="row">
    <div class="col-lg-7 mb-3"><div class="card p-4 shadow-sm h-100"><h4 class="text-primary mb-3">Nuevo maestro</h4>
      <form method="POST" class="row g-2" autocomplete="off">
        <div class="col-md-6"><input name="nombre" class="form-control" placeholder="Nombre" maxlength="100" required></div>
        <div class="col-md-6"><input name="apellido" class="form-control" placeholder="Apellido" maxlength="100" required></div>
        <div class="col-md-6"><input name="usuario" class="form-control" placeholder="Usuario" maxlength="50" autocomplete="off" required></div>
        <div class="col-md-6"><input type="password" name="password" class="form-control" placeholder="Contraseña (mín. 6)" autocomplete="new-password" required></div>
        <div class="col-md-6"><select name="asignatura_id" class="form-select">
          <option value="">Elige una asignatura...</option>
          <?php foreach($asignaturas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= h($a['nombre']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><select name="rol" class="form-select"><option value="docente">Docente</option><option value="admin">Administrador</option></select></div>
        <div class="col-md-3"><button name="crear" class="btn btn-primary w-100">Crear</button></div>
      </form>
      <?php if(!$asignaturas): ?><small class="text-danger d-block mt-2">Primero crea una asignatura (cuadro de la derecha).</small><?php endif; ?>
      <small class="text-muted d-block mt-2">La asignatura es obligatoria para docentes; para administradores es opcional.</small>
    </div></div>

    <div class="col-lg-5 mb-3"><div class="card p-4 shadow-sm h-100"><h4 class="text-primary mb-3">Asignaturas</h4>
      <form method="POST" class="d-flex gap-2 mb-3">
        <input type="text" name="nombre_asignatura" class="form-control" required maxlength="100" placeholder="Ej: Lenguaje">
        <button name="crear_asignatura" class="btn btn-primary">Agregar</button>
      </form>
      <?php foreach($asignaturas as $a): ?>
        <?php if($eda==$a['id']): ?>
        <form method="POST" class="d-flex gap-2 border-top py-2">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <input type="text" name="nombre_asignatura" class="form-control form-control-sm" value="<?= h($a['nombre']) ?>" maxlength="100" required>
          <button name="editar_asignatura" class="btn btn-sm btn-primary">Guardar</button>
          <a href="maestros.php" class="btn btn-sm btn-light">Cancelar</a>
        </form>
        <?php else: ?>
        <div class="d-flex justify-content-between align-items-center border-top py-2">
          <div><?= h($a['nombre']) ?> <small class="text-muted">(<?= (int)$a['tot'] ?> maestro<?= $a['tot']==1?'':'s' ?>)</small></div>
          <div class="d-flex gap-1">
            <a href="?editar_asig=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
            <form method="POST" onsubmit="return confirm('¿Eliminar la asignatura?')"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="borrar_asignatura" class="btn btn-sm btn-outline-danger">✕</button></form>
          </div>
        </div>
        <?php endif; ?>
      <?php endforeach; if(!$asignaturas) echo "<p class='text-muted small mb-0'>Aún no hay asignaturas.</p>"; ?>
    </div></div>
  </div>

  <div class="card shadow-sm">
    <div class="d-flex align-items-center gap-2 px-3 pt-3 pb-2">
      <h5 class="mb-0 text-primary">Maestros registrados</h5>
      <span class="badge bg-light text-dark border"><?= count($m) ?></span>
    </div>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 tabla-maestros">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Usuario</th>
          <th>Rol</th>
          <th>Asignatura</th>
          <th>Nueva contraseña</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($m as $x): $mid = (int)$x['id']; ?>
        <?php if($ed==$mid): ?>
        <tr class="fila-edicion">
          <td>
            <form id="fe-<?= $mid ?>" method="POST"><input type="hidden" name="id" value="<?= $mid ?>"></form>
            <div class="d-flex gap-1">
              <input form="fe-<?= $mid ?>" name="nombre" class="form-control form-control-sm" style="min-width:110px" value="<?= h($x['nombre']) ?>" placeholder="Nombre" maxlength="100" required>
              <input form="fe-<?= $mid ?>" name="apellido" class="form-control form-control-sm" style="min-width:110px" value="<?= h($x['apellido']) ?>" placeholder="Apellido" maxlength="100" required>
            </div>
          </td>
          <td class="text-muted">@<?= h($x['usuario']) ?></td>
          <td><span class="badge <?= $x['rol']=='admin'?'bg-dark':'bg-primary' ?>"><?= $x['rol']=='admin'?'Administrador':'Docente' ?></span></td>
          <td>
            <select form="fe-<?= $mid ?>" name="asignatura_id" class="form-select form-select-sm" style="min-width:150px">
              <option value="">Sin asignatura</option>
              <?php foreach($asignaturas as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $a['id']==$x['asignatura_id']?'selected':'' ?>><?= h($a['nombre']) ?></option><?php endforeach; ?>
            </select>
          </td>
          <td class="text-muted small">—</td>
          <td class="text-end text-nowrap">
            <button form="fe-<?= $mid ?>" name="editar_maestro" class="btn btn-sm btn-primary">Guardar</button>
            <a href="maestros.php" class="btn btn-sm btn-light">Cancelar</a>
          </td>
        </tr>
        <?php else: ?>
        <tr>
          <td class="fw-semibold"><?= h(trim($x['nombre'].' '.$x['apellido']) ?: $x['usuario']) ?></td>
          <td class="text-muted">@<?= h($x['usuario']) ?></td>
          <td><span class="badge <?= $x['rol']=='admin'?'bg-dark':'bg-primary' ?>"><?= $x['rol']=='admin'?'Administrador':'Docente' ?></span></td>
          <td>
            <?php if($x['asignatura']): ?><span class="badge bg-primary-subtle text-primary"><?= h($x['asignatura']) ?></span>
            <?php else: ?><span class="badge bg-warning-subtle text-warning-emphasis">Sin asignatura</span><?php endif; ?>
          </td>
          <td>
            <form method="POST" class="d-flex gap-1" style="min-width:240px">
              <input type="hidden" name="id" value="<?= $mid ?>">
              <input type="password" name="password" class="form-control form-control-sm" placeholder="Nueva clave (mín. 6)" autocomplete="new-password" minlength="6" required>
              <button name="clave" class="btn btn-sm btn-outline-primary">Cambiar</button>
            </form>
          </td>
          <td class="text-end text-nowrap">
            <a href="?editar=<?= $mid ?>" class="btn btn-sm btn-outline-primary">Editar</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este maestro? Sus opciones de canje se borran; el histórico y las metas se conservan.')"><input type="hidden" name="id" value="<?= $mid ?>"><button name="borrar" class="btn btn-sm btn-outline-danger">✕</button></form>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div><?php include 'footer.php'; ?>
</body></html>
