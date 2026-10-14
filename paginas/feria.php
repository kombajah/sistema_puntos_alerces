<?php
// Feria del canje: los docentes canjean los puntos de los alumnos por productos de la feria abierta.
//  · Por curso: se elige el curso, aparece la lista de alumnos con los premios y se canjea desde cada fila.
//  · Por tarjeta: se escanea la tarjeta NFC o el QR del alumno y se canjea al instante.
require_once 'conexion.php';
requiere_login();
require_once 'feria_lib.php';
feria_asegurar($conn);

// Últimos canjes de una feria (los usa la página al cargar y la respuesta de cada canje para actualizar la tabla sin recargar).
function feria_ultimos($conn, $feriaId){
  $s = $conn->prepare("SELECT c.fecha, a.nombre alumno, cu.nombre curso, c.nombre_opcion premio, c.puntos_virtuales pts, " . sql_nombre_maestro('m') . " profesor
     FROM canjes c JOIN alumnos a ON a.id=c.alumno_id JOIN cursos cu ON cu.id=a.curso_id LEFT JOIN maestros m ON m.id=c.maestro_id
     WHERE c.feria_id=? ORDER BY c.fecha DESC, c.id DESC LIMIT 15");
  $s->bind_param("i", $feriaId); $s->execute();
  $r = $s->get_result()->fetch_all(MYSQLI_ASSOC);
  foreach ($r as &$x) { $x['fecha_txt'] = date('d/m H:i', strtotime($x['fecha'])); $x['pts'] = (int)$x['pts']; $x['profesor'] = $x['profesor'] ?: '—'; }
  unset($x);
  return $r;
}

// ---------------------------- Endpoints JSON ----------------------------
if (isset($_GET['a'])) {
  header('Content-Type: application/json; charset=utf-8');
  $a = $_GET['a'];
  if ($a === 'curso') {
    $cid = (int)($_GET['curso'] ?? 0);
    $s = $conn->prepare("SELECT a.id, a.nombre,
        COALESCE((SELECT SUM(puntos) FROM registro_puntos WHERE alumno_id=a.id),0)
      - COALESCE((SELECT SUM(puntos_virtuales) FROM canjes WHERE alumno_id=a.id),0) AS saldo
      FROM alumnos a WHERE a.curso_id=? ORDER BY a.nombre");
    $s->bind_param("i", $cid); $s->execute();
    $al = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($al as &$x) { $x['id'] = (int)$x['id']; $x['saldo'] = (int)$x['saldo']; } unset($x);
    echo json_encode(['alumnos' => $al], JSON_UNESCAPED_UNICODE); exit;
  }
  if ($a === 'alumno') {
    $id = (int)($_GET['id'] ?? 0);
    $s = $conn->prepare("SELECT a.id, a.nombre, c.nombre curso FROM alumnos a JOIN cursos c ON c.id=a.curso_id WHERE a.id=?");
    $s->bind_param("i", $id); $s->execute();
    $r = $s->get_result()->fetch_assoc();
    if (!$r) { echo json_encode(['error' => 'Alumno no encontrado']); exit; }
    $r['id'] = (int)$r['id']; $r['saldo'] = (int)feria_saldo($conn, $id);
    echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
  }
  if ($a === 'canjear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    iniciar_sesion();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { echo json_encode(['ok' => false, 'error' => 'La página caducó. Recárgala e inténtalo de nuevo.']); exit; }
    $items = json_decode((string)($_POST['items'] ?? '{}'), true);
    if (!is_array($items)) $items = [];
    [$ok, $msg, $datos] = feria_canjear($conn, (int)($_POST['feria_id'] ?? 0), (int)($_POST['alumno_id'] ?? 0), $items);
    if (!$ok) { echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE); exit; }
    $disp = [];
    foreach (feria_productos($conn, (int)$_POST['feria_id'], true) as $p) $disp[$p['id']] = $p['disp'];
    echo json_encode(['ok' => true, 'saldo' => $datos['saldo'], 'total' => $datos['total'], 'premios' => $datos['premios'], 'disp' => $disp, 'ultimos' => feria_ultimos($conn, (int)$_POST['feria_id'])], JSON_UNESCAPED_UNICODE); exit;
  }
  echo json_encode(['error' => 'Solicitud no válida']); exit;
}

// ---------------------------- Página ----------------------------
$abiertas = feria_abiertas($conn);
$feriaId = (int)($_GET['feria'] ?? 0);
if (!in_array($feriaId, array_map('intval', array_column($abiertas, 'id')), true)) $feriaId = $abiertas ? (int)$abiertas[0]['id'] : 0;
$feria = null;
foreach ($abiertas as $f) if ((int)$f['id'] === $feriaId) $feria = $f;
$productos = $feriaId ? feria_productos($conn, $feriaId, true) : [];
$cursos = $conn->query("SELECT id, nombre FROM cursos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$ultimos = $feriaId ? feria_ultimos($conn, $feriaId) : [];
?>
<!DOCTYPE html><html lang="es"><head><title>Feria del canje</title><?php include 'head.php'; ?>
<style>
.fa-fila{background:#fff;border:1px solid var(--forest-line);border-radius:14px;padding:12px;margin-bottom:10px}
.fa-fila.sin-saldo{opacity:.6}
.fchip{border:1px solid var(--forest-primary);background:var(--forest-tint);color:var(--forest-text);border-radius:14px;padding:6px 12px;margin:0 6px 6px 0;font-size:.9rem;text-align:left;cursor:pointer}
.fchip b{color:var(--forest-primary-dark)}
.fchip.on{background:var(--forest-primary);color:var(--on-primary)}
.fchip.on b{color:var(--on-primary)}
.fchip.off{opacity:.4;cursor:not-allowed}
.fchip small{display:block;font-size:.7rem;opacity:.8}
.fchip .menos{display:inline-block;margin-left:6px;padding:0 7px;border-radius:8px;background:#00000026;font-weight:700}
#aviso{position:fixed;top:10px;left:50%;transform:translateX(-50%);z-index:3000;max-width:92%;display:none;box-shadow:0 6px 20px #0003}
#lectorQR{display:none;max-width:360px;margin:10px auto}
</style></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div id="aviso" class="alert mb-0"></div>
<div class="container mt-2 mb-4" style="max-width:980px">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h4 class="text-primary mb-0 me-auto">🎪 Feria del canje</h4>
    <?php if (es_admin()): ?><a href="ferias.php" class="btn btn-sm btn-outline-primary">⚙️ Administrar ferias</a><?php endif; ?>
  </div>

<?php if (!$abiertas): ?>
  <div class="alert alert-info">No hay ninguna feria abierta en este momento.<?php if (es_admin()): ?> Crea una en <a href="ferias.php">Administrar ferias</a>.<?php else: ?> Consulta con el administrador cuándo se abrirá la próxima.<?php endif; ?></div>
<?php else: ?>
  <div class="card p-3 shadow-sm mb-3">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-8">
        <label class="form-label small mb-1">Feria</label>
        <select name="feria" class="form-select" onchange="this.form.submit()">
          <?php foreach ($abiertas as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $feriaId ? 'selected' : '' ?>><?= h($f['nombre']) ?><?= $f['fecha'] ? ' · ' . h(date('d/m/Y', strtotime($f['fecha']))) : '' ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 small text-muted pt-md-4"><?= count($productos) ?> producto<?= count($productos) === 1 ? '' : 's' ?> disponible<?= count($productos) === 1 ? '' : 's' ?></div>
    </form>
  </div>

  <?php if (!$productos): ?>
    <div class="alert alert-warning">Esta feria todavía no tiene productos activos.<?php if (es_admin()): ?> Agrégalos en <a href="ferias.php?feria=<?= $feriaId ?>">Administrar ferias</a>.<?php endif; ?></div>
  <?php else: ?>

  <div class="card p-3 shadow-sm mb-3">
    <h5 class="text-primary mb-2">👥 Canjear por curso</h5>
    <div class="row g-2 mb-2">
      <div class="col-md-5"><select id="selCurso" class="form-select"><option value="">Elige un curso...</option>
        <?php foreach ($cursos as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><input id="buscar" class="form-control" placeholder="Buscar alumno..." disabled></div>
      <div class="col-md-3 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" id="soloSaldo" checked><label class="form-check-label small" for="soloSaldo">Solo con saldo</label></div></div>
    </div>
    <p class="small text-muted mb-2">Toca un premio para agregarlo (varias veces si quieres más de uno) y presiona <b>Canjear</b> en la fila del alumno.</p>
    <div id="listaAlumnos"><p class="text-muted small mb-0">Elige un curso para ver a sus alumnos.</p></div>
  </div>

  <div class="card p-3 shadow-sm mb-3">
    <h5 class="text-primary mb-2">🛜 Canjear con tarjeta</h5>
    <p class="small text-muted mb-2">Acerca la tarjeta NFC o muestra el QR del alumno: aparecen sus premios para canjear de inmediato.</p>
    <div class="d-flex flex-wrap gap-2 mb-2">
      <button type="button" class="btn btn-dark rounded-pill flex-fill" id="btnNFC" onclick="iniciarNFC()">🛜 Escanear tarjeta NFC</button>
      <button type="button" class="btn btn-outline-dark rounded-pill flex-fill" onclick="iniciarQR()">📷 Escanear código QR</button>
    </div>
    <div id="estadoScan" class="small text-muted text-center mb-2"></div>
    <div id="lectorQR"></div>
    <div id="resScan"></div>
  </div>
  <?php endif; ?>

  <div class="card p-3 shadow-sm table-responsive">
    <h6 class="mb-2">Últimos canjes de esta feria</h6>
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Fecha</th><th>Alumno</th><th>Curso</th><th>Premio</th><th class="text-center">Pts</th><th>Profesor</th></tr></thead>
      <tbody id="ultimos">
      <?php foreach ($ultimos as $u): ?>
        <tr><td><?= h($u['fecha_txt']) ?></td><td><?= h($u['alumno']) ?></td><td><?= h($u['curso']) ?></td><td><?= h($u['premio']) ?></td><td class="text-center"><?= (int)$u['pts'] ?></td><td><?= h($u['profesor']) ?></td></tr>
      <?php endforeach; if (!$ultimos) echo "<tr><td colspan='6' class='text-center text-muted'>Aún no hay canjes en esta feria.</td></tr>"; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>
<?php include 'footer.php'; ?>
<?php if ($abiertas && $productos): ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>, FERIA = <?= (int)$feriaId ?>;
const P = <?= json_encode($productos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const pm = {}, disp = {}; P.forEach(p => { pm[p.id] = p; disp[p.id] = p.disp; });
const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let filas = [];

function aviso(tipo, txt){
  const a = $('aviso'); a.className = 'alert mb-0 alert-' + (tipo === 'ok' ? 'success' : 'danger'); a.textContent = txt; a.style.display = 'block';
  clearTimeout(a._t); a._t = setTimeout(() => a.style.display = 'none', 6000);
}
function pintarUltimos(lista){
  const tb = $('ultimos');
  tb.innerHTML = lista.length ? lista.map(u => '<tr><td>' + esc(u.fecha_txt) + '</td><td>' + esc(u.alumno) + '</td><td>' + esc(u.curso) + '</td><td>' + esc(u.premio) + '</td><td class="text-center">' + u.pts + '</td><td>' + esc(u.profesor) + '</td></tr>').join('')
    : '<tr><td colspan="6" class="text-center text-muted">Aún no hay canjes en esta feria.</td></tr>';
}
function sonido(){ try { const au = new Audio('sonidos/caja.mp3'); au.volume = 0.8; au.play().catch(() => {}); } catch (e) {} }
const totalSel = sel => Object.keys(sel).reduce((t, id) => t + sel[id] * pm[id].costo, 0);

// Fila de un alumno con los premios de la feria. opts.onCanje se llama tras un canje exitoso.
function filaAlumno(al, opts){
  opts = opts || {};
  const sel = {};
  const d = document.createElement('div'); d.className = 'fa-fila';
  d.innerHTML = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-2"><div><strong>' + esc(al.nombre) + '</strong>' +
    (al.curso ? '<div class="small text-muted">' + esc(al.curso) + '</div>' : '') + '</div><span class="badge bg-warning text-dark fs-6 saldo"></span></div>' +
    '<div class="chips"></div><div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2"><div class="small text-muted resumen"></div>' +
    '<button type="button" class="btn btn-primary btn-sm px-4 bc">Canjear</button></div>';
  const chips = d.querySelector('.chips'), bc = d.querySelector('.bc');
  function pintar(){
    const tot = totalSel(sel), quedan = al.saldo - tot;
    d.classList.toggle('sin-saldo', al.saldo <= 0);
    d.querySelector('.saldo').textContent = 'Saldo: ' + al.saldo + ' pts';
    chips.innerHTML = '';
    P.forEach(p => {
      const q = sel[p.id] || 0, dp = disp[p.id] === null ? Infinity : disp[p.id] - q;
      const sinSaldo = p.costo > quedan, agotado = dp <= 0, off = (sinSaldo || agotado);
      const b = document.createElement('button'); b.type = 'button';
      b.className = 'fchip' + (q ? ' on' : '') + (off && !q ? ' off' : '');
      b.innerHTML = esc(p.nombre) + ' <b>' + p.costo + ' pts</b>' + (q ? ' <span class="n">×' + q + '</span><span class="menos" title="Quitar uno">−</span>' : '') +
        (disp[p.id] !== null ? '<small>' + (disp[p.id] <= 0 ? 'Agotado' : 'Quedan ' + disp[p.id]) + '</small>' : '');
      b.onclick = e => {
        if (e.target.classList.contains('menos')) { sel[p.id]--; if (!sel[p.id]) delete sel[p.id]; pintar(); return; }
        if (off) return;
        sel[p.id] = (sel[p.id] || 0) + 1; pintar();
      };
      chips.appendChild(b);
    });
    d.querySelector('.resumen').textContent = tot ? 'Total: ' + tot + ' pts · Quedarían: ' + quedan + ' pts' : 'Selecciona uno o más premios';
    bc.disabled = !tot || quedan < 0;
  }
  bc.onclick = async () => {
    const tot = totalSel(sel);
    const nombres = Object.keys(sel).map(id => pm[id].nombre + (sel[id] > 1 ? ' ×' + sel[id] : '')).join(', ');
    if (!confirm('¿Canjear ' + nombres + ' (' + tot + ' pts) para ' + al.nombre + '?')) return;
    bc.disabled = true;
    const fd = new FormData(); fd.append('csrf', CSRF); fd.append('feria_id', FERIA); fd.append('alumno_id', al.id); fd.append('items', JSON.stringify(sel));
    try {
      const r = await fetch('feria.php?a=canjear', { method: 'POST', body: fd, credentials: 'same-origin' });
      const x = await r.json();
      if (x.ok) {
        al.saldo = x.saldo; Object.keys(sel).forEach(k => delete sel[k]);
        Object.keys(x.disp).forEach(k => disp[k] = x.disp[k]);
        sonido(); aviso('ok', '✅ ' + al.nombre + ': ' + x.premios + ' (' + x.total + ' pts). Saldo: ' + x.saldo + ' pts');
        filas.forEach(f => f.pintar());
        if (x.ultimos) pintarUltimos(x.ultimos);
        if (opts.onCanje) opts.onCanje();
      } else { aviso('err', x.error || 'No se pudo canjear.'); pintar(); }
    } catch (e) { aviso('err', 'Error de conexión. Revisa tu internet e inténtalo de nuevo.'); pintar(); }
  };
  const f = { el: d, al, pintar, bc }; filas.push(f); pintar();
  return f;
}

// ---------------- Modo 1: por curso ----------------
let alumnosCurso = [];
function pintarLista(){
  const cont = $('listaAlumnos'), q = $('buscar').value.trim().toLowerCase(), solo = $('soloSaldo').checked;
  filas = filas.filter(f => !cont.contains(f.el));
  cont.innerHTML = '';
  const vis = alumnosCurso.filter(a => (!solo || a.saldo > 0) && (!q || a.nombre.toLowerCase().includes(q)));
  if (!vis.length) { cont.innerHTML = '<p class="text-muted small mb-0">' + (alumnosCurso.length ? 'Ningún alumno coincide (revisa «Solo con saldo» o la búsqueda).' : 'Este curso no tiene alumnos.') + '</p>'; return; }
  vis.forEach(a => cont.appendChild(filaAlumno(a).el));
}
$('selCurso').onchange = async function(){
  $('buscar').disabled = !this.value; $('buscar').value = '';
  if (!this.value) { alumnosCurso = []; $('listaAlumnos').innerHTML = '<p class="text-muted small mb-0">Elige un curso para ver a sus alumnos.</p>'; return; }
  $('listaAlumnos').innerHTML = '<p class="text-muted small mb-0">Cargando...</p>';
  try { const r = await fetch('feria.php?a=curso&curso=' + encodeURIComponent(this.value), { credentials: 'same-origin' }); alumnosCurso = (await r.json()).alumnos || []; pintarLista(); }
  catch (e) { $('listaAlumnos').innerHTML = '<p class="text-danger small mb-0">No se pudo cargar el curso.</p>'; }
};
$('buscar').oninput = pintarLista; $('soloSaldo').onchange = pintarLista;

// ---------------- Modo 2: tarjeta NFC o QR ----------------
let nfcAbort = null, qrScanner = null, modoScan = null, ocupadoScan = false, ultimoNFC = { codigo: '', t: 0 };
const estadoScan = t => $('estadoScan').textContent = t;
function detenerNFC(){ if (nfcAbort) { try { nfcAbort.abort(); } catch (e) {} nfcAbort = null; } }
async function detenerQR(){ if (qrScanner) { try { await qrScanner.clear(); } catch (e) {} qrScanner = null; } $('lectorQR').style.display = 'none'; }
async function detenerTodo(){ detenerNFC(); await detenerQR(); }

async function cargarPorCodigo(codigo){
  if (ocupadoScan || !codigo) return false;
  ocupadoScan = true;
  try {
    const d = await (await fetch('buscar_alumno.php?uid=' + encodeURIComponent(codigo), { credentials: 'same-origin' })).json();
    if (d.error) { estadoScan('⚠️ ' + d.error); return false; }
    const a = await (await fetch('feria.php?a=alumno&id=' + d.id, { credentials: 'same-origin' })).json();
    if (a.error) { estadoScan('⚠️ ' + a.error); return false; }
    await detenerTodo();
    mostrarEscaneado(a);
    return true;
  } catch (e) { estadoScan('⚠️ Error al consultar el alumno'); return false; }
  finally { ocupadoScan = false; }
}
function mostrarEscaneado(a){
  const cont = $('resScan');
  filas = filas.filter(f => !cont.contains(f.el));
  cont.innerHTML = '';
  const f = filaAlumno(a, { onCanje: () => setTimeout(reanudar, 900) });
  cont.appendChild(f.el);
  const sig = document.createElement('button'); sig.type = 'button'; sig.className = 'btn btn-outline-secondary btn-sm w-100 mb-2';
  sig.textContent = 'Cancelar y leer otra tarjeta'; sig.onclick = reanudar; cont.appendChild(sig);
  estadoScan('');
}
function reanudar(){
  const cont = $('resScan'); filas = filas.filter(f => !cont.contains(f.el)); cont.innerHTML = '';
  if (modoScan === 'qr') iniciarQR(); else if (modoScan === 'nfc') iniciarNFC();
}
function extraerCodigosNFC(ev){
  const c = [];
  if (ev.serialNumber) c.push(ev.serialNumber.replace(/[^0-9a-fA-F]/g, '').toUpperCase());
  if (ev.message && ev.message.records) for (const r of ev.message.records) {
    if (r.recordType === 'text' || r.recordType === 'url') { try { const t = new TextDecoder(r.recordType === 'text' ? (r.encoding || 'utf-8') : 'utf-8').decode(r.data).trim(); if (t) c.push(t); } catch (e) {} }
  }
  return c.filter(x => x);
}
async function procesarNFC(ev){
  const codigos = extraerCodigosNFC(ev);
  if (!codigos.length) { estadoScan('⚠️ Tarjeta sin datos legibles. Prueba con otra o usa el QR.'); return; }
  const t = Date.now();
  if (codigos[0] === ultimoNFC.codigo && t - ultimoNFC.t < 2000) return;
  ultimoNFC = { codigo: codigos[0], t };
  if (ocupadoScan) return;
  if (navigator.vibrate) navigator.vibrate(60);
  estadoScan('⏳ Procesando tarjeta...');
  for (const c of codigos) { if (await cargarPorCodigo(c)) return; }
}
async function iniciarNFC(){
  modoScan = 'nfc'; await detenerQR();
  if (!window.isSecureContext) { estadoScan('⚠️ El NFC solo funciona en un sitio con HTTPS.'); return; }
  if (!('NDEFReader' in window)) { estadoScan('⚠️ Este navegador no lee NFC (requiere Chrome en Android). Usa el lector QR.'); return; }
  detenerNFC();
  try {
    const l = new NDEFReader();
    l.addEventListener('reading', procesarNFC);
    l.addEventListener('readingerror', () => estadoScan('⚠️ No se pudo leer la tarjeta. Acércala de nuevo y mantenla quieta un segundo.'));
    nfcAbort = new AbortController();
    await l.scan({ signal: nfcAbort.signal });
    estadoScan('🛜 Listo. Acerca la tarjeta a la parte trasera del teléfono.');
  } catch (e) {
    nfcAbort = null;
    estadoScan(e && e.name === 'NotAllowedError' ? '⚠️ Permiso de NFC denegado. Actívalo en los permisos del sitio y vuelve a intentar.' : '⚠️ No se pudo iniciar el NFC: ' + ((e && e.message) || e) + '. Revisa que el NFC esté activado.');
  }
}
async function iniciarQR(){
  modoScan = 'qr'; detenerNFC();
  if (typeof Html5QrcodeScanner === 'undefined') { estadoScan('⚠️ Cargando librería QR... intenta de nuevo en un momento.'); return; }
  await detenerQR();
  $('lectorQR').style.display = 'block';
  try {
    qrScanner = new Html5QrcodeScanner('lectorQR', { fps: 10, qrbox: { width: 220, height: 220 }, rememberLastUsedCamera: true, supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA] }, false);
    qrScanner.render(async txt => { await cargarPorCodigo(txt); }, () => {});
    estadoScan('📷 Apunta la cámara al código QR del alumno.');
  } catch (e) { $('lectorQR').style.display = 'none'; estadoScan('⚠️ Error al iniciar el escáner QR'); }
}
(function(){
  if (!window.isSecureContext || !('NDEFReader' in window)) {
    const b = $('btnNFC'); b.classList.replace('btn-dark', 'btn-outline-secondary');
    estadoScan('El NFC solo funciona con Chrome en Android (y HTTPS). En iPhone o PC usa el código QR.');
  }
})();
</script>
<?php endif; ?>
</body></html>
