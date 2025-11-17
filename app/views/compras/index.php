<?php
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? (isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'],'/') : '');
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0">Compras</h1>
  <a class="btn btn-primary" href="<?= $h($base) ?>/compras/nuevo">Nueva compra</a>
</div>

<form class="row g-2 mb-3" method="get" action="<?= $h($base) ?>/index.php">
  <input type="hidden" name="r" value="compras/index">
  <div class="col-md-2">
    <label class="form-label mb-0 small">Folio</label>
    <input class="form-control form-control-sm" name="folio" value="<?= $h($filters['folio'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label mb-0 small">Proveedor</label>
    <select class="form-select form-select-sm" name="proveedor_id">
      <option value="">— todos —</option>
      <?php foreach ($proveedores as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((string)($filters['proveedor_id'] ?? '')===(string)$p['id']?'selected':'') ?>>
          <?= $h($p['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label mb-0 small">Proyecto</label>
    <select class="form-select form-select-sm" name="proyecto_id">
      <option value="">— todos —</option>
      <?php foreach ($proyectos as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((string)($filters['proyecto_id'] ?? '')===(string)$p['id']?'selected':'') ?>>
          <?= $h($p['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label mb-0 small">Tipo doc</label>
    <select class="form-select form-select-sm" name="tipo_doc">
      <option value="">— todos —</option>
      <?php foreach (['FAC'=>'Factura','BOL'=>'Boleta','NC'=>'N. Crédito','ND'=>'N. Débito','OC'=>'OC'] as $v=>$lbl): ?>
        <option value="<?= $h($v) ?>" <?= (($filters['tipo_doc'] ?? '')===$v?'selected':'')?>><?= $h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label mb-0 small">Estado</label>
    <select class="form-select form-select-sm" name="estado">
      <option value="">— todos —</option>
      <?php foreach (['borrador'=>'Borrador','anulada'=>'Anulada','procesada'=>'Procesada'] as $v=>$lbl): ?>
        <option value="<?= $h($v) ?>" <?= (($filters['estado'] ?? '')===$v?'selected':'')?>><?= $h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label mb-0 small">Desde</label>
    <input type="date" class="form-control form-control-sm" name="desde" value="<?= $h($filters['desde'] ?? '') ?>">
  </div>
  <div class="col-md-2">
    <label class="form-label mb-0 small">Hasta</label>
    <input type="date" class="form-control form-control-sm" name="hasta" value="<?= $h($filters['hasta'] ?? '') ?>">
  </div>
  <div class="col-md-2 d-flex align-items-end">
    <button class="btn btn-sm btn-secondary w-100">Filtrar</button>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>Doc</th>
        <th>Proveedor</th>
        <th>Proyecto</th>
        <th class="text-end">Subtotal</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($compras)): ?>
        <tr><td colspan="6" class="text-center text-muted">Sin resultados</td></tr>
      <?php else: foreach ($compras as $c): ?>
        <tr>
          <td><?= $h($c['fecha_doc']) ?></td>
          <td><?= $h($c['tipo_doc'].' '.$c['folio']) ?></td>
          <td><?= $h($c['proveedor']) ?></td>
          <td><?= $h($c['proyecto'] ?? '') ?></td>
          <td class="text-end"><?= number_format((float)$c['subtotal'], 2, ',', '.') ?></td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-secondary" href="<?= $h($base) ?>/compras/ver/<?= (int)$c['id'] ?>">Ver</a>
            <?php if (($c['estado'] ?? 'borrador') === 'borrador'): ?>
              <a class="btn btn-sm btn-warning" href="<?= $h($base) ?>/compras/editar/<?= (int)$c['id'] ?>">Editar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
	  
	  
	  
	  <?php endif; ?>
    </tbody>
  </table>
</div>
</div>