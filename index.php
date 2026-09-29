<?php
// Único punto de entrada desplegado como función Vercel. Todas las páginas viven en
// /paginas y se sirven desde aquí (require interno), para no superar el límite de
// Funciones Serverless del plan Hobby (12). Ver vercel.json.
require_once __DIR__ . '/paginas/conexion.php';
iniciar_sesion();

$permitidas = [
  'reporte.php','nfc.php','canje.php','historico.php','contenido.php','maestros.php',
  'tarjetas.php','tarjetas_imprimir.php','carga_masiva.php','plantilla_alumnos.php',
  'qr.php','buscar_alumno.php','instalar.php','salir.php',
];

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pagina = basename($ruta);

if (in_array($pagina, $permitidas, true)) {
  require __DIR__ . '/paginas/' . $pagina;
  exit;
}

if ($pagina !== '' && $pagina !== 'index.php') {
  http_response_code(404);
  echo "Página no encontrada.";
  exit;
}

// --- Login ---
if (isset($_SESSION['maestro'])) { header("Location: reporte.php"); exit; }
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $u = $_POST['usuario'] ?? '';
  $s = $conn->prepare("SELECT id, password, rol FROM maestros WHERE usuario = ?");
  $s->bind_param("s", $u); $s->execute();
  $r = $s->get_result()->fetch_assoc();
  if ($r && password_verify($_POST['password'] ?? '', $r['password'])) {
    session_regenerate_id(true);
    $_SESSION['maestro'] = $u; $_SESSION['id'] = $r['id']; $_SESSION['rol'] = $r['rol'];
    header("Location: reporte.php"); exit;
  }
  $error = "Usuario o contraseña incorrectos.";
}
?>
<!DOCTYPE html><html lang="es"><head><title>Escuela Los Alerces - Sistema de Puntos</title><?php include __DIR__ . '/paginas/head.php'; ?></head>
<body class="d-flex flex-column" style="min-height:100vh">
  <div class="flex-grow-1 d-flex align-items-center justify-content-center px-3">
    <div class="card login-card p-4 text-center w-100">
      <img src="assets/logo_alerces.png" alt="Escuela Los Alerces" class="login-avatar mx-auto mb-3">
      <h2 class="login-title mb-0">Escuela Los Alerces</h2>
      <p class="login-subtitle mb-4">Acceso Docente</p>
      <form method="POST">
        <?php if($error) echo "<div class='alert alert-danger'>".h($error)."</div>"; ?>
        <input type="text" name="usuario" class="form-control mb-3" placeholder="Usuario" required autofocus>
        <input type="password" name="password" class="form-control mb-3" placeholder="Contraseña" required>
        <button class="btn login-btn text-white w-100 rounded-pill py-2">Ingresar al Sistema</button>
      </form>
    </div>
  </div>
  <?php include __DIR__ . '/paginas/footer.php'; ?>
</body></html>
