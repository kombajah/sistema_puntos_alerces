<?php
require_once 'conexion.php';
iniciar_sesion();
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
<!DOCTYPE html><html lang="es"><head><title>Escuela Los Alerces - Sistema de Puntos</title><?php include 'head.php'; ?></head>
<body class="d-flex align-items-center" style="min-height:100vh">
<div class="container">
  <div class="card login-card p-4 text-center">
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
<?php include 'footer.php'; ?>
</body></html>
