<?php
// /costos/app/views/maestros/_form.php
declare(strict_types=1);
$tipos = ['MAT'=>'Materiales','MO'=>'Mano de Obra','EQ'=>'Equipos','SUBC'=>'Subcontratos','CON'=>'Gastos/Consumibles','VIA'=>'Viáticos'];
?>
<div class="row g-3">
  <div class="col-md-3">
    <label class="form-label">Código</label>
    <input class="form-control" name="codigo" required maxlength="50" value="<?= htmlspecialchars((string)$row['codigo']) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Descripción</label>
    <input class="form-control" name="descripcion" required maxlength="255" value="<?= htmlspecialchars((string)$row['descripcion']) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Unidad</label>
    <input class="form-control" name="unidad" maxlength="16" value="<?= htmlspecialchars((string)$row['unidad']) ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label">Tipo de costo</label>
    <select class="form-select" name="tipo_costo">
      <?php foreach ($tipos as $k=>$v): ?>
        <option value="<?= $k ?>" <?= ($row['tipo_costo']===$k)?'selected':'' ?>><?= $k ?> · <?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Subtipo (opcional)</label>
    <input class="form-control" name="subtipo_costo" maxlength="8" value="<?= htmlspecialchars((string)$row['subtipo_costo']) ?>" placeholder="p.ej. COLA, HAB, VAIR">
  </div>
  <div class="col-md-3">
    <label class="form-label">Impuesto (regla)</label>
    <input class="form-control" name="impuesto_regla" maxlength="32" value="<?= htmlspecialchars((string)$row['impuesto_regla']) ?>">
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" <?= !empty($row['activo'])?'checked':'' ?>>
      <label class="form-check-label" for="activo">Activo</label>
    </div>
  </div>
</div>
