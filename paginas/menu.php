<?php $act = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)); function lk($f,$t,$a){ return "<a href='$f' class='text-white text-decoration-none small ".($a==$f?'fw-bold':'')."'>$t</a>"; } ?>
<div class="header-app d-flex justify-content-around flex-wrap gap-2 pb-2">
  <?= lk('reporte.php','📊 Reporte',$act) ?>
  <?= lk('nfc.php','🛜 NFC',$act) ?>
  <?= lk('canje.php','🎁 Canje',$act) ?>
  <?= lk('metas.php','🎯 Metas',$act) ?>
  <?= lk('historico.php','🕘 Histórico',$act) ?>
  <?= lk('tarjetas.php','🪪 Tarjetas',$act) ?>
  <?= lk('contenido.php','⚙️ Contenido',$act) ?>
  <?= lk('carga_masiva.php','📥 Carga masiva',$act) ?>
  <?php if (es_admin()): ?><?= lk('maestros.php','🔑 Maestros',$act) ?><?php endif; ?>
  <a href="salir.php" class="text-white text-decoration-none small">Salir</a>
</div>
<div class="text-center pb-3">
  <span class="badge rounded-pill" style="background:#ffffff; color:#388e3c; border: 1px solid #388e3c; font-size:.85rem; padding:6px 14px">
    👋 Usuario: <strong><?= h($_SESSION['maestro']) ?></strong> · <?= es_admin()?'Administrador':'Docente' ?>
  </span>
</div>