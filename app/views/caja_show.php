<?php
/** @var string $base */
/** @var array $caja */
/** @var array $row */
/** @var array $adjuntos */
$base = rtrim((string)($base ?? ''), '/');
$fmt = function($n){ return number_format((float)$n, 0, ',', ','); };
?>
 <style>
 <!--
body{padding-top:4.5rem; 	
background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); 
background-color: transparent;	background-repeat: repeat;}	
-->
</style>
<div class="mx-auto d-block " style="align:center;">
<div class="container my-4" data-base="<?= htmlspecialchars($base, ENT_QUOTES) ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Movimiento #<?= (int)$row['id'] ?></h4>
    <a class="btn btn-secondary" href="<?= $base ?>/caja">Volver</a>
  </div>

  <div class="card mb-3">
    <div class="card-body row g-3">
      <div class="col-md-4"><div class="fw-bold small">Fecha</div><div class="fw-bold"><?= htmlspecialchars($row['fecha_mov']) ?></div></div>
      <div class="col-md-4"><div class="fw-bold small">Tipo</div><div class="fw-bold"><?= htmlspecialchars($row['tipo']) ?></div></div>
      <div class="col-md-4"><div class="fw-bold small">Estado</div><div class="fw-bold"><?= htmlspecialchars($row['estado']) ?></div></div>
      <div class="col-md-3"><div class="fw-bold small">Monto</div><div class="fw-bold">$ <?= $fmt($row['monto'] ?? 0) ?></div></div>
      <div class="col-md-3"><div class="fw-bold small">Doc.</div><div><?= htmlspecialchars(($row['documento_tipo'] ?? 'OTRO').(($row['numero_doc']) ? ' #'.$row['numero_doc'] : '')) ?></div></div>
      <div class="col-md-3">
        <div class="fw-bold small">Imputación</div>
        <?php if (!empty($row['cod_imputacion'])): ?>
          <span class="badge bg-secondary"><?= htmlspecialchars($row['cod_imputacion']) ?></span>
          <small class="text-muted"><?= htmlspecialchars($row['glosa_imputacion']) ?></small>
        <?php else: ?>
          <span class="text-muted">—</span>
        <?php endif; ?>
      </div>
      <div class="col-md-12"><div class="fw-bold small">Descripción</div><div><?= htmlspecialchars($row['descripcion'] ?? '—') ?></div></div>
    </div>
  </div>

  <div class=" mb-3">
    <div class="card-header">Adjuntos</div>
    <div class="card-body">
      <?php if (!empty($adjuntos)): ?>
        <div class="list-group mb-3">
          <?php foreach ($adjuntos as $a): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
               href="<?= $base ?>/caja/archivo/<?= (int)$a['id'] ?>" target="_blank" rel="noopener">
              <span><?= htmlspecialchars($a['nombre_archivo']) ?> <span class="text-muted small ms-2"><?= htmlspecialchars($a['mime_type']) ?></span></span>
              <small class="text-muted"><?= htmlspecialchars($a['creado_en'] ?? '') ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-muted">Sin archivos.</div>
      <?php endif; ?>

      <form class="mt-3" action="<?= $base ?>/caja/upload" method="post" enctype="multipart/form-data">
        <input type="hidden" name="movimiento_id" value="<?= (int)$row['id'] ?>">
        <div class="row g-2 align-items-end">
          <div class="col-md-8">
            <label class="form-label">Agregar adjunto (PDF/JPG/PNG, máx 20MB)</label>
            <input type="file" name="doc_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
          </div>
          <div class="col-md-4 text-end">
            <button class="btn btn-primary">Subir archivo</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
</div>