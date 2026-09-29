<?php $act = basename($_SERVER['PHP_SELF']); function lk($f,$t,$a){ return "<a href='$f' class='text-white text-decoration-none small ".($a==$f?'fw-bold':'')."'>$t</a>"; } ?>
<div class="header-app d-flex justify-content-around flex-wrap gap-2 pb-2">
  <?= lk('reporte.php','📊 Reporte',$act) ?>
  <?= lk('nfc.php','🛜 NFC',$act) ?>
  <?= lk('canje.php','🎁 Canje',$act) ?>
  <?= lk('historico.php','🕘 Histórico',$act) ?>
  <?= lk('tarjetas.php','🪪 Tarjetas',$act) ?>
  <?= lk('contenido.php','⚙️ Contenido',$act) ?>
  <?php if (es_admin()): ?><?= lk('carga_masiva.php','📥 Carga masiva',$act) ?><?= lk('maestros.php','🔑 Maestros',$act) ?><?php endif; ?>
  <a href="salir.php" class="text-white text-decoration-none small">Salir</a>
</div>
<div class="text-center pb-3">
  <span class="badge rounded-pill" style="background:#ffffff33;color:#fff;font-size:.85rem;padding:6px 14px">
    👋 Sesión activa: <strong><?= h($_SESSION['maestro']) ?></strong> · <?= es_admin()?'Administrador':'Docente' ?>
  </span>
</div>
