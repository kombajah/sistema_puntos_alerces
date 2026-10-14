<?php
// Administración de las ferias del canje (solo administrador): ferias, productos con su costo en puntos y resultados.
require_once 'conexion.php';
requiere_login();
if (!es_admin()) { header('Location: feria.php'); exit; }
require_once 'feria_lib.php';
feria_asegurar($conn);

function fechaOk($d){ return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4)); }
// Devuelve el id del curso si existe, o null (= producto para todos los cursos).
function cursoOk($conn, $v){
  $id = (int)$v; if ($id <= 0) return null;
  $q = $conn->prepare("SELECT id FROM cursos WHERE id=?"); $q->bind_param("i", $id); $q->execute();
  return $q->get_result()->fetch_assoc() ? $id : null;
}
function opcionesCursos($cursos, $sel = null){
  $o = '<option value="">Todos los cursos</option>';
  foreach ($cursos as $c) $o .= '<option value="' . (int)$c['id'] . '"' . ((int)$c['id'] === (int)$sel ? ' selected' : '') . '>' . h($c['nombre']) . '</option>';
  return $o;
}
function volver($id, $tipo, $msg){ $_SESSION['flash_feria'] = [$tipo, $msg]; header('Location: ferias.php' . ($id ? '?feria=' . (int)$id : '')); exit; }

