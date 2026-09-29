<?php
require_once 'conexion.php'; requiere_login();
if (!es_admin()) { http_response_code(403); die("Solo el administrador puede usar la carga masiva."); }

function _nuevo_qr(){ return bin2hex(random_bytes(6)); }

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $resultado = ['ok'=>0, 'errores'=>[['linea'=>0,'motivo'=>'No se recibió un archivo válido (revisa que sea .csv).']]];
  } else {
    $fh = fopen($_FILES['archivo']['tmp_name'], 'r');
    if (!$fh) {
      $resultado = ['ok'=>0, 'errores'=>[['linea'=>0,'motivo'=>'No se pudo leer el archivo.']]];
    } else {
      $bom = fread($fh, 3); if ($bom !== "\xEF\xBB\xBF") rewind($fh);
      $header = fgetcsv($fh, 0, ',');
      if (!$header) {
        $resultado = ['ok'=>0, 'errores'=>[['linea'=>1,'motivo'=>'Archivo vacío o con formato inválido.']]];
      } else {
        $header = array_map(fn($c) => strtolower(trim((string)$c)), $header);
        $idx = array_flip($header);
        $faltan = array_diff(['docente_usuario','asignatura','curso','alumno'], array_keys($idx));
        if ($faltan) {
          $resultado = ['ok'=>0, 'errores'=>[['linea'=>1,'motivo'=>'Faltan columnas obligatorias: '.implode(', ', $faltan)]]];
        } else {
          $ok = 0; $errores = []; $linea = 1;
          while (($fila = fgetcsv($fh, 0, ',')) !== false) {
            $linea++;
            if (count(array_filter($fila, fn($v) => trim((string)$v) !== '')) === 0) continue; // fila vacía
            try {
              $docU = trim($fila[$idx['docente_usuario']] ?? '');
              $agN  = trim($fila[$idx['asignatura']] ?? '');
              $curN = trim($fila[$idx['curso']] ?? '');
              $alN  = trim($fila[$idx['alumno']] ?? '');
              $nfc  = isset($idx['nfc_uid']) ? trim($fila[$idx['nfc_uid']] ?? '') : '';
              if ($docU === '' || $agN === '' || $curN === '' || $alN === '') throw new Exception('Faltan datos obligatorios en la fila.');

              $s = $conn->prepare("SELECT id FROM maestros WHERE usuario=?");
              $s->bind_param("s", $docU); $s->execute();
              $doc = $s->get_result()->fetch_assoc();
              if (!$doc) throw new Exception("El docente '$docU' no existe (créalo primero en Maestros).");
              $did = (int)$doc['id'];

              $s = $conn->prepare("SELECT id FROM asignaturas WHERE nombre=? AND docente_id=?");
              $s->bind_param("si", $agN, $did); $s->execute();
              $ag = $s->get_result()->fetch_assoc();
              if ($ag) { $agId = (int)$ag['id']; }
              else {
                $s = $conn->prepare("INSERT INTO asignaturas (nombre,docente_id) VALUES (?,?)");
                $s->bind_param("si", $agN, $did); $s->execute(); $agId = $conn->insert_id;
              }

              $s = $conn->prepare("SELECT id FROM cursos WHERE nombre=? AND asignatura_id=? AND docente_id=?");
              $s->bind_param("sii", $curN, $agId, $did); $s->execute();
              $cur = $s->get_result()->fetch_assoc();
              if ($cur) { $curId = (int)$cur['id']; }
              else {
                $s = $conn->prepare("INSERT INTO cursos (nombre,docente_id,asignatura_id) VALUES (?,?,?)");
                $s->bind_param("sii", $curN, $did, $agId); $s->execute(); $curId = $conn->insert_id;
              }

              $s = $conn->prepare("SELECT COUNT(*) t FROM alumnos WHERE curso_id=?");
              $s->bind_param("i", $curId); $s->execute();
              if ($s->get_result()->fetch_assoc()['t'] >= 50) throw new Exception("El curso '$curN' ($agN, $docU) ya tiene 50 alumnos.");

              $qr = _nuevo_qr();
              $nfcVal = $nfc !== '' ? $nfc : null;
              $s = $conn->prepare("INSERT INTO alumnos (curso_id,nombre,nfc_uid,qr_code) VALUES (?,?,?,?)");
              $s->bind_param("isss", $curId, $alN, $nfcVal, $qr); $s->execute();
              $ok++;
            } catch (mysqli_sql_exception $e) {
              $errores[] = ['linea'=>$linea, 'motivo'=>'Tarjeta NFC duplicada u otro conflicto de datos.'];
            } catch (Exception $e) {
              $errores[] = ['linea'=>$linea, 'motivo'=>$e->getMessage()];
            }
          }
          $resultado = ['ok'=>$ok, 'errores'=>$errores];
        }
      }
      fclose($fh);
    }
  }
}
?>
<!DOCTYPE html><html lang="es"><head><title>Carga masiva</title><?php include 'head.php'; ?></head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
  <div class="card p-4 shadow-sm mb-3">
    <h4 class="text-primary mb-3">Carga masiva de alumnos</h4>
    <p class="text-muted">Sube un archivo <b>.csv</b> con las columnas <code>docente_usuario, asignatura, curso, alumno, nfc_uid</code>.
      Si la asignatura o el curso no existen para ese docente, se crean automáticamente. La columna <code>nfc_uid</code> es opcional.</p>
    <a href="plantilla_alumnos.php" class="btn btn-outline-primary mb-3">⬇️ Descargar plantilla CSV</a>
    <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
      <input type="file" name="archivo" accept=".csv" class="form-control" style="max-width:320px" required>
      <button class="btn btn-primary">Cargar alumnos</button>
    </form>
    <small class="text-muted d-block mt-2">¿Tienes un archivo Excel (.xlsx)? Ábrelo y usa "Guardar como" → CSV (delimitado por comas) antes de subirlo.</small>
  </div>

  <?php if ($resultado): ?>
  <div class="card p-4 shadow-sm">
    <div class="alert <?= $resultado['ok']>0 ? 'alert-success' : 'alert-warning' ?>">
      <?= (int)$resultado['ok'] ?> alumno(s) registrados correctamente.
      <?php if($resultado['errores']): ?> <?= count($resultado['errores']) ?> fila(s) con error.<?php endif; ?>
    </div>
    <?php if($resultado['errores']): ?>
    <table class="table table-sm">
      <thead><tr><th>Línea</th><th>Motivo</th></tr></thead>
      <tbody><?php foreach($resultado['errores'] as $e): ?>
        <tr><td><?= (int)$e['linea'] ?></td><td><?= h($e['motivo']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
</body></html>
