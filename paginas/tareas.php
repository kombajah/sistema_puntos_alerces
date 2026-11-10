<?php
// Tareas y actividades por curso: los docentes las registran (curso, fecha de entrega y descripción) y los apoderados
// las ven en su reporte mientras la fecha de entrega no haya pasado.
require_once 'conexion.php';
requiere_login();
require_once 'tareas_lib.php';
tareas_asegurar($conn);

$uid = docente_id(); $admin = es_admin();
$hoy = substr(ahora_chile(), 0, 10);
$limiteFecha = date('Y-m-d', strtotime($hoy . ' +365 days'));
$error = '';

$cursos = $conn->query("SELECT id, nombre FROM cursos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$cursosIds = array_map('intval', array_column($cursos, 'id'));

function tarea_fecha_ok(string $f): bool { $d = DateTime::createFromFormat('Y-m-d', $f); return $d && $d->format('Y-m-d') === $f; }
function tarea_cargar($conn, int $id) {
  $s = $conn->prepare("SELECT id, curso_id, descripcion, fecha_entrega, creado_por FROM tareas WHERE id=?");
  $s->bind_param("i", $id); $s->execute();
  return $s->get_result()->fetch_assoc();
}
// Solo quien registró la tarea (o el administrador) puede editarla o eliminarla.
$puede = fn($t) => $t && ($admin || (int)$t['creado_por'] === $uid);

// ---------------------------- Acciones ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validar();
  if (isset($_POST['guardar_tarea'])) {
    $id    = (int)($_POST['id'] ?? 0);
    $curso = (int)($_POST['curso_id'] ?? 0);
    $fecha = trim((string)($_POST['fecha_entrega'] ?? ''));
    $desc  = mb_substr(trim(str_replace(["\r\n", "\r"], "\n", (string)($_POST['descripcion'] ?? ''))), 0, 500);
    $actual = $id ? tarea_cargar($conn, $id) : null;

    if ($id && !$actual) {
      $error = 'Esa tarea ya no existe.';
    } elseif ($id && !$puede($actual)) {
      $error = 'Solo quien registró la tarea o el administrador puede modificarla.';
    } elseif (!in_array($curso, $cursosIds, true)) {
      $error = 'Elige un curso.';
    } elseif (!tarea_fecha_ok($fecha)) {
      $error = 'Elige una fecha de entrega válida.';
    } elseif ($fecha < $hoy && !($actual && $actual['fecha_entrega'] === $fecha)) {
      $error = 'La fecha de entrega no puede ser anterior a hoy.';
    } elseif ($fecha > $limiteFecha) {
      $error = 'La fecha de entrega está demasiado lejos (máximo un año). Revisa que esté bien escrita.';
    } elseif ($desc === '') {
      $error = 'Escribe la descripción de la tarea o actividad.';
    } else {
      $f = ahora_chile();
      if ($id) {
        $s = $conn->prepare("UPDATE tareas SET curso_id=?, descripcion=?, fecha_entrega=?, actualizado_por=?, actualizado_en=? WHERE id=?");
        $s->bind_param("issisi", $curso, $desc, $fecha, $uid, $f, $id); $s->execute();
        $ok = 'actualizada';
      } else {
        $asig = asignatura_docente($conn);
        $s = $conn->prepare("INSERT INTO tareas (curso_id, descripcion, fecha_entrega, asignatura_id, creado_por, creado_en) VALUES (?,?,?,?,?,?)");
        $s->bind_param("issiis", $curso, $desc, $fecha, $asig, $uid, $f); $s->execute();
        $ok = 'creada';
      }
      header('Location: tareas.php?curso=' . $curso . '&ok=' . $ok); exit;
    }
    // Con error se vuelve a mostrar el formulario con lo que escribió.
    $form = ['id' => $id, 'curso_id' => $curso, 'fecha_entrega' => $fecha, 'descripcion' => $desc];
  } elseif (isset($_POST['eliminar_tarea'])) {
    $id = (int)($_POST['id'] ?? 0);
    $t = tarea_cargar($conn, $id);
    if (!$t) { $error = 'Esa tarea ya no existe.'; }
    elseif (!$puede($t)) { $error = 'Solo quien registró la tarea o el administrador puede eliminarla.'; }
    else {
      $s = $conn->prepare("DELETE FROM tareas WHERE id=?"); $s->bind_param("i", $id); $s->execute();
      header('Location: tareas.php?curso=' . (int)$t['curso_id'] . '&ok=eliminada'); exit;
    }
  }
}
$mensaje = ['creada' => 'Tarea registrada. Los apoderados del curso ya la ven en su reporte.', 'actualizada' => 'Tarea actualizada.', 'eliminada' => 'Tarea eliminada.'][$_GET['ok'] ?? ''] ?? '';