// ---------------------------- Descarga de los canjes de una feria (Excel .xlsx; CSV si el servidor no tiene ZipArchive) ----------------------------
function xlsx_col($i){ $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; }
function xlsx_txt($t){ return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string)$t), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
// $filas: cada celda es texto, número o ['fecha' => 'Y-m-d H:i:s'].
function xlsx_enviar(array $cab, array $filas, array $anchos, $archivo, $hoja){
  $rows = '<row r="1">';
  foreach ($cab as $i => $t) $rows .= '<c r="' . xlsx_col($i) . '1" s="1" t="inlineStr"><is><t>' . xlsx_txt($t) . '</t></is></c>';
  $rows .= '</row>'; $n = 1;
  foreach ($filas as $f) {
    $n++; $rows .= '<row r="' . $n . '">';
    foreach ($f as $i => $v) {
      $ref = xlsx_col($i) . $n;
      if (is_array($v)) { $ts = strtotime(($v['fecha'] ?? '') . ' UTC'); $rows .= $ts === false ? '<c r="' . $ref . '"/>' : '<c r="' . $ref . '" s="2"><v>' . ($ts / 86400 + 25569) . '</v></c>'; }
      elseif (is_int($v) || is_float($v)) $rows .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
      else $rows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_txt($v) . '</t></is></c>';
    }
    $rows .= '</row>';
  }
  $cols = '';
  foreach ($anchos as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
  $ult = xlsx_col(count($cab) - 1) . max(1, $n);
  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $rows . '</sheetData><autoFilter ref="A1:' . $ult . '"/></worksheet>';
  $hdr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
  $ns = 'http://schemas.openxmlformats.org/';
  $zip = new ZipArchive(); $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
  $zip->open($tmp, ZipArchive::OVERWRITE);
  $zip->addFromString('[Content_Types].xml', $hdr . '<Types xmlns="' . $ns . 'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
  $zip->addFromString('_rels/.rels', $hdr . '<Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $zip->addFromString('xl/workbook.xml', $hdr . '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships"><sheets><sheet name="' . xlsx_txt($hoja) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $zip->addFromString('xl/_rels/workbook.xml.rels', $hdr . '<Relationships xmlns="' . $ns . 'package/2006/relationships"><Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
  $zip->addFromString('xl/styles.xml', $hdr . '<styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy\ hh:mm"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2E7D62"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
  $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
  $zip->close();
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $archivo . '"');
  header('Content-Length: ' . filesize($tmp));
  readfile($tmp); @unlink($tmp); exit;
}

if (isset($_GET['excel']) || isset($_GET['csv'])) {
  $fid = (int)($_GET['excel'] ?? $_GET['csv']);
  $s = $conn->prepare("SELECT nombre FROM ferias WHERE id=?"); $s->bind_param("i", $fid); $s->execute();
  $f = $s->get_result()->fetch_assoc();
  if (!$f) { http_response_code(404); exit; }
  $s = $conn->prepare("SELECT c.fecha, cu.nombre curso, a.nombre alumno, c.nombre_opcion premio, c.puntos_virtuales pts, " . sql_nombre_maestro('m') . " profesor
     FROM canjes c JOIN alumnos a ON a.id=c.alumno_id JOIN cursos cu ON cu.id=a.curso_id LEFT JOIN maestros m ON m.id=c.maestro_id
     WHERE c.feria_id=? ORDER BY c.fecha, c.id");
  $s->bind_param("i", $fid); $s->execute();
  $datos = [];
  foreach ($s->get_result() as $r) $datos[] = [$f['nombre'], ['fecha' => $r['fecha']], $r['curso'], $r['alumno'], (string)$r['premio'], (int)$r['pts'], (string)$r['profesor']];
  $cab = ['Feria', 'Fecha', 'Curso', 'Alumno', 'Premio', 'Puntos', 'Profesor'];
  if (class_exists('ZipArchive')) xlsx_enviar($cab, $datos, [32, 18, 14, 28, 28, 9, 24], 'Canjes_feria_' . $fid . '.xlsx', 'Canjes');
  // Respaldo: CSV (para Excel en español, con separador «;»)
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="Canjes_feria_' . $fid . '.csv"');
  echo "\xEF\xBB\xBF";
  $o = fopen('php://output', 'w');
  fputcsv($o, $cab, ';');
  foreach ($datos as $d) { $d[1] = $d[1]['fecha']; fputcsv($o, $d, ';'); }
  fclose($o); exit;
}

// ---------------------------- Acciones ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validar();
  $ac = $_POST['accion'] ?? ''; $fid = (int)($_POST['feria_id'] ?? 0); $uid = docente_id();

  if ($ac === 'crear_feria') {
    $nom = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100); $fecha = trim($_POST['fecha'] ?? ''); $copiar = (int)($_POST['copiar_de'] ?? 0);
    if ($nom === '') volver(0, 'danger', 'Escribe el nombre de la feria.');
    $fv = fechaOk($fecha) ? $fecha : null; $ahora = ahora_chile();
    $s = $conn->prepare("INSERT INTO ferias (nombre, fecha, estado, creada, creada_por) VALUES (?,?,'abierta',?,?)");
    $s->bind_param("sssi", $nom, $fv, $ahora, $uid); $s->execute();
    $nuevo = (int)$conn->insert_id;
    if ($copiar) {
      $s = $conn->prepare("INSERT INTO feria_productos (feria_id, nombre, costo_puntos, stock, activo, curso_id) SELECT ?, nombre, costo_puntos, stock, activo, curso_id FROM feria_productos WHERE feria_id=?");
      $s->bind_param("ii", $nuevo, $copiar); $s->execute();
    }
    volver($nuevo, 'success', 'Feria creada y abierta.' . ($copiar ? ' Se copiaron los productos.' : ' Ahora agrega sus productos.'));
  }
  if ($ac === 'editar_feria') {
    $nom = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100); $fecha = trim($_POST['fecha'] ?? ''); $fv = fechaOk($fecha) ? $fecha : null;
    if ($nom === '') volver($fid, 'danger', 'Escribe el nombre de la feria.');
    $s = $conn->prepare("UPDATE ferias SET nombre=?, fecha=? WHERE id=?"); $s->bind_param("ssi", $nom, $fv, $fid); $s->execute();
    volver($fid, 'success', 'Feria actualizada. Los canjes anteriores conservan el nombre con el que se registraron.');
  }
  if ($ac === 'estado_feria') {
    $est = ($_POST['estado'] ?? '') === 'abierta' ? 'abierta' : 'cerrada';
    $s = $conn->prepare("UPDATE ferias SET estado=? WHERE id=?"); $s->bind_param("si", $est, $fid); $s->execute();
    volver($fid, 'success', $est === 'abierta' ? 'Feria abierta: los docentes ya pueden canjear.' : 'Feria cerrada: ya no se pueden registrar canjes en ella.');
  }
  if ($ac === 'borrar_feria') {
    $s = $conn->prepare("SELECT COUNT(*) n FROM canjes WHERE feria_id=?"); $s->bind_param("i", $fid); $s->execute();
    if ((int)$s->get_result()->fetch_assoc()['n'] > 0) volver($fid, 'danger', 'Esta feria ya tiene canjes y no se puede eliminar. Ciérrala para conservar su historial.');
    $s = $conn->prepare("DELETE FROM ferias WHERE id=?"); $s->bind_param("i", $fid); $s->execute();
    volver(0, 'success', 'Feria eliminada.');
  }
  if ($ac === 'crear_producto') {
    $nom = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100); $costo = (int)($_POST['costo'] ?? 0);
    $stockTxt = trim($_POST['stock'] ?? ''); $stock = $stockTxt === '' ? null : max(0, (int)$stockTxt);
    $cursoId = cursoOk($conn, $_POST['curso_id'] ?? '');
    if ($nom === '' || $costo < 1) volver($fid, 'danger', 'Escribe el nombre del producto y un costo en puntos válido (1 o más).');
    $s = $conn->prepare("INSERT INTO feria_productos (feria_id, nombre, costo_puntos, stock, curso_id) VALUES (?,?,?,?,?)");
    $s->bind_param("isiii", $fid, $nom, $costo, $stock, $cursoId); $s->execute();
    volver($fid, 'success', 'Producto agregado.');
  }
  if ($ac === 'guardar_producto') {
    $pid = (int)($_POST['id'] ?? 0); $nom = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100); $costo = (int)($_POST['costo'] ?? 0);
    $stockTxt = trim($_POST['stock'] ?? ''); $stock = $stockTxt === '' ? null : max(0, (int)$stockTxt);
    $cursoId = cursoOk($conn, $_POST['curso_id'] ?? '');
    if ($nom === '' || $costo < 1) volver($fid, 'danger', 'Nombre y costo válidos son obligatorios.');
    $s = $conn->prepare("UPDATE feria_productos SET nombre=?, costo_puntos=?, stock=?, curso_id=? WHERE id=? AND feria_id=?");
    $s->bind_param("siiiii", $nom, $costo, $stock, $cursoId, $pid, $fid); $s->execute();
    volver($fid, 'success', 'Producto actualizado. Los canjes ya hechos conservan su costo original.');
  }
  if ($ac === 'toggle_producto') {
    $pid = (int)($_POST['id'] ?? 0); $nuevo = (int)($_POST['activo'] ?? 0) ? 1 : 0;
    $s = $conn->prepare("UPDATE feria_productos SET activo=? WHERE id=? AND feria_id=?"); $s->bind_param("iii", $nuevo, $pid, $fid); $s->execute();
    volver($fid, 'success', $nuevo ? 'Producto activado.' : 'Producto desactivado: no aparece para canjear.');
  }
  if ($ac === 'borrar_producto') {
    $pid = (int)($_POST['id'] ?? 0);
    $s = $conn->prepare("SELECT COUNT(*) n FROM canjes WHERE feria_producto_id=?"); $s->bind_param("i", $pid); $s->execute();
    if ((int)$s->get_result()->fetch_assoc()['n'] > 0) volver($fid, 'danger', 'Ese producto ya fue canjeado y no se puede eliminar. Desactívalo.');
    $s = $conn->prepare("DELETE FROM feria_productos WHERE id=? AND feria_id=?"); $s->bind_param("ii", $pid, $fid); $s->execute();
    volver($fid, 'success', 'Producto eliminado.');
  }
  if ($ac === 'copiar_productos') {
    $desde = (int)($_POST['desde'] ?? 0);
    if (!$desde || $desde === $fid) volver($fid, 'danger', 'Elige la feria desde la que quieres copiar.');
    $s = $conn->prepare("INSERT INTO feria_productos (feria_id, nombre, costo_puntos, stock, activo, curso_id) SELECT ?, nombre, costo_puntos, stock, activo, curso_id FROM feria_productos WHERE feria_id=?");
    $s->bind_param("ii", $fid, $desde); $s->execute();
    volver($fid, 'success', 'Productos copiados.');
  }
}

