<?php
require_once 'conexion.php'; requiere_login();
$mensaje='';$error='';
function nuevo_qr(){ return bin2hex(random_bytes(6)); }
// Los cursos son compartidos por todos los docentes: basta con que el curso exista.
function curso_existe($conn,$cid){$s=$conn->prepare("SELECT id FROM cursos WHERE id=?"); $s->bind_param("i",$cid);$s->execute();
  return (bool)$s->get_result()->fetch_assoc();
}
function cupo($conn,$cid,$excluir=0){
  $s=$conn->prepare("SELECT COUNT(*) t FROM alumnos WHERE curso_id=? AND id<>?"); $s->bind_param("ii",$cid,$excluir);$s->execute();
  return $s->get_result()->fetch_assoc()['t'] < 50;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  try {
    if (isset($_POST['crear_curso'])) {
      $n = mb_substr(trim($_POST['nombre_curso'] ?? ''), 0, 100);
      if ($n === '') $error = "Escribe el nombre del curso.";
      else {
        $s=$conn->prepare("SELECT id FROM cursos WHERE nombre=?"); $s->bind_param("s",$n); $s->execute();
        if ($s->get_result()->fetch_assoc()) $error = "Ya existe un curso con ese nombre.";
        else { $s=$conn->prepare("INSERT INTO cursos (nombre) VALUES (?)"); $s->bind_param("s",$n); $s->execute(); $mensaje = "Curso creado."; }
      }

    } elseif (isset($_POST['crear_alumno'])) {
      $cid=(int)$_POST['curso_id']; $n=trim($_POST['nombre_alumno']); $uid=trim($_POST['nfc_uid']) ?: null; 
      $qr=nuevo_qr();$qr_apoderado=nuevo_qr(); // QR generado automáticamente para el apoderado
      
      if (!curso_existe($conn,$cid))$error = "Curso no válido.";
      elseif (!cupo($conn,$cid))$error = "El curso ya tiene el máximo de 50 alumnos.";
      else { 
        $s=$conn->prepare("INSERT INTO alumnos (curso_id,nombre,nfc_uid,qr_code,qr_apoderado) VALUES (?,?,?,?,?)");
        $s->bind_param("issss",$cid,$n,$uid,$qr,$qr_apoderado); $s->execute();$mensaje="Alumno registrado (QRs generados automáticamente)."; 
      }

    } elseif (isset($_POST['editar_alumno'])) {
      $id=(int)$_POST['id']; $cid=(int)$_POST['curso_id']; $n=trim($_POST['nombre_alumno']); $uid=trim($_POST['nfc_uid']) ?: null;
      $s=$conn->prepare("SELECT curso_id FROM alumnos WHERE id=?"); $s->bind_param("i",$id);$s->execute();
      $act=$s->get_result()->fetch_assoc();
      if (!$act || !curso_existe($conn,$cid))$error = "Alumno o curso no válido.";
      elseif (!cupo($conn,$cid,$id))$error = "El curso destino ya tiene 50 alumnos.";
      else {
        $s=$conn->prepare("UPDATE alumnos SET curso_id=?, nombre=?, nfc_uid=? WHERE id=?"); $s->bind_param("issi",$cid,$n,$uid,$id);$s->execute();
        if (!empty($_POST['regen_qr'])) { 
          $q=nuevo_qr();$q_apod=nuevo_qr(); 
          $s=$conn->prepare("UPDATE alumnos SET qr_code=?, qr_apoderado=? WHERE id=?"); 
          $s->bind_param("ssi",$q,$q_apod,$id);$s->execute(); 
        }
        $mensaje = "Alumno actualizado.";
      }

    } elseif (isset($_POST['borrar_alumno'])) {$id=(int)$_POST['id'];$s=$conn->prepare("SELECT curso_id FROM alumnos WHERE id=?"); $s->bind_param("i",$id);$s->execute(); $a=$s->get_result()->fetch_assoc();
      if (!$a)$error = "Alumno no válido.";
      else { $s=$conn->prepare("DELETE FROM alumnos WHERE id=?"); $s->bind_param("i",$id); $s->execute();$mensaje="Alumno eliminado."; }

    } elseif (isset($_POST['borrar_curso'])) {
      $id=(int)$_POST['id'];
      // Los cursos son compartidos y al borrarlos se pierden alumnos y puntos de todos los docentes: solo admin.
      if (!es_admin())$error = "Solo el administrador puede eliminar cursos.";
      elseif (!curso_existe($conn,$id))$error = "Curso no válido.";
      else { $s=$conn->prepare("DELETE FROM cursos WHERE id=?"); $s->bind_param("i",$id); $s->execute();$mensaje="Curso eliminado."; }
    }
  } catch (mysqli_sql_exception $e) {$error = "No se pudo guardar (¿tarjeta NFC ya asignada a otro alumno?)."; }
}

