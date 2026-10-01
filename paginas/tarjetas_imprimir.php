<?php
require_once 'conexion.php'; requiere_login();

$esApoderado = ($_GET['tipo'] ?? '') === 'apoderado';

$fc  = (int)($_GET['curso'] ?? 0);
$fal = trim($_GET['alumno'] ?? '');

$types=''; $vals=[];
$sql = "SELECT al.nombre, al.qr_code, al.qr_apoderado, c.nombre curso
        FROM alumnos al JOIN cursos c ON c.id=al.curso_id
        WHERE 1=1";
if ($fc)  { $sql.=" AND c.id=?";  $types.='i'; $vals[]=$fc; }
if ($fal !== '') { $sql.=" AND al.nombre LIKE ?"; $types.='s'; $vals[]='%'.$fal.'%'; }
if ($esApoderado) { $sql.=" AND al.qr_apoderado IS NOT NULL"; }
$sql.=" ORDER BY c.nombre, al.nombre";
$s=$conn->prepare($sql); if($vals) $s->bind_param($types,...$vals); $s->execute();
$alumnos = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Mismo formato de URL que usa contenido.php para el QR del apoderado, para que
// al escanearlo abra directamente reporte_apoderado.php con el token correcto.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443 ? "https://" : "http://";
$baseUrlApoderado = $protocol . ($_SERVER['HTTP_HOST'] ?? '') . '/reporte_apoderado.php?token=';
$codigos = $esApoderado
  ? array_map(fn($a) => $baseUrlApoderado . $a['qr_apoderado'], $alumnos)
  : array_column($alumnos, 'qr_code');
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Tarjetas de alumnos</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
body{font-family:'Quicksand',system-ui,sans-serif;background:#f3f7ef;color:#38452f;margin:16px}
.g{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.t{border:2px dashed <?= $esApoderado ? '#e7c9a9' : '#b7dcb9' ?>;border-radius:14px;padding:14px;text-align:center;break-inside:avoid;background:#fff}
.t img.logo{width:36px;height:36px;object-fit:contain;margin-bottom:4px}
.t div.q{display:flex;justify-content:center;margin:6px 0}
.t b{display:block;margin-top:4px}
.t small{color:#7c8b72}
.t .etiqueta{display:inline-block;background:#d99a5b;color:#fff;font-size:11px;font-weight:700;letter-spacing:.5px;border-radius:6px;padding:2px 8px;margin-bottom:6px}
@media print{.np{display:none}}
</style></head><body>
<h3>Tarjetas de <?= $esApoderado ? 'apoderado' : 'alumno' ?> (<?= count($alumnos) ?>) <button class="np" onclick="print()">Imprimir / Guardar como PDF</button></h3>
<?php if($esApoderado): ?><p class="text-muted">Alumnos sin QR de apoderado generado quedan fuera de este listado (edítalos en Contenido para generarlo).</p><?php endif; ?>
<div class="g"><?php foreach($alumnos as $i=>$a): ?>
  <div class="t">
    <?php if($esApoderado): ?><span class="etiqueta">APODERADO</span><br><?php endif; ?>
    <img src="assets/logo_alerces.png" class="logo" alt="">
    <div class="q" id="q<?= $i ?>"></div>
    <b><?= h($a['nombre']) ?></b>
    <small><?= h($a['curso']) ?></small>
  </div>
<?php endforeach; if(!$alumnos) echo "<p>No hay alumnos para este filtro.</p>"; ?></div>
<script>const D=<?= json_encode($codigos, JSON_HEX_TAG) ?>;
D.forEach((c,i)=>new QRCode(document.getElementById('q'+i),{text:c,width:120,height:120}));</script>
</body></html>
