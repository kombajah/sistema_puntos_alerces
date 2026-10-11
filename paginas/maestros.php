<?php
require_once 'conexion.php'; requiere_login();
if (!es_admin()) { http_response_code(403); die("Solo el administrador puede gestionar maestros."); }
$mensaje=''; $error=''; $nuevoId = 0; $abrirAsig = false;
// Las acciones de edición se envían por fetch (sin recargar); se responde JSON.
$esAjax = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

function asignatura_existe($conn,$id){
  if ($id <= 0) return false;
  $s=$conn->prepare("SELECT id FROM asignaturas WHERE id=?"); $s->bind_param("i",$id); $s->execute();
  return (bool)$s->get_result()->fetch_assoc();
}
function nombre_asignatura_repetido($conn,$nombre,$excluir=0){
  $s=$conn->prepare("SELECT id FROM asignaturas WHERE nombre=? AND id<>?"); $s->bind_param("si",$nombre,$excluir); $s->execute();
  return (bool)$s->get_result()->fetch_assoc();
}

// ---------- TARJETAS DE ACCESO DE DOCENTES (vista de impresión) ----------
// ?tarjetas=todos  -> todos los docentes   |   ?tarjetas=ID -> un docente en particular
if (isset($_GET['tarjetas'])) {
  $sel = $_GET['tarjetas'];
  $sql = "SELECT id, nombre, apellido, usuario, clave_texto FROM maestros WHERE rol='docente'";
  if ($sel === 'todos') { $s = $conn->prepare($sql." ORDER BY apellido, nombre, usuario"); }
  else { $sid = (int)$sel; $s = $conn->prepare($sql." AND id=?"); $s->bind_param("i", $sid); }
  $s->execute();
  $docs = $s->get_result()->fetch_all(MYSQLI_ASSOC);
  $conClave = array_values(array_filter($docs, fn($d) => (string)$d['clave_texto'] !== ''));
  $sinClave = array_values(array_filter($docs, fn($d) => (string)$d['clave_texto'] === ''));

  // La tarjeta lleva el QR de acceso al sistema (misma URL del sitio desde donde se genera)
  $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https://" : "http://";
  $urlSitio = $protocol . ($_SERVER['HTTP_HOST'] ?? '') . '/';
  $mascota = 'assets/alercin_mascota.png';
  ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Tarjetas de acceso docentes</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
@page{size:A4;margin:8mm}
*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{font-family:'Poppins','Quicksand',system-ui,sans-serif;background:#eef3ea;color:#222;margin:16px}
.bar{margin-bottom:14px;font-size:13px}
.bar a,.bar button{display:inline-block;margin:2px 4px 2px 0;padding:5px 10px;border:1px solid #6fb04f;border-radius:6px;background:#fff;color:#38452f;text-decoration:none;font-size:13px;cursor:pointer}
.aviso{background:#fff3cd;border:1px solid #ffe08a;border-radius:6px;padding:8px 12px;margin:8px 0;font-size:13px}

.pagina{display:grid;grid-template-columns:190mm;gap:5mm;justify-content:center;margin-bottom:6mm;page-break-after:always;break-after:page}
.pagina:last-child{page-break-after:auto;break-after:auto}

.tarjeta{display:flex;width:190mm;height:58mm;background:#fff;border:.3mm dashed #9aa79a;border-radius:4mm;overflow:hidden;break-inside:avoid}
.izq{position:relative;width:50%;background:linear-gradient(135deg,#eef5ea,#e1eedb);border-right:.3mm dashed #9aa79a;display:flex;align-items:flex-end}
.izq .marca{position:absolute;left:5mm;top:5mm;display:flex;align-items:center;gap:2mm;font-weight:700;font-size:7pt;letter-spacing:.3mm;color:#2f6b2f}
.izq .marca img{width:7mm;height:7mm;object-fit:contain}
.izq .mascota{height:50mm;width:36mm;object-fit:contain;margin-left:2mm}
.izq .txt{position:absolute;left:41mm;top:12mm;right:3mm}
.izq .hola{font-size:9pt;color:#5d6b53}
.izq .nombre{font-size:21pt;font-weight:800;color:#2a7a35;line-height:1.05}
.izq .sub{font-size:7.5pt;color:#6b786a;margin-top:2mm;line-height:1.3}
.izq .pill{display:inline-block;margin-top:3.5mm;border:.3mm solid #7dbb55;background:#fff;color:#2a7a35;border-radius:5mm;padding:1.2mm 3mm;font-size:6.5pt;font-weight:700}

.der{position:relative;width:50%;padding:4.5mm 5mm 3mm 5mm;display:flex;flex-direction:column}
.der .top{display:flex;justify-content:space-between;align-items:center}
.der .tag{background:#dff3e4;color:#1e8a3c;font-size:5.8pt;font-weight:800;letter-spacing:.3mm;border-radius:3mm;padding:1mm 2.5mm}
.der .esc{font-size:7pt;color:#6b6b6b}
.der .medio{flex:1;display:flex;align-items:center;gap:3.5mm}
.der .logo{width:15mm;height:15mm;border:.3mm solid #e1e1e1;border-radius:2.5mm;padding:1.2mm;object-fit:contain;flex:none}
.der .datos{flex:1;min-width:0}
.der .datos small{display:block;font-size:4.6pt;font-weight:700;letter-spacing:.3mm;color:#777;margin-top:1.3mm}
.der .datos .doc{font-size:11pt;font-weight:700;line-height:1.1;color:#1a1a1a}
.der .datos .usr{font-family:'Courier New',monospace;font-size:10pt;font-weight:700;color:#1e7a35}
.der .datos .clv{font-family:'Courier New',monospace;font-size:10pt;font-weight:700;color:#d9531e;word-break:break-all}
.der .qrbox{text-align:center;flex:none}
.der .qrbox .qr{border:.3mm solid #e1e1e1;border-radius:2.5mm;padding:1.2mm;line-height:0;display:inline-block}
.der .qrbox .qr img,.der .qrbox .qr canvas{width:19mm!important;height:19mm!important}
.der .qrbox small{display:block;font-size:4.6pt;color:#888;margin-top:.8mm;line-height:1.15}
.der .pie{border-top:.3mm solid #e3e3e3;text-align:center;padding-top:1.5mm}
.der .pie .url{font-size:6pt;color:#555}
.der .pie .nota{font-size:4.8pt;color:#999;margin-top:.5mm}

@media print{.np{display:none!important}body{margin:0;background:#fff}}
</style></head><body>

<div class="bar np">
  <b>Tarjetas de acceso docentes (<?= count($conClave) ?>)</b> &nbsp;
  <button onclick="print()">🖨️ Imprimir / Guardar como PDF</button>
  <a href="maestros.php">← Volver a Maestros</a>
  <br><small>Hoja A4 con 4 tarjetas. Imprime al 100 % (sin “ajustar a página”).</small>
  <?php if($sinClave): ?>
  <div class="aviso">⚠️ No se generó tarjeta para <?= count($sinClave) ?> docente(s) porque no tienen clave guardada:
    <?= h(implode(', ', array_map(fn($d) => trim($d['nombre'].' '.$d['apellido']) ?: $d['usuario'], $sinClave))) ?>.
    Asígnales una nueva contraseña desde «Maestros registrados» (campo «Nueva contraseña») y vuelve a generar.</div>
  <?php endif; ?>
</div>

<?php
if (!$conClave && !$sinClave) echo "<p>No hay docentes para generar tarjetas.</p>";
$idx = 0;
foreach (array_chunk($conClave, 4) as $grupo): ?>
<div class="pagina">
  <?php foreach ($grupo as $d): $nom = trim($d['nombre'].' '.$d['apellido']) ?: $d['usuario']; ?>
  <div class="tarjeta">
    <div class="izq">
      <div class="marca"><img src="assets/logo_alerces.png" alt="">LOS ALERCES</div>
      <img class="mascota" src="<?= h($mascota) ?>" alt="">
      <div class="txt">
        <div class="hola">¡Hola! Soy</div>
        <div class="nombre">Alercín</div>
        <div class="sub">Sistema de Puntos<br>Escuela Los Alerces</div>
        <span class="pill">Tarjeta de acceso del docente</span>
      </div>
    </div>
    <div class="der">
      <div class="top"><span class="tag">ACCESO DOCENTE</span><span class="esc">Escuela Los Alerces</span></div>
      <div class="medio">
        <img class="logo" src="assets/logo_alerces.png" alt="">
        <div class="datos">
          <small>DOCENTE</small><div class="doc"><?= h($nom) ?></div>
          <small>USUARIO</small><div class="usr"><?= h($d['usuario']) ?></div>
          <small>CLAVE</small><div class="clv"><?= h($d['clave_texto']) ?></div>
        </div>
        <div class="qrbox"><div class="qr"><div id="q<?= $idx ?>"></div></div><small>Escanea para<br>ingresar</small></div>
      </div>
      <div class="pie"><div class="url"><?= h($urlSitio) ?></div><div class="nota">Tarjeta personal. Guárdala en un lugar seguro y no compartas tu clave.</div></div>
    </div>
  </div>
  <?php $idx++; endforeach; ?>
</div>
<?php endforeach; ?>

<script>
const URL_SITIO = <?= json_encode($urlSitio, JSON_HEX_TAG) ?>;
for (let i = 0; i < <?= (int)$idx ?>; i++) {
  const el = document.getElementById('q' + i);
  if (el) new QRCode(el, {text: URL_SITIO, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M});
}
</script>
</body></html>
<?php
  exit;
}

if ($_SERVER["REQUEST_METHOD"]=="POST") {
  try {
    // ---------- MANTENEDOR DE ASIGNATURAS (movido desde Contenido) ----------
    if (isset($_POST['crear_asignatura'])) {
      $abrirAsig = true;
      $n = mb_substr(trim($_POST['nombre_asignatura'] ?? ''), 0, 100);
      if ($n === '') $error = "Escribe el nombre de la asignatura.";
      elseif (nombre_asignatura_repetido($conn,$n)) $error = "Esa asignatura ya existe.";
      else { $s=$conn->prepare("INSERT INTO asignaturas (nombre) VALUES (?)"); $s->bind_param("s",$n); $s->execute(); $mensaje = "Asignatura creada."; }

    } elseif (isset($_POST['editar_asignatura'])) {
      $abrirAsig = true;
      $id=(int)$_POST['id']; $n = mb_substr(trim($_POST['nombre_asignatura'] ?? ''), 0, 100);
      if ($n === '') $error = "Escribe el nombre de la asignatura.";
      elseif (!asignatura_existe($conn,$id)) $error = "Asignatura no válida.";
      elseif (nombre_asignatura_repetido($conn,$n,$id)) $error = "Ya existe otra asignatura con ese nombre.";
      else { $s=$conn->prepare("UPDATE asignaturas SET nombre=? WHERE id=?"); $s->bind_param("si",$n,$id); $s->execute(); $mensaje = "Asignatura actualizada."; }

    } elseif (isset($_POST['borrar_asignatura'])) {
      $abrirAsig = true;
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
        // clave_texto: solo para docentes, se usa únicamente para imprimir su tarjeta de acceso.
        $claveTxt = ($rol==='docente') ? $p : null;
        $s=$conn->prepare("INSERT INTO maestros (nombre,apellido,usuario,password,clave_texto,rol,asignatura_id) VALUES (?,?,?,?,?,?,?)");
        $s->bind_param("ssssssi",$nom,$ape,$u,$h,$claveTxt,$rol,$aidVal); $s->execute(); $nuevoId = (int)$conn->insert_id; $mensaje="Maestro creado.";
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
        $s->bind_param("ssii",$nom,$ape,$aidVal,$id); $s->execute(); $mensaje="Maestro actualizado.";
      }

    } elseif (isset($_POST['clave'])) {
      $id=(int)$_POST['id']; $p=$_POST['password'];
      if (strlen($p)<6) $error="La contraseña debe tener al menos 6 caracteres.";
      else { $h=password_hash($p,PASSWORD_DEFAULT);
        // Se mantiene sincronizada la clave de la tarjeta (solo docentes; administradores no guardan clave en texto).
        $s=$conn->prepare("UPDATE maestros SET password=?, clave_texto=IF(rol='docente', ?, NULL) WHERE id=?"); $s->bind_param("ssi",$h,$p,$id); $s->execute(); $mensaje="Contraseña actualizada."; }

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

if ($esAjax) {
  header('Content-Type: application/json; charset=utf-8');
  $out = ['ok' => ($error === ''), 'mensaje' => $mensaje, 'error' => $error];
  if ($out['ok'] && isset($_POST['editar_maestro'])) {
    $s = $conn->prepare("SELECT m.nombre, m.apellido, m.usuario, m.asignatura_id, ag.nombre asignatura FROM maestros m LEFT JOIN asignaturas ag ON ag.id=m.asignatura_id WHERE m.id=?");
    $idr = (int)$_POST['id']; $s->bind_param("i", $idr); $s->execute();
    $out['maestro'] = $s->get_result()->fetch_assoc();
  }
  if ($out['ok'] && isset($_POST['clave'])) {
    $s = $conn->prepare("SELECT (clave_texto IS NOT NULL AND clave_texto <> '') tiene_clave FROM maestros WHERE id=?");
    $idr = (int)$_POST['id']; $s->bind_param("i", $idr); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $out['tiene_clave'] = !empty($r['tiene_clave']);
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}

$asignaturas = $conn->query("SELECT ag.id, ag.nombre, (SELECT COUNT(*) FROM maestros WHERE asignatura_id=ag.id) tot FROM asignaturas ag ORDER BY ag.nombre")->fetch_all(MYSQLI_ASSOC);
$m = $conn->query("SELECT m.id, m.nombre, m.apellido, m.usuario, m.rol, m.asignatura_id, ag.nombre asignatura,
                          (m.clave_texto IS NOT NULL AND m.clave_texto <> '') tiene_clave
                   FROM maestros m LEFT JOIN asignaturas ag ON ag.id=m.asignatura_id
                   ORDER BY m.rol DESC, m.apellido, m.nombre, m.usuario")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head><title>Maestros</title><?php include 'head.php'; ?>
<style>
  /* ---- Formulario compacto ---- */
  .card-nuevo{padding:.9rem 1rem}
  .card-nuevo h5{font-size:1.05rem}

  /* ---- Herramientas (tarjetas / asignaturas) ---- */
  details.herr{background:#fff;border:1px solid var(--forest-line);border-radius:16px;box-shadow:0 .125rem .25rem rgba(0,0,0,.075)}
  details.herr>summary{list-style:none;cursor:pointer;padding:.7rem 1rem;display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--forest-primary-dark)}
  details.herr>summary::-webkit-details-marker{display:none}
  details.herr>summary .chev{margin-left:auto;transition:transform .15s;font-size:.8rem;opacity:.7}
  details.herr[open]>summary .chev{transform:rotate(180deg)}
  details.herr .herr-body{padding:0 1rem 1rem}
  .lista-asig{max-height:260px;overflow-y:auto;border-top:1px solid var(--forest-line)}
  .lista-asig .item{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.35rem 0;border-bottom:1px solid #eef3e9}
  .lista-asig .item:last-child{border-bottom:0}
  .item .e{display:none}
  .item.editando .e{display:flex}
  .item.editando .v{display:none}

  /* ---- Tabla con scroll propio ---- */
  .tabla-scroll{position:relative;overflow:auto;max-height:68vh;min-height:320px;border-top:1px solid var(--forest-line);border-radius:0 0 16px 16px;scroll-behavior:smooth}
  .tabla-maestros{min-width:980px}
  .tabla-maestros thead th{position:sticky;top:0;z-index:3;background:#e8f2e4;color:#38452f;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #cfe0c8;white-space:nowrap;padding:.6rem .75rem}
  .tabla-maestros td{padding:.45rem .75rem;background:#fff}
  .tabla-maestros tbody tr:hover td{background:#f5f9f2}
  /* la columna Nombre queda fija al desplazar horizontalmente */
  .tabla-maestros th.col-nombre,.tabla-maestros td.col-nombre{position:sticky;left:0;box-shadow:1px 0 0 #e3ebdc}
  .tabla-maestros th.col-nombre{z-index:4}
  .tabla-maestros td.col-nombre{z-index:2}
  .tabla-maestros tr.editando td{background:#fff8e6!important}
  .tabla-maestros tr.oculta{display:none}
  .tabla-maestros tr.flash td{animation:flashFila 1.6s ease-out}
  @keyframes flashFila{0%,30%{background:#d8efcf!important}100%{background:#fff}}
  .tabla-maestros .e{display:none}
  .tabla-maestros tr.editando .e{display:flex}
  .tabla-maestros tr.editando select.e{display:block}
  .tabla-maestros tr.editando .v{display:none!important}
  .sin-resultados{display:none;padding:1.5rem;text-align:center;color:#7c8b72}

  /* ---- Volver arriba (dentro de la tabla) ---- */
  .wrap-tabla{position:relative}
  #btnArribaTabla{position:absolute;right:14px;bottom:14px;z-index:6;display:none;align-items:center;gap:6px;padding:7px 14px;border:0;border-radius:999px;background:#5fa43c;color:#fff;font-weight:700;font-size:.85rem;box-shadow:0 2px 8px rgba(0,0,0,.25);cursor:pointer}
  #btnArribaTabla:hover{background:#4d8c2f}
  #btnArribaTabla.visible{display:inline-flex}

  /* ---- Avisos flotantes ---- */
  #toasts{position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:3000;display:flex;flex-direction:column;gap:6px;align-items:center;pointer-events:none}
  .toast-msg{pointer-events:auto;padding:8px 16px;border-radius:10px;font-size:.9rem;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.2);animation:entra .2s ease-out}
  .toast-msg.ok{background:#e3f4dc;color:#2f5d2a;border:1px solid #b9dcae}
  .toast-msg.err{background:#fde8e8;color:#8a2a2a;border:1px solid #f1bcbc}
  @keyframes entra{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
  @media (prefers-reduced-motion:reduce){.toast-msg,.tabla-maestros tr.flash td{animation:none}.tabla-scroll{scroll-behavior:auto}}
</style></head>
<body class="bg-light"><?php include 'menu.php'; ?>
<div id="toasts" aria-live="polite"></div>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success py-2'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger py-2'>".h($error)."</div>"; ?>

  <!-- ===== NUEVO MAESTRO (una sola fila en pantallas anchas) ===== -->
  <div class="card card-nuevo shadow-sm mb-3">
    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
      <h5 class="text-primary mb-0">Nuevo maestro</h5>
      <small class="text-muted">La asignatura es obligatoria para docentes; para administradores es opcional.</small>
    </div>
    <form method="POST" class="row g-2" autocomplete="off">
      <div class="col-6 col-md-3 col-xl"><input name="nombre" class="form-control form-control-sm" placeholder="Nombre" maxlength="100" required></div>
      <div class="col-6 col-md-3 col-xl"><input name="apellido" class="form-control form-control-sm" placeholder="Apellido" maxlength="100" required></div>
      <div class="col-6 col-md-3 col-xl"><input name="usuario" class="form-control form-control-sm" placeholder="Usuario" maxlength="50" autocomplete="off" required></div>
      <div class="col-6 col-md-3 col-xl"><input type="password" name="password" class="form-control form-control-sm" placeholder="Contraseña (mín. 6)" autocomplete="new-password" required></div>
      <div class="col-6 col-md-4 col-xl"><select name="asignatura_id" class="form-select form-select-sm sel-asig">
        <option value="">Asignatura...</option>
        <?php foreach($asignaturas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= h($a['nombre']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="col-6 col-md-4 col-xl-auto"><select name="rol" class="form-select form-select-sm"><option value="docente">Docente</option><option value="admin">Administrador</option></select></div>
      <div class="col-12 col-md-4 col-xl-auto"><button name="crear" class="btn btn-sm btn-primary w-100 px-4">Crear</button></div>
    </form>
    <?php if(!$asignaturas): ?><small class="text-danger d-block mt-2">Primero crea una asignatura (panel «Asignaturas» de abajo).</small><?php endif; ?>
  </div>

  <!-- ===== HERRAMIENTAS: tarjetas y asignaturas (plegables) ===== -->
  <div class="row g-3 mb-3 align-items-start">
    <div class="col-lg-6">
      <details class="herr">
        <summary>🪪 Tarjetas de acceso docentes <span class="chev">▼</span></summary>
        <div class="herr-body">
          <p class="text-muted small mb-2">Genera las tarjetas con el usuario y la clave de cada docente. Se abren en una pestaña nueva lista para imprimir.</p>
          <form method="GET" action="maestros.php" target="_blank" class="d-flex flex-wrap gap-2">
            <select name="tarjetas" class="form-select form-select-sm flex-grow-1" style="min-width:200px;width:auto" aria-label="Docente">
              <option value="todos">Todos los docentes</option>
              <?php foreach($m as $x): if($x['rol']!=='docente') continue; ?>
              <option value="<?= (int)$x['id'] ?>"><?= h(trim($x['nombre'].' '.$x['apellido']) ?: $x['usuario']) ?> (@<?= h($x['usuario']) ?>)<?= $x['tiene_clave'] ? '' : ' — sin clave guardada' ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary px-3">Generar tarjetas</button>
          </form>
          <?php $sinClaveN = count(array_filter($m, fn($x) => $x['rol']==='docente' && !$x['tiene_clave'])); if($sinClaveN): ?>
          <small class="text-warning-emphasis d-block mt-2">⚠️ <?= $sinClaveN ?> docente(s) no tienen clave guardada: no saldrán en las tarjetas hasta que les asignes una nueva contraseña en la tabla.</small>
          <?php endif; ?>
        </div>
      </details>
    </div>

    <div class="col-lg-6">
      <details class="herr" id="panelAsig" <?= $abrirAsig ? 'open' : '' ?>>
        <summary>📚 Asignaturas <span class="badge bg-light text-dark border"><?= count($asignaturas) ?></span> <span class="chev">▼</span></summary>
        <div class="herr-body">
          <form method="POST" class="d-flex gap-2 mb-2">
            <input type="text" name="nombre_asignatura" class="form-control form-control-sm" required maxlength="100" placeholder="Ej: Lenguaje">
            <button name="crear_asignatura" class="btn btn-sm btn-primary">Agregar</button>
          </form>
          <div class="lista-asig">
          <?php foreach($asignaturas as $a): $aid = (int)$a['id']; ?>
            <div class="item" id="asig-<?= $aid ?>" data-id="<?= $aid ?>">
              <form method="POST" data-ajax class="e flex-grow-1 gap-1">
                <input type="hidden" name="id" value="<?= $aid ?>">
                <input type="text" name="nombre_asignatura" class="form-control form-control-sm" value="<?= h($a['nombre']) ?>" maxlength="100" required>
                <button name="editar_asignatura" class="btn btn-sm btn-primary">Guardar</button>
                <button type="button" class="btn btn-sm btn-light" data-accion="cancelar-asig">Cancelar</button>
              </form>
              <div class="v"><span class="nom-asig"><?= h($a['nombre']) ?></span> <small class="text-muted">(<?= (int)$a['tot'] ?> maestro<?= $a['tot']==1?'':'s' ?>)</small></div>
              <div class="v d-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary" data-accion="editar-asig">Editar</button>
                <form method="POST" onsubmit="return confirm('¿Eliminar la asignatura?')"><input type="hidden" name="id" value="<?= $aid ?>"><button name="borrar_asignatura" class="btn btn-sm btn-outline-danger" aria-label="Eliminar asignatura">✕</button></form>
              </div>
            </div>
          <?php endforeach; if(!$asignaturas) echo "<p class='text-muted small mb-0 pt-2'>Aún no hay asignaturas.</p>"; ?>
          </div>
        </div>
      </details>
    </div>
  </div>

  <!-- ===== MAESTROS REGISTRADOS ===== -->
  <div class="card shadow-sm mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2">
      <h5 class="mb-0 text-primary">Maestros registrados</h5>
      <span class="badge bg-light text-dark border" id="cuenta"><?= count($m) ?></span>
      <div class="ms-auto d-flex flex-wrap gap-2">
        <input type="search" id="filtroTexto" class="form-control form-control-sm" style="width:230px" placeholder="🔎 Buscar nombre, usuario o asignatura" aria-label="Buscar maestro">
        <select id="filtroRol" class="form-select form-select-sm" style="width:auto" aria-label="Filtrar por rol">
          <option value="">Todos los roles</option><option value="docente">Docentes</option><option value="admin">Administradores</option>
        </select>
      </div>
    </div>

    <div class="wrap-tabla">
      <div class="tabla-scroll" id="tablaScroll" tabindex="0">
        <table class="table align-middle mb-0 tabla-maestros">
          <thead>
            <tr>
              <th class="col-nombre">Nombre</th>
              <th>Usuario</th>
              <th>Rol</th>
              <th>Asignatura</th>
              <th>Nueva contraseña</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody id="cuerpo">
          <?php foreach($m as $x): $mid = (int)$x['id'];
            $nomCompleto = trim($x['nombre'].' '.$x['apellido']) ?: $x['usuario'];
            $buscar = mb_strtolower($nomCompleto.' '.$x['usuario'].' '.($x['asignatura'] ?? ''), 'UTF-8'); ?>
          <tr id="m-<?= $mid ?>" data-id="<?= $mid ?>" data-rol="<?= h($x['rol']) ?>" data-buscar="<?= h($buscar) ?>" class="<?= $mid===$nuevoId ? 'fila-nueva' : '' ?>">
            <td class="col-nombre">
              <form id="fe-<?= $mid ?>" method="POST" data-ajax class="d-none"><input type="hidden" name="id" value="<?= $mid ?>"></form>
              <span class="v fw-semibold nom-completo"><?= h($nomCompleto) ?></span>
              <div class="e gap-1">
                <input form="fe-<?= $mid ?>" name="nombre" class="form-control form-control-sm" style="min-width:110px" value="<?= h($x['nombre']) ?>" placeholder="Nombre" maxlength="100" required>
                <input form="fe-<?= $mid ?>" name="apellido" class="form-control form-control-sm" style="min-width:110px" value="<?= h($x['apellido']) ?>" placeholder="Apellido" maxlength="100" required>
              </div>
            </td>
            <td class="text-muted">@<?= h($x['usuario']) ?></td>
            <td><span class="badge <?= $x['rol']=='admin'?'bg-dark':'bg-primary' ?>"><?= $x['rol']=='admin'?'Administrador':'Docente' ?></span></td>
            <td>
              <span class="v celda-asig">
                <?php if($x['asignatura']): ?><span class="badge bg-primary-subtle text-primary" data-asig-badge="<?= (int)$x['asignatura_id'] ?>"><?= h($x['asignatura']) ?></span>
                <?php else: ?><span class="badge bg-warning-subtle text-warning-emphasis">Sin asignatura</span><?php endif; ?>
              </span>
              <select form="fe-<?= $mid ?>" name="asignatura_id" class="e form-select form-select-sm sel-asig" style="min-width:150px">
                <option value="">Sin asignatura</option>
                <?php foreach($asignaturas as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $a['id']==$x['asignatura_id']?'selected':'' ?>><?= h($a['nombre']) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td>
              <form method="POST" data-ajax class="d-flex gap-1" style="min-width:240px">
                <input type="hidden" name="id" value="<?= $mid ?>">
                <input type="password" name="password" class="form-control form-control-sm" placeholder="Nueva clave (mín. 6)" autocomplete="new-password" minlength="6" required>
                <button name="clave" class="btn btn-sm btn-outline-primary">Cambiar</button>
              </form>
            </td>
            <td class="text-end text-nowrap">
              <span class="v">
                <?php if($x['rol']==='docente'): ?>
                  <a href="?tarjetas=<?= $mid ?>" target="_blank" class="btn btn-sm btn-outline-success btn-tarjeta <?= $x['tiene_clave'] ? '' : 'd-none' ?>" title="Generar tarjeta de acceso">🪪</a>
                  <span class="d-inline-block btn-tarjeta-off <?= $x['tiene_clave'] ? 'd-none' : '' ?>" title="Sin clave guardada: asigna una nueva contraseña"><button type="button" class="btn btn-sm btn-outline-secondary" disabled>🪪</button></span>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-accion="editar">Editar</button>
                <form method="POST" data-ajax class="d-inline" data-confirmar="¿Eliminar este maestro? Sus opciones de canje se borran; el histórico y las metas se conservan."><input type="hidden" name="id" value="<?= $mid ?>"><button name="borrar" class="btn btn-sm btn-outline-danger" aria-label="Eliminar maestro">✕</button></form>
              </span>
              <span class="e gap-1 justify-content-end">
                <button type="submit" form="fe-<?= $mid ?>" name="editar_maestro" class="btn btn-sm btn-primary">Guardar</button>
                <button type="button" class="btn btn-sm btn-light" data-accion="cancelar">Cancelar</button>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="sin-resultados" id="sinResultados">Ningún maestro coincide con la búsqueda.</div>
      </div>
      <button type="button" id="btnArribaTabla" aria-label="Volver arriba">⬆️ Volver arriba</button>
    </div>
  </div>
</div>

<script>
(function(){
  const cont   = document.getElementById('tablaScroll');
  const cuerpo = document.getElementById('cuerpo');
  const btnTop = document.getElementById('btnArribaTabla');
  const txt    = document.getElementById('filtroTexto');
  const selRol = document.getElementById('filtroRol');
  const cuenta = document.getElementById('cuenta');
  const vacio  = document.getElementById('sinResultados');
  const sinTilde = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');

  /* ---------- Avisos flotantes ---------- */
  function aviso(msg, ok){
    const d = document.createElement('div');
    d.className = 'toast-msg ' + (ok ? 'ok' : 'err'); d.textContent = msg;
    document.getElementById('toasts').appendChild(d);
    setTimeout(() => d.remove(), ok ? 2800 : 5000);
  }

  /* ---------- Mostrar una fila y resaltarla dentro de la tabla ---------- */
  function resaltar(tr, centrar){
    if (centrar) {
      const r = tr.getBoundingClientRect(), c = cont.getBoundingClientRect();
      cont.scrollTo({top: cont.scrollTop + (r.top - c.top) - c.height/2 + r.height/2});
    }
    tr.classList.remove('flash'); void tr.offsetWidth; tr.classList.add('flash');
  }

  /* ---------- Filtros ---------- */
  function filtrar(){
    const q = sinTilde(txt.value.trim()), rol = selRol.value; let vis = 0;
    cuerpo.querySelectorAll('tr').forEach(tr => {
      const ok = (!rol || tr.dataset.rol === rol) && (!q || sinTilde(tr.dataset.buscar).includes(q));
      tr.classList.toggle('oculta', !ok); if (ok) vis++;
    });
    const total = cuerpo.querySelectorAll('tr').length;
    cuenta.textContent = (vis === total) ? total : (vis + ' de ' + total);
    vacio.style.display = vis ? 'none' : 'block';
  }
  txt.addEventListener('input', filtrar); selRol.addEventListener('change', filtrar);

  /* ---------- Volver arriba (lista de maestros) ---------- */
  cont.addEventListener('scroll', () => btnTop.classList.toggle('visible', cont.scrollTop > 150), {passive:true});
  btnTop.addEventListener('click', () => cont.scrollTo({top:0}));

  /* ---------- Edición en el lugar ---------- */
  function entrarEdicion(tr){
    tr.classList.add('editando');
    const i = tr.querySelector('input[name=nombre]'); if (i) { i.focus(); i.select(); }
  }
  function salirEdicion(tr){ tr.classList.remove('editando'); }

  document.addEventListener('click', e => {
    const b = e.target.closest('[data-accion]'); if (!b) return;
    const a = b.dataset.accion, tr = b.closest('tr'), it = b.closest('.item');
    if (a === 'editar')   entrarEdicion(tr);
    if (a === 'cancelar') { document.getElementById('fe-' + tr.dataset.id).reset(); salirEdicion(tr); }
    if (a === 'editar-asig')   { it.classList.add('editando'); const i = it.querySelector('input[name=nombre_asignatura]'); i.focus(); i.select(); }
    if (a === 'cancelar-asig') { it.querySelector('form').reset(); it.classList.remove('editando'); }
  });
  // Esc cancela la edición; Enter ya guarda (envía el formulario)
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const tr = e.target.closest && e.target.closest('tr.editando');
    if (tr) { document.getElementById('fe-' + tr.dataset.id).reset(); salirEdicion(tr); return; }
    const it = e.target.closest && e.target.closest('.item.editando');
    if (it) { it.querySelector('form').reset(); it.classList.remove('editando'); }
  });

  /* ---------- Envío por fetch (sin recargar la página) ---------- */
  document.addEventListener('submit', async e => {
    const f = e.target; if (!f.matches('form[data-ajax]')) return;
    e.preventDefault();
    if (f.dataset.confirmar && !confirm(f.dataset.confirmar)) return;

    const fd = new FormData(f);
    if (e.submitter && e.submitter.name) fd.append(e.submitter.name, '1');
    // Los campos del formulario de edición viven en otras celdas (atributo form=)
    const accion = ['editar_maestro','clave','borrar','editar_asignatura'].find(k => fd.has(k));
    const btns = [...document.querySelectorAll('button[form="' + f.id + '"]'), ...f.querySelectorAll('button')];
    btns.forEach(b => b.disabled = true);
    try {
      const r = await fetch('maestros.php', {method:'POST', body:fd, headers:{'X-Requested-With':'fetch'}});
      const d = await r.json();
      if (!d.ok) { aviso(d.error || 'No se pudo completar la operación.', false); return; }
      const tr = f.closest('tr') || document.getElementById('m-' + fd.get('id'));

      if (accion === 'editar_maestro' && d.maestro) {
        const mm = d.maestro, nom = ((mm.nombre + ' ' + mm.apellido).trim()) || mm.usuario;
        tr.querySelector('.nom-completo').textContent = nom;
        const celda = tr.querySelector('.celda-asig');
        celda.innerHTML = '';
        const sp = document.createElement('span');
        if (mm.asignatura) { sp.className = 'badge bg-primary-subtle text-primary'; sp.dataset.asigBadge = mm.asignatura_id; sp.textContent = mm.asignatura; }
        else { sp.className = 'badge bg-warning-subtle text-warning-emphasis'; sp.textContent = 'Sin asignatura'; }
        celda.appendChild(sp);
        // lo recién guardado pasa a ser el valor "original" (para que Cancelar no lo revierta)
        tr.querySelectorAll('input[name=nombre],input[name=apellido]').forEach(i => i.defaultValue = i.value);
        tr.querySelectorAll('select[name=asignatura_id] option').forEach(o => o.defaultSelected = o.selected);
        tr.dataset.buscar = (nom + ' ' + mm.usuario + ' ' + (mm.asignatura || '')).toLowerCase();
        salirEdicion(tr); filtrar(); resaltar(tr, false);
        aviso(d.mensaje, true);
      }
      else if (accion === 'clave') {
        f.reset();
        if (d.tiene_clave) { tr.querySelector('.btn-tarjeta')?.classList.remove('d-none'); tr.querySelector('.btn-tarjeta-off')?.classList.add('d-none'); }
        resaltar(tr, false); aviso(d.mensaje, true);
      }
      else if (accion === 'borrar') {
        tr.remove(); filtrar(); aviso(d.mensaje, true);
      }
      else if (accion === 'editar_asignatura') {
        const it = f.closest('.item'), id = it.dataset.id, nombre = fd.get('nombre_asignatura').trim();
        it.querySelector('.nom-asig').textContent = nombre;
        f.querySelector('input[name=nombre_asignatura]').defaultValue = nombre;
        it.classList.remove('editando');
        document.querySelectorAll('select.sel-asig option[value="' + id + '"]').forEach(o => o.textContent = nombre);
        document.querySelectorAll('[data-asig-badge="' + id + '"]').forEach(b => b.textContent = nombre);
        cuerpo.querySelectorAll('tr').forEach(t => { if (t.querySelector('[data-asig-badge="' + id + '"]')) {
          const nm = t.querySelector('.nom-completo').textContent, us = t.children[1].textContent.replace('@','');
          t.dataset.buscar = (nm + ' ' + us + ' ' + nombre).toLowerCase(); } });
        aviso(d.mensaje, true);
      }
    } catch (err) {
      aviso('Error de conexión. Intenta nuevamente.', false);
    } finally {
      btns.forEach(b => { b.disabled = false; });
    }
  });

  /* ---------- Si se acaba de crear un maestro: llevarlo a la vista y resaltarlo ---------- */
  const nuevo = cuerpo.querySelector('tr.fila-nueva');
  if (nuevo) setTimeout(() => resaltar(nuevo, true), 150);
})();
</script>
<?php include 'footer.php'; ?>
</body></html>
