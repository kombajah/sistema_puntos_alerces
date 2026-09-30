<?php
require_once 'conexion.php'; requiere_login();
$types=''; $vals=[];
$sqlC = "SELECT c.id, c.nombre, m.usuario docente FROM cursos c JOIN maestros m ON m.id=c.docente_id WHERE 1=1";
filtro_docente($sqlC,$types,$vals,'c');
$s=$conn->prepare($sqlC." ORDER BY c.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$cursos = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$cats = $conn->query("SELECT * FROM categorias ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$filtro = (int)($_GET['curso'] ?? 0);

// --- Totales por curso (para las barras y para agrupar por asignatura) ---
$types=''; $vals=[];
$sqlTot = "SELECT c.id, c.nombre curso, ag.nombre asignatura,
  COALESCE((SELECT SUM(r.puntos) FROM registro_puntos r JOIN alumnos al ON al.id=r.alumno_id WHERE al.curso_id=c.id),0) total
  FROM cursos c JOIN asignaturas ag ON ag.id=c.asignatura_id WHERE 1=1";
filtro_docente($sqlTot,$types,$vals,'c');
$s=$conn->prepare($sqlTot." ORDER BY total DESC"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$totalesCurso = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$totalesAsignatura = [];
foreach ($totalesCurso as $row) { $totalesAsignatura[$row['asignatura']] = ($totalesAsignatura[$row['asignatura']] ?? 0) + $row['total']; }
arsort($totalesAsignatura);

// --- Metas de la semana actual (widget) ---
$hoy = new DateTime(); $lunesHoy = (clone $hoy)->modify('monday this week')->format('Y-m-d');
$types=''; $vals=[];
$sqlM = "SELECT mt.puntos_objetivo, c.nombre curso, ag.nombre asignatura,
  COALESCE((SELECT SUM(r.puntos) FROM registro_puntos r JOIN alumnos al ON al.id=r.alumno_id
            WHERE al.curso_id=c.id AND r.fecha >= mt.semana_inicio AND r.fecha < DATE_ADD(mt.semana_inicio, INTERVAL 7 DAY)),0) avance
  FROM metas mt JOIN cursos c ON c.id=mt.curso_id JOIN asignaturas ag ON ag.id=c.asignatura_id
  WHERE mt.semana_inicio = ?";
filtro_docente($sqlM,$types,$vals,'c');
$types = 's'.$types; array_unshift($vals, $lunesHoy);
$s=$conn->prepare($sqlM); $s->bind_param($types,...$vals); $s->execute();
$metasSemana = $s->get_result()->fetch_all(MYSQLI_ASSOC);

$types=''; $vals=[];
$sql = "SELECT a.id, a.nombre alumno, c.nombre curso, ag.nombre asignatura, r.categoria_id, SUM(r.puntos) pts
        FROM alumnos a JOIN cursos c ON a.curso_id=c.id JOIN asignaturas ag ON ag.id=c.asignatura_id
        LEFT JOIN registro_puntos r ON r.alumno_id=a.id WHERE 1=1";
filtro_docente($sql,$types,$vals,'c');
if ($filtro) { $sql .= " AND c.id=?"; $types.='i'; $vals[]=$filtro; }
$sql .= " GROUP BY a.id, a.nombre, c.nombre, ag.nombre, r.categoria_id ORDER BY c.nombre, a.nombre";
$s = $conn->prepare($sql); if ($vals) $s->bind_param($types, ...$vals); $s->execute();

$filas = [];
foreach ($s->get_result() as $r) {
  $id = $r['id'];
  $filas[$id] ??= ['alumno'=>$r['alumno'],'curso'=>$r['curso'],'asignatura'=>$r['asignatura'],'cat'=>[],'total'=>0];
  if ($r['categoria_id']) { $filas[$id]['cat'][$r['categoria_id']] = (int)$r['pts']; $filas[$id]['total'] += (int)$r['pts']; }
}
$can=[]; foreach($conn->query('SELECT alumno_id, SUM(puntos_virtuales) t FROM canjes GROUP BY alumno_id') as $q) $can[$q['alumno_id']]=(int)$q['t'];
foreach($filas as $id=>&$f) $f['canje']=$can[$id]??0; unset($f);
uasort($filas, fn($x,$y)=>[$x['curso'],-$x['total']]<=>[$y['curso'],-$y['total']]);
?>
<!DOCTYPE html><html lang="es"><head><title>Reportería</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">

  <?php if($metasSemana): ?>
  <div class="card p-3 shadow-sm mb-3">
    <h5 class="mb-3">🎯 Metas de esta semana</h5>
    <?php foreach($metasSemana as $m): $pct = $m['puntos_objetivo']>0 ? min(100, round($m['avance']/$m['puntos_objetivo']*100)) : 0; $ok = $m['avance']>=$m['puntos_objetivo']; ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between small"><span><?= h($m['curso']) ?> · <?= h($m['asignatura']) ?></span><span><?= (int)$m['avance'] ?> / <?= (int)$m['puntos_objetivo'] ?> pts</span></div>
        <div style="background:#e6efe0;border-radius:8px;overflow:hidden;height:12px">
          <div style="width:<?= $pct ?>%;background:<?= $ok?'linear-gradient(90deg,#8fbf94,#4d7c50)':'linear-gradient(90deg,#d99a5b,#e7c9a9)' ?>;height:100%"></div>
        </div>
      </div>
    <?php endforeach; ?>
    <a href="metas.php" class="small">Gestionar metas →</a>
  </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-6 mb-3"><div class="card p-3 shadow-sm h-100">
      <h5 class="mb-3">Puntos totales por curso</h5>
      <?php $max = max(array_column($totalesCurso,'total')) ?: 1; ?>
      <?php foreach($totalesCurso as $row): $pct = round($row['total']/$max*100); ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small"><span><?= h($row['curso']) ?> · <?= h($row['asignatura']) ?></span><span><?= (int)$row['total'] ?> pts</span></div>
          <div style="background:#e6efe0;border-radius:8px;overflow:hidden;height:12px"><div style="width:<?= $pct ?>%;background:linear-gradient(90deg,#a8d5ba,#6ea36f);height:100%"></div></div>
        </div>
      <?php endforeach; if(!$totalesCurso) echo "<p class='text-muted small mb-0'>Sin datos aún.</p>"; ?>
    </div></div>
    <div class="col-md-6 mb-3"><div class="card p-3 shadow-sm h-100">
      <h5 class="mb-3">Puntos totales por asignatura</h5>
      <?php $maxAg = $totalesAsignatura ? max($totalesAsignatura) : 1; ?>
      <?php foreach($totalesAsignatura as $nombreAg=>$total): $pct = round($total/$maxAg*100); ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small"><span><?= h($nombreAg) ?></span><span><?= (int)$total ?> pts</span></div>
          <div style="background:#e6efe0;border-radius:8px;overflow:hidden;height:12px"><div style="width:<?= $pct ?>%;background:linear-gradient(90deg,#e7c9a9,#d99a5b);height:100%"></div></div>
        </div>
      <?php endforeach; if(!$totalesAsignatura) echo "<p class='text-muted small mb-0'>Sin datos aún.</p>"; ?>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Dashboard de Puntos</h3>
    <form method="GET"><select name="curso" class="form-select" onchange="this.form.submit()">
      <option value="0">Todos mis cursos</option>
      <?php foreach($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $filtro==$c['id']?'selected':'' ?>><?= h($c['nombre']) ?><?= es_admin()?' — '.h($c['docente']):'' ?></option><?php endforeach; ?>
    </select></form>
  </div>
  <div class="card p-3 shadow-sm table-responsive">
    <table class="table table-striped align-middle">
      <thead><tr><th>Curso</th><th>Asignatura</th><th>Alumno</th>
        <?php foreach($cats as $k): ?><th class="text-center"><?= h($k['nombre']) ?></th><?php endforeach; ?>
        <th class="text-center">Total</th><th class="text-center">Canjeado</th><th class="text-center">Saldo</th></tr></thead>
      <tbody>
      <?php foreach($filas as $f): ?>
        <tr><td><?= h($f['curso']) ?></td><td><?= h($f['asignatura']) ?></td><td><?= h($f['alumno']) ?></td>
          <?php foreach($cats as $k): ?><td class="text-center"><?= $f['cat'][$k['id']] ?? 0 ?></td><?php endforeach; ?>
          <td class="text-center"><strong><?= $f['total'] ?> pts</strong></td>
          <td class="text-center"><?= $f['canje'] ?></td>
          <td class="text-center"><strong><?= $f['total']-$f['canje'] ?></strong></td></tr>
      <?php endforeach; if(!$filas) echo "<tr><td colspan='".(6+count($cats))."' class='text-center text-muted'>Sin datos</td></tr>"; ?>
      </tbody>
    </table>
  </div>
</div><?php include 'footer.php'; ?>
</body></html>