// ---------------------------- Datos de la página ----------------------------
$filtroCurso = (int)($_GET['curso'] ?? 0);
if (!in_array($filtroCurso, $cursosIds, true)) $filtroCurso = 0;
$verVencidas = !empty($_GET['vencidas']);

// Formulario: en blanco, con lo que se escribió (si hubo error) o con la tarea que se edita.
if (!isset($form)) {
  $form = ['id' => 0, 'curso_id' => $filtroCurso, 'fecha_entrega' => '', 'descripcion' => ''];
  $ed = (int)($_GET['editar'] ?? 0);
  if ($ed) {
    $t = tarea_cargar($conn, $ed);
    if ($puede($t)) $form = ['id' => (int)$t['id'], 'curso_id' => (int)$t['curso_id'], 'fecha_entrega' => $t['fecha_entrega'], 'descripcion' => $t['descripcion']];
  }
}

$desde = $verVencidas ? date('Y-m-d', strtotime($hoy . ' -30 days')) : $hoy;
$sql = "SELECT t.id, t.curso_id, t.descripcion, t.fecha_entrega, t.creado_por, c.nombre curso, asg.nombre asignatura, " . sql_nombre_maestro('m') . " profesor
        FROM tareas t JOIN cursos c ON c.id=t.curso_id LEFT JOIN asignaturas asg ON asg.id=t.asignatura_id LEFT JOIN maestros m ON m.id=t.creado_por
        WHERE t.fecha_entrega >= ?" . ($filtroCurso ? " AND t.curso_id=?" : "") . " ORDER BY t.fecha_entrega, c.nombre, t.id";
