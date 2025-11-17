<?php
// /costos/app/views/maestros/index.php
declare(strict_types=1);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="m-0">Maestro Catálogo</h3>
  <a class="btn btn-primary" href="<?= htmlspecialchars($base) ?>/maestros/create">Nuevo ítem</a>
</div>

<form method="get" class="row g-2 mb-3" action="<?= htmlspecialchars($base) ?>/maestros/index">
  <div class="col-sm-4">
    <input class="form-control" type="text" name="q" value="<?= htmlspecialchars((string)($q ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por código o descripción">
  </div>
  <div class="col-sm-3">
    <select class="form-select" name="tipo">
      <option value="">-- Tipo --</option>
      <?php foreach (['MAT','MO','EQ','SUBC','CON','VIA'] as $t): ?>
        <option value="<?= $t ?>" <?= (!empty($tipo) && $tipo===$t)?'selected':'' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-sm-3 form-check d-flex align-items-center">
    <input class="form-check-input me-2" type="checkbox" name="inact" value="1" <?= (!empty($inact)?'checked':'') ?> id="inact">
    <label class="form-check-label" for="inact">Incluir inactivos</label>
  </div>
  <div class="col-sm-2">
    <button class="btn btn-outline-secondary w-100">Filtrar</button>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm table-striped align-middle">
    <thead>
      <tr>
        <th style="width:110px">Código</th>
        <th>Descripción</th>
        <th style="width:80px">Tipo</th>
        <th style="width:90px">Subtipo</th>
        <th style="width:70px">Unidad</th>
        <th style="width:120px">Impuesto</th>
        <th style="width:90px">Estado</th>
        <th style="width:170px"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($items)): foreach ($items as $r): ?>
        <tr class="<?= !$r['activo'] ? 'table-warning' : '' ?>">
          <td><code><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td><?= htmlspecialchars($r['descripcion']) ?></td>
          <td><?= htmlspecialchars($r['tipo_costo']) ?></td>
          <td><?= htmlspecialchars((string)$r['subtipo_costo']) ?></td>
          <td><?= htmlspecialchars($r['unidad']) ?></td>
          <td><?= htmlspecialchars($r['impuesto_regla']) ?></td>
          <td><?= $r['activo'] ? 'Activo' : 'Inactivo' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($base) ?>/maestros/edit/<?= (int)$r['id'] ?>">Editar</a>
            <a class="btn btn-sm btn-outline-warning" href="<?= htmlspecialchars($base) ?>/maestros/toggle/<?= (int)$r['id'] ?>" onclick="return confirm('¿Cambiar estado?');"><?= $r['activo'] ? 'Desactivar' : 'Activar' ?></a>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted">Sin resultados.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
