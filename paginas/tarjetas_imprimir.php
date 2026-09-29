<?php
require_once 'conexion.php'; requiere_login();

$fc  = (int)($_GET['curso'] ?? 0);
$fa  = (int)($_GET['asignatura'] ?? 0);
$fd  = es_admin() ? (int)($_GET['docente'] ?? 0) : 0;
$fal = trim($_GET['alumno'] ?? '');

$types=''; $vals=[];
$sql = "SELECT al.nombre, al.qr_code, c.nombre curso, ag.nombre asignatura
        FROM alumnos al JOIN cursos c ON c.id=al.curso_id JOIN asignaturas ag ON ag.id=c.asignatura_id
        WHERE 1=1";
filtro_docente($sql,$types,$vals,'c');
if ($fc)  { $sql.=" AND c.id=?";  $types.='i'; $vals[]=$fc; }
if ($fa)  { $sql.=" AND ag.id=?"; $types.='i'; $vals[]=$fa; }
if (es_admin() && $fd) { $sql.=" AND c.docente_id=?"; $types.='i'; $vals[]=$fd; }
if ($fal !== '') { $sql.=" AND al.nombre LIKE ?"; $types.='s'; $vals[]='%'.$fal.'%'; }
$sql.=" ORDER BY c.nombre, al.nombre";
$s=$conn->prepare($sql); if($vals) $s->bind_param($types,...$vals); $s->execute();
$alumnos = $s->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Tarjetas de alumnos</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
body{font-family:'Quicksand',system-ui,sans-serif;background:#f3f7ef;color:#38452f;margin:16px}
.g{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.t{border:2px dashed #b7dcb9;border-radius:14px;padding:14px;text-align:center;break-inside:avoid;background:#fff}
.t img.logo{width:36px;height:36px;object-fit:contain;margin-bottom:4px}
.t div.q{display:flex;justify-content:center;margin:6px 0}
.t b{display:block;margin-top:4px}
.t small{color:#7c8b72}
@media print{.np{display:none}}
</style></head><body>
<h3>Tarjetas de alumnos (<?= count($alumnos) ?>) <button class="np" onclick="print()">Imprimir / Guardar como PDF</button></h3>
<div class="g"><?php foreach($alumnos as $i=>$a): ?>
  <div class="t">
    <img src="assets/logo_alerces.png" class="logo" alt="">
    <div class="q" id="q<?= $i ?>"></div>
    <b><?= h($a['nombre']) ?></b>
    <small><?= h($a['curso']) ?> · <?= h($a['asignatura']) ?></small>
  </div>
<?php endforeach; if(!$alumnos) echo "<p>No hay alumnos para este filtro.</p>"; ?></div>
<script>const D=<?= json_encode(array_column($alumnos,'qr_code'), JSON_HEX_TAG) ?>;
D.forEach((c,i)=>new QRCode(document.getElementById('q'+i),{text:c,width:120,height:120}));</script>
</body></html>
