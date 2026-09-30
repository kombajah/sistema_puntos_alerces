<?php
require_once 'conexion.php'; requiere_login();
$mensaje=''; $error='';

function es_mi_curso_meta($conn,$cid){
  $s=$conn->prepare("SELECT docente_id FROM cursos WHERE id=?"); $s->bind_param("i",$cid); $s->execute();
  $r=$s->get_result()->fetch_assoc();
  return $r && (es_admin() || (int)$r['docente_id']===docente_id());
}
function lunes_de_semana_iso($valorWeek){
  // $valorWeek viene de <input type="week"> con formato "YYYY-Www"
  if (!preg_match('/^(\d{4})-W(\d{2})$/', $valorWeek, $m)) return null;
  $dt = new DateTime();
  $dt->setISODate((int)$m[1], (int)$m[2], 1); // 1 = lunes
  return $dt->format('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['crear_meta'])) {
    $cid = (int)$_POST['curso_id'];
    $obj = (int)$_POST['puntos_objetivo'];
    $lunes = lunes_de_semana_iso(trim($_POST['semana'] ?? ''));
    if (!es_mi_curso_meta($conn,$cid)) $error = "Ese curso no te pertenece.";
    elseif (!$lunes) $error = "Elige una semana válida.";
    elseif ($obj < 1) $error = "El puntaje objetivo debe ser mayor a 0.";
    else {
      try {
        $s = $conn->prepare("INSERT INTO metas (curso_id,semana_inicio,puntos_objetivo,creado_por) VALUES (?,?,?,?)");
        $mid = docente_id();
        $s->bind_param("isii", $cid, $lunes, $obj, $mid); $s->execute();
        $mensaje = "Meta creada.";
      } catch (mysqli_sql_exception $e) { $error = "Ya existe una meta para ese curso en esa semana."; }
    }
  } elseif (isset($_POST['borrar_meta'])) {
    $id = (int)$_POST['id'];
    $s=$conn->prepare("SELECT curso_id FROM metas WHERE id=?"); $s->bind_param("i",$id); $s->execute();
    $m=$s->get_result()->fetch_assoc();
    if (!$m || !es_mi_curso_meta($conn,$m['curso_id'])) $error = "No tienes permiso sobre esa meta.";
    else { $s=$conn->prepare("DELETE FROM metas WHERE id=?"); $s->bind_param("i",$id); $s->execute(); $mensaje="Meta eliminada."; }
  }
}

$types=''; $vals=[];
$sqlC = "SELECT c.id, c.nombre, ag.nombre asignatura, m.usuario docente FROM cursos c
         JOIN asignaturas ag ON ag.id=c.asignatura_id JOIN maestros m ON m.id=c.docente_id WHERE 1=1";
filtro_docente($sqlC,$types,$vals,'c');
$s=$conn->prepare($sqlC." ORDER BY c.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$cursos = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$types=''; $vals=[];
$sql = "SELECT mt.id, mt.semana_inicio, mt.puntos_objetivo, c.nombre curso, ag.nombre asignatura, m.usuario docente,
  COALESCE((SELECT SUM(r.puntos) FROM registro_puntos r JOIN alumnos al ON al.id=r.alumno_id
            WHERE al.curso_id=c.id AND r.fecha >= mt.semana_inicio AND r.fecha < DATE_ADD(mt.semana_inicio, INTERVAL 7 DAY)),0) avance
  FROM metas mt JOIN cursos c ON c.id=mt.curso_id JOIN asignaturas ag ON ag.id=c.asignatura_id JOIN maestros m ON m.id=c.docente_id
  WHERE 1=1";
filtro_docente($sql,$types,$vals,'c');
$sql .= " ORDER BY mt.semana_inicio DESC, c.nombre";
$s=$conn->prepare($sql); if($vals) $s->bind_param($types,...$vals); $s->execute();
$metas = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$semanaActual = (new DateTime())->format('o-\WW');
?>
<!DOCTYPE html><html lang="es"><head><title>Metas semanales</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <?php if($mensaje) echo "<div class='alert alert-success'>".h($mensaje)."</div>"; ?>
  <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>

  <div class="card p-4 shadow-sm mb-3">
    <h4 class="text-primary mb-3">🎯 Nueva meta semanal</h4>
    <?php if(!$cursos): ?><p class="text-muted">Primero crea un curso en Contenido.</p><?php else: ?>
    <form method="POST" class="row g-2 align-items-end">
      <div class="col-md-5"><label class="form-label small">Curso</label>
        <select name="curso_id" class="form-select" required>
          <?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?> · <?= h($c['asignatura']) ?><?= es_admin()?' — '.h($c['docente']):'' ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label small">Semana</label>
        <input type="week" name="semana" class="form-control" value="<?= $semanaActual ?>" required></div>
      <div class="col-md-2"><label class="form-label small">Puntos meta</label>
        <input type="number" name="puntos_objetivo" class="form-control" min="1" value="20" required></div>
      <div class="col-md-2"><button name="crear_meta" class="btn btn-primary w-100">Crear meta</button></div>
    </form>
    <?php endif; ?>
  </div>

  <div class="card p-3 shadow-sm">
    <h5 class="mb-3">Metas registradas</h5>
    <?php if(!$metas): ?><p class="text-muted text-center">Aún no hay metas creadas.</p><?php endif; ?>
    <?php foreach($metas as $m):
      $inicio = new DateTime($m['semana_inicio']); $fin = (clone $inicio)->modify('+6 days');
      $pct = $m['puntos_objetivo']>0 ? min(100, round($m['avance']/$m['puntos_objetivo']*100)) : 0;
      $cumplida = $m['avance'] >= $m['puntos_objetivo'];
    ?>
    <div class="border-top py-3">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-1">
        <div><strong><?= h($m['curso']) ?> · <?= h($m['asignatura']) ?></strong><?= es_admin()?' <span class="badge bg-secondary">'.h($m['docente']).'</span>':'' ?>
          <div class="small text-muted"><?= $inicio->format('d/m') ?> — <?= $fin->format('d/m/Y') ?></div></div>
        <div class="text-end">
          <span class="fw-bold"><?= (int)$m['avance'] ?> / <?= (int)$m['puntos_objetivo'] ?> pts</span>
          <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta meta?')">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button name="borrar_meta" class="btn btn-sm btn-outline-danger ms-2">✕</button>
          </form>
        </div>
      </div>
      <div style="background:#e6efe0;border-radius:8px;overflow:hidden;height:16px">
        <div style="width:<?= $pct ?>%;background:<?= $cumplida ? 'linear-gradient(90deg,#8fbf94,#4d7c50)' : 'linear-gradient(90deg,#d99a5b,#e7c9a9)' ?>;height:100%;transition:width .3s"></div>
      </div>
      <div class="small text-muted mt-1"><?= $pct ?>%<?= $cumplida ? ' 🎉 ¡Meta cumplida!' : '' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'footer.php'; ?>
</body></html>
