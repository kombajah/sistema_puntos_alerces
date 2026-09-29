<?php
require_once 'conexion.php';

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
  die("Acceso denegado: Token no proporcionado.");
}

// 1. Consultar información general del alumno
$stmt = $conn->prepare("
  SELECT a.id, a.nombre AS alumno_nombre, c.nombre AS curso_nombre, ag.nombre AS asignatura_nombre
  FROM alumnos a
  JOIN cursos c ON c.id = a.curso_id
  JOIN asignaturas ag ON ag.id = c.asignatura_id
  WHERE a.qr_apoderado = ?
");
$stmt->bind_param("s", $token);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

if (!$alumno) {
  die("Acceso denegado: Token inválido.");
}

$alumno_id = (int)$alumno['id'];

// 2. Consultar desglose de puntos por categoría
// Asume que existe una tabla 'puntos' relacionada con 'alumnos' y 'categorias'
$stmtPuntos = $conn->prepare("
  SELECT cat.nombre AS categoria, COALESCE(SUM(p.puntos), 0) AS total_puntos
  FROM categorias cat
  LEFT JOIN puntos p ON p.categoria_id = cat.id AND p.alumno_id = ?
  GROUP BY cat.id, cat.nombre
  ORDER BY cat.nombre ASC
");
$stmtPuntos->bind_param("i", $alumno_id);
$stmtPuntos->execute();
$reporteCategorias = $stmtPuntos->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Obtener el total de puntos general
$totalGeneral = array_sum(array_column($reporteCategorias, 'total_puntos'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reporte de Alumno - Apoderados</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 600px;">
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body text-center bg-primary text-white rounded-top">
      <h4 class="mb-1"><?= htmlspecialchars($alumno['alumno_nombre']) ?></h4>
      <p class="mb-0 text-white-50"><?= htmlspecialchars($alumno['curso_nombre']) ?> · <?= htmlspecialchars($alumno['asignatura_nombre']) ?></p>
    </div>
    <div class="card-body text-center border-bottom">
      <span class="text-muted d-block uppercase text-uppercase fw-bold small">Puntaje Total</span>
      <h1 class="display-4 fw-bold text-success mb-0"><?= $totalGeneral ?></h1>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
      <h5 class="mb-0 text-primary">Detalle por Categoría</h5>
    </div>
    <ul class="list-group list-group-flush">
      <?php if (empty($reporteCategorias)): ?>
        <li class="list-group-item text-center text-muted py-3">No hay categorías registradas.</li>
      <?php else: ?>
        <?php foreach ($reporteCategorias as $cat): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center py-3">
            <span class="fw-semibold"><?= htmlspecialchars($cat['categoria']) ?></span>
            <span class="badge bg-primary rounded-pill fs-6"><?= (int)$cat['total_puntos'] ?> pts</span>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </div>
</div>
</body>
</html>