// ---------------------------- Datos ----------------------------
$flash = $_SESSION['flash_feria'] ?? null; unset($_SESSION['flash_feria']);
$ferias = $conn->query("SELECT f.*, (SELECT COUNT(*) FROM canjes c WHERE c.feria_id=f.id) canjes FROM ferias f ORDER BY f.fecha DESC, f.id DESC")->fetch_all(MYSQLI_ASSOC);
$sel = (int)($_GET['feria'] ?? 0);
$feria = null;
foreach ($ferias as $f) if ((int)$f['id'] === $sel) $feria = $f;
if (!$feria && $ferias) { $feria = $ferias[0]; $sel = (int)$feria['id']; }
$cursos = $conn->query("SELECT id, nombre FROM cursos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$prods = []; $res = null; $canjes = [];
if ($feria) {
  $prods = feria_productos($conn, $sel);
  $s = $conn->prepare("SELECT COUNT(*) n, COALESCE(SUM(puntos_virtuales),0) pts, COUNT(DISTINCT alumno_id) alumnos FROM canjes WHERE feria_id=?");
  $s->bind_param("i", $sel); $s->execute(); $res = $s->get_result()->fetch_assoc();
  $s = $conn->prepare("SELECT c.fecha, a.nombre alumno, cu.nombre curso, c.nombre_opcion premio, c.puntos_virtuales pts, " . sql_nombre_maestro('m') . " profesor
     FROM canjes c JOIN alumnos a ON a.id=c.alumno_id JOIN cursos cu ON cu.id=a.curso_id LEFT JOIN maestros m ON m.id=c.maestro_id
     WHERE c.feria_id=? ORDER BY c.fecha DESC, c.id DESC LIMIT 300");
  $s->bind_param("i", $sel); $s->execute(); $canjes = $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html><html lang="es"><head><title>Administrar ferias</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2 mb-4" style="max-width:1000px">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h4 class="text-primary mb-0 me-auto">⚙️ Administrar ferias del canje</h4>
    <a href="feria.php" class="btn btn-sm btn-outline-primary">🎪 Ir a canjear</a>
  </div>
  <?php if ($flash): ?><div class="alert alert-<?= h($flash[0]) ?>"><?= h($flash[1]) ?></div><?php endif; ?>

  <div class="card p-3 shadow-sm mb-3">
    <h5 class="mb-2">Nueva feria</h5>
    <form method="POST" class="row g-2"><?= csrf_campo() ?><input type="hidden" name="accion" value="crear_feria">
      <div class="col-md-5"><input name="nombre" class="form-control" maxlength="100" required placeholder="Ej: Feria del canje Otoño 2026"></div>
      <div class="col-md-2"><input type="date" name="fecha" class="form-control" title="Fecha de la feria (opcional)"></div>
      <div class="col-md-3"><select name="copiar_de" class="form-select"><option value="0">Sin copiar productos</option>
        <?php foreach ($ferias as $f): ?><option value="<?= (int)$f['id'] ?>">Copiar productos de: <?= h($f['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Crear feria</button></div>
    </form>
  </div>

<?php if (!$ferias): ?>
  <div class="alert alert-info">Aún no hay ferias. Crea la primera arriba.</div>
<?php else: ?>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($ferias as $f): ?>
      <a href="ferias.php?feria=<?= (int)$f['id'] ?>" class="btn btn-sm <?= (int)$f['id'] === $sel ? 'btn-primary' : 'btn-outline-primary' ?>">
        <?= $f['estado'] === 'abierta' ? '🟢' : '⚪' ?> <?= h($f['nombre']) ?><?= $f['fecha'] ? ' · ' . h(date('d/m/Y', strtotime($f['fecha']))) : '' ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card p-3 shadow-sm mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <h5 class="mb-0 me-auto"><?= h($feria['nombre']) ?>
        <span class="badge <?= $feria['estado'] === 'abierta' ? 'bg-success' : 'bg-secondary' ?>"><?= $feria['estado'] === 'abierta' ? 'Abierta' : 'Cerrada' ?></span></h5>
      <form method="POST"><?= csrf_campo() ?><input type="hidden" name="accion" value="estado_feria"><input type="hidden" name="feria_id" value="<?= $sel ?>">
        <input type="hidden" name="estado" value="<?= $feria['estado'] === 'abierta' ? 'cerrada' : 'abierta' ?>">
        <button class="btn btn-sm btn-outline-primary"><?= $feria['estado'] === 'abierta' ? '🔒 Cerrar feria' : '🔓 Reabrir feria' ?></button></form>
      <form method="POST" onsubmit="return confirm('¿Eliminar esta feria y sus productos?')"><?= csrf_campo() ?><input type="hidden" name="accion" value="borrar_feria"><input type="hidden" name="feria_id" value="<?= $sel ?>">
        <button class="btn btn-sm btn-outline-danger" <?= (int)$feria['canjes'] > 0 ? 'disabled title="Tiene canjes: ciérrala para conservar el historial"' : '' ?>>Eliminar</button></form>
    </div>
    <form method="POST" class="row g-2 mb-1"><?= csrf_campo() ?><input type="hidden" name="accion" value="editar_feria"><input type="hidden" name="feria_id" value="<?= $sel ?>">
      <div class="col-md-6"><input name="nombre" class="form-control form-control-sm" maxlength="100" required value="<?= h($feria['nombre']) ?>"></div>
      <div class="col-md-3"><input type="date" name="fecha" class="form-control form-control-sm" value="<?= h($feria['fecha'] ?? '') ?>"></div>
      <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary w-100">Guardar nombre y fecha</button></div>
    </form>
  </div>

  <div class="card p-3 shadow-sm mb-3">
    <h5 class="mb-2">🎁 Productos de la feria</h5>
    <form method="POST" class="row g-2 mb-3"><?= csrf_campo() ?><input type="hidden" name="accion" value="crear_producto"><input type="hidden" name="feria_id" value="<?= $sel ?>">
      <div class="col-md-4"><input name="nombre" class="form-control" maxlength="100" required placeholder="Producto (ej: Lápiz con goma)"></div>
      <div class="col-md-3"><select name="curso_id" class="form-select" title="Curso al que pertenece el producto"><?= opcionesCursos($cursos) ?></select></div>
      <div class="col-6 col-md-1"><input type="number" name="costo" class="form-control" min="1" required placeholder="Pts" title="Costo en puntos"></div>
      <div class="col-6 col-md-1"><input type="number" name="stock" class="form-control" min="0" placeholder="Stock" title="Stock (vacío = sin límite)"></div>
      <div class="col-md-3"><button class="btn btn-primary w-100">Agregar producto</button></div>
    </form>
    <?php foreach ($prods as $p): ?>
      <form method="POST" class="row g-2 align-items-center border-top py-2"><?= csrf_campo() ?><input type="hidden" name="feria_id" value="<?= $sel ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <div class="col-md-3"><input name="nombre" class="form-control form-control-sm <?= $p['activo'] ? '' : 'text-muted' ?>" maxlength="100" value="<?= h($p['nombre']) ?>"></div>
        <div class="col-md-2"><select name="curso_id" class="form-select form-select-sm" title="Curso del producto"><?= opcionesCursos($cursos, $p['curso_id']) ?></select></div>
        <div class="col-4 col-md-1"><input type="number" name="costo" class="form-control form-control-sm" min="1" value="<?= $p['costo'] ?>" title="Costo en puntos"></div>
        <div class="col-4 col-md-1"><input type="number" name="stock" class="form-control form-control-sm" min="0" value="<?= $p['stock'] === null ? '' : $p['stock'] ?>" placeholder="Sin límite" title="Stock total (vacío = sin límite)"></div>
        <div class="col-4 col-md-1 small text-muted text-center" title="Canjeados"><?= $p['usados'] ?> canj.</div>
        <div class="col-md-4 d-flex gap-1 justify-content-md-end">
          <button name="accion" value="guardar_producto" class="btn btn-sm btn-outline-primary">Guardar</button>
          <button name="accion" value="toggle_producto" class="btn btn-sm btn-outline-secondary" onclick="this.form.activo.value=<?= $p['activo'] ? 0 : 1 ?>"><?= $p['activo'] ? 'Desactivar' : 'Activar' ?></button>
          <input type="hidden" name="activo" value="">
          <button name="accion" value="borrar_producto" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este producto?')" <?= $p['usados'] > 0 ? 'disabled title="Ya fue canjeado: desactívalo"' : '' ?>>✕</button>
        </div>
      </form>
    <?php endforeach; if (!$prods) echo "<p class='text-muted small mb-0'>Esta feria aún no tiene productos.</p>"; ?>
    <?php if (count($ferias) > 1): ?>
    <form method="POST" class="d-flex flex-wrap gap-2 border-top pt-3 mt-2"><?= csrf_campo() ?><input type="hidden" name="accion" value="copiar_productos"><input type="hidden" name="feria_id" value="<?= $sel ?>">
      <select name="desde" class="form-select form-select-sm w-auto"><option value="0">Copiar productos de otra feria...</option>
        <?php foreach ($ferias as $f): if ((int)$f['id'] !== $sel): ?><option value="<?= (int)$f['id'] ?>"><?= h($f['nombre']) ?></option><?php endif; endforeach; ?></select>
      <button class="btn btn-sm btn-outline-secondary">Copiar</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card p-3 shadow-sm table-responsive">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <h5 class="mb-0 me-auto">📋 Canjes de esta feria</h5>
      <span class="small text-muted"><?= (int)$res['n'] ?> premio(s) · <?= (int)$res['pts'] ?> pts · <?= (int)$res['alumnos'] ?> alumno(s)</span>
      <a class="btn btn-sm btn-outline-primary <?= (int)$res['n'] ? '' : 'disabled' ?>" href="ferias.php?excel=<?= $sel ?>">⬇️ Descargar Excel</a>
    </div>
    <table class="table table-sm table-striped align-middle mb-0">
      <thead><tr><th>Fecha</th><th>Curso</th><th>Alumno</th><th>Premio</th><th class="text-center">Pts</th><th>Profesor</th></tr></thead>
      <tbody>
      <?php foreach ($canjes as $c): ?>
        <tr><td><?= h(date('d/m/Y H:i', strtotime($c['fecha']))) ?></td><td><?= h($c['curso']) ?></td><td><?= h($c['alumno']) ?></td><td><?= h($c['premio']) ?></td><td class="text-center"><?= (int)$c['pts'] ?></td><td><?= $c['profesor'] ? h($c['profesor']) : '—' ?></td></tr>
      <?php endforeach; if (!$canjes) echo "<tr><td colspan='6' class='text-center text-muted'>Sin canjes todavía.</td></tr>"; ?>
      </tbody>
    </table>
    <?php if ((int)$res['n'] > 300): ?><small class="text-muted mt-2">Se muestran los últimos 300; el Excel incluye todos.</small><?php endif; ?>
  </div>
<?php endif; ?>
</div>
<?php include 'footer.php'; ?>
</body></html>