$s = $conn->prepare($sql);
if ($filtroCurso) $s->bind_param("si", $desde, $filtroCurso); else $s->bind_param("s", $desde);
$s->execute();
$tareas = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$qs = fn(array $extra = []) => http_build_query(array_filter(array_merge(['curso' => $filtroCurso ?: null, 'vencidas' => $verVencidas ? 1 : null], $extra), fn($v) => $v !== null));
?>
<!DOCTYPE html><html lang="es"><head><title>Tareas y actividades</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2 mb-4" style="max-width:900px">
  <?php if ($mensaje) echo "<div class='alert alert-success'>" . h($mensaje) . "</div>"; ?>
  <?php if ($error) echo "<div class='alert alert-danger'>" . h($error) . "</div>"; ?>

  <div class="card p-4 shadow-sm mb-3">
    <h4 class="text-primary mb-3"><?= $form['id'] ? '✏️ Editar tarea o actividad' : '📝 Nueva tarea o actividad' ?></h4>
    <?php if (!$cursos): ?>
      <p class="text-muted mb-0">Primero crea un curso en <a href="contenido.php">Cursos</a>.</p>
    <?php else: ?>
    <form method="POST" class="row g-2 align-items-end">
      <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
      <div class="col-md-6">
        <label class="form-label small mb-1">Curso</label>
        <select name="curso_id" class="form-select" required>
          <option value="" disabled <?= $form['curso_id'] ? '' : 'selected' ?>>Elige un curso...</option>
          <?php foreach ($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$form['curso_id'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small mb-1">Fecha de entrega</label>
        <input type="date" name="fecha_entrega" class="form-control" value="<?= h($form['fecha_entrega']) ?>" <?= $form['id'] ? '' : 'min="' . h($hoy) . '"' ?> max="<?= h($limiteFecha) ?>" required>
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">Descripción de la tarea o actividad</label>
        <textarea name="descripcion" id="descTarea" class="form-control" rows="3" maxlength="500" placeholder="Ej: Traer el cuaderno de matemática con los ejercicios de la página 45 resueltos" required><?= h($form['descripcion']) ?></textarea>
        <div class="small text-muted text-end"><span id="cuentaDesc">0</span> / 500</div>
      </div>
      <div class="col-12 d-flex gap-2">
        <button name="guardar_tarea" class="btn btn-primary"><?= $form['id'] ? 'Guardar cambios' : 'Registrar tarea' ?></button>
        <?php if ($form['id']): ?><a href="tareas.php?<?= h($qs()) ?>" class="btn btn-outline-secondary">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <div class="small text-muted mt-2">Los apoderados la ven en el reporte de sus hijos del curso hasta el día de la fecha de entrega (inclusive).</div>
    <?php endif; ?>
  </div>

  <div class="card p-3 shadow-sm">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <h5 class="mb-0 me-auto"><?= $verVencidas ? 'Tareas (incluye vencidas de los últimos 30 días)' : 'Tareas por entregar' ?></h5>
      <form method="GET" class="d-flex gap-2 align-items-center m-0">
        <?php if ($verVencidas): ?><input type="hidden" name="vencidas" value="1"><?php endif; ?>
        <select name="curso" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">Todos los cursos</option>
          <?php foreach ($cursos as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $filtroCurso ? 'selected' : '' ?>><?= h($c['nombre']) ?></option><?php endforeach; ?>
        </select>
      </form>
      <a class="btn btn-sm btn-outline-secondary" href="tareas.php?<?= h(http_build_query(array_filter(['curso' => $filtroCurso ?: null, 'vencidas' => $verVencidas ? null : 1]))) ?>"><?= $verVencidas ? 'Ocultar vencidas' : 'Ver vencidas' ?></a>
    </div>
    <?php if (!$tareas): ?>
      <p class="text-center text-muted my-3">No hay tareas <?= $verVencidas ? 'en este período' : 'pendientes' ?><?= $filtroCurso ? ' para este curso' : '' ?>.</p>
    <?php endif; ?>
    <?php foreach ($tareas as $t): $e = tarea_etiqueta_fecha($t['fecha_entrega'], $hoy); $mia = $puede($t); ?>
      <div class="border-top py-3 <?= $e['dias'] < 0 ? 'opacity-50' : '' ?>">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
          <span class="fw-bold"><?= h(fecha_corta($t['fecha_entrega'])) ?></span>
          <span class="badge <?= h($e['clase']) ?>"><?= h($e['texto']) ?></span>
          <span class="badge text-bg-light border"><?= h($t['curso']) ?></span>
          <?php if ($t['asignatura']): ?><span class="small text-muted"><?= h($t['asignatura']) ?></span><?php endif; ?>
        </div>
        <div style="white-space:pre-line"><?= h($t['descripcion']) ?></div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
          <span class="small text-muted me-auto">Registrada por <?= h($t['profesor'] ?: 'usuario eliminado') ?></span>
          <?php if ($mia): ?>
            <a class="btn btn-sm btn-outline-primary" href="tareas.php?<?= h($qs(['editar' => (int)$t['id']])) ?>">Editar</a>
            <form method="POST" class="m-0" onsubmit="return confirm('¿Eliminar esta tarea? Dejará de verse en el reporte de los apoderados.')">
              <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button name="eliminar_tarea" class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function(){
  var t = document.getElementById('descTarea'), c = document.getElementById('cuentaDesc');
  if (!t || !c) return;
  function act(){ c.textContent = t.value.length; }
  t.addEventListener('input', act); act();
})();
</script>
<?php include 'footer.php'; ?>
</body></html>