// --- Listados ---
$cursos = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM alumnos WHERE curso_id=c.id) tot FROM cursos c ORDER BY c.nombre")->fetch_all(MYSQLI_ASSOC);

$idsCursos = array_column($cursos,'id');$alumnos = [];
if ($idsCursos) {
  $ph = implode(',', array_fill(0,count($idsCursos),'?'));
  $s =$conn->prepare("SELECT * FROM alumnos WHERE curso_id IN ($ph) ORDER BY nombre");
  $s->bind_param(str_repeat('i',count($idsCursos)), ...$idsCursos);$s->execute();
  $alumnos =$s->get_result()->fetch_all(MYSQLI_ASSOC);
}

$ed = (int)($_GET['editar'] ?? 0);

// Detectar esquema y dominio base para construir la URL del apoderado
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host . '/reporte_apoderado.php?token=';
?>
<!DOCTYPE html><html lang="es"><head><title>Contenido</title><?php include 'head.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>

  <div id="statusNFC" class="alert alert-info py-2 mb-3 text-center fw-bold" style="display:none;"></div>

  <div class="row">
    <div class="col-md-6 mb-4"><div class="card p-4 shadow-sm h-100">
      <h4 class="text-primary mb-3">Agregar Curso</h4>
      <form method="POST">
        <input type="text" name="nombre_curso" class="form-control mb-3" required maxlength="100" placeholder="Ej: 1ro Básico A">
        <button name="crear_curso" class="btn btn-primary w-100">Crear Curso</button>
      </form>
      <small class="text-muted d-block mt-2">Los cursos son compartidos por todos los docentes.</small>
    </div></div>

    <div class="col-md-6 mb-4"><div class="card p-4 shadow-sm h-100">
      <h4 class="text-primary mb-3">Registrar Alumno</h4>
      <form method="POST">
        <select name="curso_id" class="form-select mb-3" required>
          <option value="" disabled selected>Elige un curso...</option>
          <?php foreach($cursos as$c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?> (<?= (int)$c['tot'] ?>/50)</option><?php endforeach; ?>
        </select>
        <input type="text" name="nombre_alumno" class="form-control mb-2" required placeholder="Nombre del alumno" <?= $cursos?'':'disabled' ?>>
        <div class="input-group mb-1">
          <input type="text" name="nfc_uid" id="nfc_uid" class="form-control" placeholder="Tarjeta NFC (opcional)">
          <button class="btn btn-dark" type="button" onclick="leerNFC('nfc_uid')">Escanear NFC</button>
        </div>
        <small class="text-muted d-block mb-3">Se generarán automáticamente los códigos QR del alumno y del apoderado.</small>
        <button name="crear_alumno" class="btn btn-success w-100" <?= $cursos?'':'disabled' ?>>Guardar Alumno</button>
      </form>
      <?php if(!$cursos): ?><small class="text-danger d-block mt-2">Primero crea un curso.</small><?php endif; ?>
    </div></div>
  </div>

  <?php foreach($cursos as$c): ?>
  <div class="card p-3 shadow-sm mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="mb-0"><?= h($c['nombre']) ?>
        <small class="text-muted">(<?= (int)$c['tot'] ?>/50)</small></h5>
      <div class="d-flex gap-2">
        <a href="qr.php?curso=<?= (int)$c['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">🖨️ Imprimir QRs</a>
        <?php if(es_admin()): ?>
        <form method="POST" onsubmit="return confirm('¿Eliminar curso, sus alumnos y todos sus puntos?')">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button name="borrar_curso" class="btn btn-sm btn-outline-danger">Eliminar curso</button></form>
        <?php endif; ?>
      </div>
    </div>
    <div class="mt-2">
    <?php foreach($alumnos as$a): if($a['curso_id']==$c['id']): ?>
      <?php if($ed==$a['id']): ?>
      <form method="POST" class="border rounded p-2 mb-2">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <input type="text" name="nombre_alumno" class="form-control mb-2" value="<?= h($a['nombre']) ?>" required>
        <select name="curso_id" class="form-select mb-2"><?php foreach($cursos as$c2): ?><option value="<?= (int)$c2['id'] ?>" <?= $c2['id']==$a['curso_id']?'selected':'' ?>><?= h($c2['nombre']) ?></option><?php endforeach; ?></select>
        <div class="input-group mb-2"><input type="text" name="nfc_uid" id="nfc_ed" class="form-control" value="<?= h($a['nfc_uid']) ?>" placeholder="Sin tarjeta NFC">
          <button class="btn btn-dark" type="button" onclick="leerNFC('nfc_ed')">Escanear NFC</button></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="regen_qr" value="1" id="rq"><label class="form-check-label" for="rq">Generar nuevos QRs (Alumno y Apoderado)</label></div>
        <button name="editar_alumno" class="btn btn-sm btn-primary">Guardar</button> <a href="contenido.php" class="btn btn-sm btn-light">Cancelar</a>
      </form>
      <?php else: ?>
      <div class="d-flex justify-content-between align-items-center border-top py-2 flex-wrap gap-2">
        <div><?= h($a['nombre']) ?>
          <span class="badge <?= $a['nfc_uid']?'bg-success':'bg-secondary' ?>">NFC <?= $a['nfc_uid']?'✓':'—' ?></span>
          <span class="badge bg-info text-dark">QR Alumno ✓</span>
          <span class="badge bg-warning text-dark">QR Apoderado ✓</span></div>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-secondary" onclick="verQR('<?= h($a['qr_code']) ?>','Alumno: <?= h(addslashes($a['nombre'])) ?>')">QR Alumno</button>
          <button class="btn btn-sm btn-outline-warning text-dark" onclick="verQR('<?= $baseUrl . h($a['qr_apoderado']) ?>','Apoderado de: <?= h(addslashes($a['nombre'])) ?>')">QR Apoderado</button>
          <a href="?editar=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="POST" onsubmit="return confirm('¿Eliminar alumno?')"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="borrar_alumno" class="btn btn-sm btn-outline-danger">✕</button></form>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="modal fade" id="mqr" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content text-center p-3">
  <h6 id="qrNombre"></h6><div id="qrBox" class="d-flex justify-content-center bg-white p-2"></div>
  <button class="btn btn-light mt-2" data-bs-dismiss="modal">Cerrar</button></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let ndef = null, destino = null;

function mostrarStatus(msj, tipo = 'info') {
  const box = document.getElementById('statusNFC');
  box.className = `alert alert-${tipo} py-2 mb-3 text-center fw-bold`;
  box.innerText = msj;
  box.style.display = 'block';
}

function extraerCodigoNFC(event) {
  if (event.serialNumber) return event.serialNumber.replace(/:/g, '').toUpperCase();
  if (event.message && event.message.records) {
    for (const record of event.message.records) {
      if (record.recordType === "text" || record.recordType === "url") {
        return new TextDecoder().decode(record.data).trim();
      }
    }
  }
  return null;
}

async function leerNFC(idCampo){
  destino = idCampo;
  if (!("NDEFReader" in window)) { mostrarStatus("⚠️ Web NFC no disponible.", "danger"); return; }
  try {
    if (!ndef) { 
      ndef = new NDEFReader();
      ndef.addEventListener("reading", (event) => { 
        const codigo = extraerCodigoNFC(event);
        if (codigo) { document.getElementById(destino).value = codigo; mostrarStatus("✅ Tarjeta asignada.", "success"); }
      });
    }
    await ndef.scan(); mostrarStatus("🛜 Acerca la tarjeta...", "info");
  } catch(e){ mostrarStatus("⚠️ Error NFC: " + (e.message || e), "danger"); }
}

function verQR(textoQR, titulo){
  document.getElementById('qrNombre').innerText = titulo;
  const b = document.getElementById('qrBox'); b.innerHTML = '';
  new QRCode(b, {text: textoQR, width: 200, height: 200});
  new bootstrap.Modal(document.getElementById('mqr')).show();
}
</script><?php include 'footer.php'; ?>
</body></html>