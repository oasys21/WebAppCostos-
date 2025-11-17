<?php
declare(strict_types=1);
/** @var array $ocs */
/** @var array $filters */
/** @var array $proveedores */
/** @var array $proyectos */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';
$sel  = function($cur, $val){ return (string)$cur === (string)$val ? 'selected' : ''; };

// Helpers de formato
$round0 = fn($n) => (int)round((float)$n, 0, PHP_ROUND_HALF_UP); // half-up
$clp    = fn($n) => 'CLP$ '.number_format($round0($n), 0, ',', '.');

?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0"><?= $h($pageTitle ?? 'Órdenes de Compra') ?></h3>
  <a class="btn btn-primary " href="<?= $h($base) ?>/ocompras/nuevo">Nueva OC</a>
  
</div>

<form class="row g-2 mb-3" method="get" action="<?= $h($base) ?>/ocompras/index" autocomplete="off">
  <div class="col-sm-2">
    <label class="form-label mb-1">N° OC</label>
    <input type="text" name="oc_num" class="form-control form-control-sm" value="<?= $h($filters['oc_num'] ?? '') ?>">
  </div>
  <div class="col-sm-3">
    <label class="form-label mb-1">Proveedor</label>
    <select name="proveedor_id" class="form-select form-select-sm">
      <option value="">-- Todos --</option>
      <?php foreach(($proveedores ?? []) as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= $sel($filters['proveedor_id'] ?? '', $p['id']) ?>><?= $h($p['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-sm-3">
    <label class="form-label mb-1">Proyecto</label>
    <select name="proyecto_id" class="form-select form-select-sm">
      <option value="">-- Todos --</option>
      <?php foreach(($proyectos ?? []) as $pr): ?>
      <option value="<?= (int)$pr['id'] ?>" <?= $sel($filters['proyecto_id'] ?? '', $pr['id']) ?>><?= $h($pr['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-sm-2">
    <label class="form-label mb-1">Estado</label>
    <select name="estado" class="form-select form-select-sm">
      <option value="">-- Todos --</option>
      <option value="borrador"  <?= $sel($filters['estado'] ?? '', 'borrador')  ?>>borrador</option>
      <option value="emitida"   <?= $sel($filters['estado'] ?? '', 'emitida')   ?>>emitida</option>
      <option value="cerrada"   <?= $sel($filters['estado'] ?? '', 'cerrada')   ?>>cerrada</option>
      <option value="anulada"   <?= $sel($filters['estado'] ?? '', 'anulada')   ?>>anulada</option>
    </select>
  </div>
  <div class="col-sm-1">
    <label class="form-label mb-1">Desde</label>
    <input type="date" name="desde" class="form-control form-control-sm" value="<?= $h($filters['desde'] ?? '') ?>">
  </div>
  <div class="col-sm-1">
    <label class="form-label mb-1">Hasta</label>
    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $h($filters['hasta'] ?? '') ?>">
  </div>
  <div class="col-12 d-flex gap-2">
    <button class="btn btn-sm btn-outline-primary">Filtrar</button>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $h($base) ?>/ocompras/index">Limpiar</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th style="width:120px">N° OC</th>
        <th style="width:95px">Fecha</th>
        <th>Proveedor</th>
        <th>Proyecto</th>
        <th style="width:100px">Estado</th>
        <th class="text-end" style="width:120px">Subtotal</th>
        <th class="text-end" style="width:120px">Desc.</th>
        <th class="text-end" style="width:120px">IVA</th>
        <th class="text-end" style="width:120px">Total</th>
        <th style="width:185px">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!empty($ocs)): foreach($ocs as $r):
        $sub = (float)($r['subtotal']  ?? 0);
        $des = (float)($r['descuento'] ?? 0);
        $iva = (float)($r['impuesto']  ?? 0);
        $tot = ($r['total'] ?? null) !== null ? (float)$r['total'] : ($sub - $des + $iva);
      ?>
      <tr>
        <td width="150px" style="background-color:transparent"><a href="<?= $h($base) ?>/ocompras/ver/<?= (int)$r['id'] ?>"><?= $h($r['oc_num']) ?></a></td>
        <td style="background-color:transparent"><?= $h($r['fecha']) ?></td>
        <td style="background-color:transparent"><?= $h($r['proveedor_nombre'] ?? $r['proveedor'] ?? '') ?></td>
        <td style="background-color:transparent"><?= $h($r['proyecto_nombre'] ?? $r['proyecto'] ?? '') ?></td>
        <td style="background-color:transparent"><span class="badge bg-secondary"><?= $h($r['estado']) ?></span></td>
        <td style="background-color:graylight" class="text-end"><?= $clp($sub) ?></td>
        <td style="background-color:graylight" class="text-end"><?= $clp($des) ?></td>
        <td style="background-color:graylight" class="text-end"><?= $clp($iva) ?></td>
        <td style="background-color:graylight" class="text-end fw-semibold"><?= $clp($tot) ?></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-primary" href="<?= $h($base) ?>/ocompras/ver/<?= (int)$r['id'] ?>">Ver</a>
          <a class="btn btn-sm btn-secondary" href="<?= $h($base) ?>/ocompras/print/<?= (int)$r['id'] ?>" target="_blank">Imprimir</a>
          <?php if(($r['estado'] ?? '') === 'borrador'): ?>
            <a class="btn btn-sm btn-success" href="<?= $h($base) ?>/ocompras/editar/<?= (int)$r['id'] ?>">Editar</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="10" class="text-center text-muted">Sin resultados</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>