<?php
// Usuario conectado (defensivo: $_SESSION['maestro'] puede ser array o un id)
$m = $_SESSION['maestro'] ?? null;
$nombreMaestro = '';
if (is_array($m)) $nombreMaestro = $m['nombre'] ?? '';
$esc = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
?>
<style>
.app-footer .pie-marca{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap}
.app-footer .pie-marca img{width:28px;height:28px;object-fit:contain}
.app-footer .pie-links a{margin:0 6px;white-space:nowrap}
.app-footer .pie-sesion{font-size:.85em;opacity:.8}
</style>
<footer class="app-footer text-center">
  <div class="pie-marca">
    <img src="assets/alercin_icono_512.png" alt="" onerror="this.style.display='none'">
    <span>Colegio Los Alerces · Año escolar <?= date('Y') ?></span>
  </div>

  <?php if (!empty($_SESSION['maestro'])): ?>
    <div class="pie-links mt-1">
      <a href="nfc.php">📲 Escanear</a>
      <a href="canje.php">🎁 Canje</a>
      <a href="buscar_alumno.php">🔎 Buscar alumno</a>
      <a href="reporte.php">📊 Reportes</a>
    </div>
    <?php if ($nombreMaestro !== ''): ?>
      <div class="pie-sesion mt-1">Sesión: <strong><?= $esc($nombreMaestro) ?></strong> · <a href="salir.php">Cerrar sesión</a></div>
    <?php endif; ?>
  <?php endif; ?>

  <!--div class="mt-1">
    © <?= date('Y') ?> by <strong>KombaJah</strong> ·
    <a href="https://instagram.com/losalercesdemaipu" target="_blank" rel="noopener">📷 Instagram</a>
    <a href="https://wa.me/56912345678" target="_blank" rel="noopener">💬 WhatsApp</a>
  </div-->
</footer>
<?php if (!empty($_SESSION['maestro'])) include __DIR__ . '/chat_widget.php'; // asistente Alercín (solo con sesión) ?>
