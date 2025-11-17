<?php
// /costos/app/views/avances_edit.php
/** @var array $row */
/** @var string $csrf */
$base = rtrim($this->cfg['BASE_URL'] ?? '', '/');
include __DIR__ . '/layout/header.php';
?>
<div class="container py-3">
  <h4>Editar Avance #<?= (int)$row['id'] ?> (Proyecto <?= (int)$row['proyecto_id'] ?>)</h4>

  <form class="row g-3" method="post" action="<?= htmlspecialchars($base.'/avances/update/'.$row['id']) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="col-md-6">
      <label class="form-label">Código</label>
      <input type="text" class="form-control" name="codigo" value="<?= htmlspecialchars((string)$row['codigo']) ?>" minlength="10" maxlength="10" required>
      <div class="form-text">Formato canónico sin guiones: FFFGGGXXXX</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Fecha avance</label>
      <input type="date" class="form-control" name="fecha_avance" value="<?= htmlspecialchars((string)$row['fecha_avance']) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Cantidad ejecutada</label>
      <input type="number" step="0.0001" min="0" class="form-control" name="cantidad_ejecutada"
             value="<?= htmlspecialchars((string)$row['cantidad_ejecutada']) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Monto ejecutado</label>
      <input type="number" step="0.01" min="0" class="form-control" name="monto_ejecutado"
             value="<?= htmlspecialchars((string)$row['monto_ejecutado']) ?>" required>
    </div>
    <div class="col-md-9">
      <label class="form-label">Observaciones</label>
      <textarea class="form-control" name="observaciones" rows="2"><?= htmlspecialchars((string)($row['observaciones'] ?? '')) ?></textarea>
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary">Actualizar</button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base.'/avances/index?proyecto_id='.$row['proyecto_id']) ?>">Volver</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
