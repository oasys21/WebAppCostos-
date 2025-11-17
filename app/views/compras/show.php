<?php
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? (isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'],'/') : '');
$isBorr = ($compra['estado'] ?? '') === 'borrador';
?>
<style>
  body{
    padding-top:4.5rem;
    background-image: url(<?= $h($base) ?>/public/images/fondoverde3.jpg);
    background-color: transparent;
    background-repeat: repeat;
  }
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0"><?= $h($pageTitle ?? 'Compra') ?></h3>
  <div class="d-flex gap-2">
    <a class="btn btn-secondary btn-sm" href="<?= $h($base) ?>/compras">Volver</a>
    <?php if($isBorr): ?>
      <a class="btn btn-primary btn-sm" href="<?= $h($base) ?>/compras/editar/<?= (int)$compra['id'] ?>">Editar</a>
      <a class="btn btn-danger btn-sm" href="<?= $h($base) ?>/compras/destroy/<?= (int)$compra['id'] ?>" onclick="return confirm('¿Eliminar compra BORRADOR?');">Eliminar</a>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body row g-3">
    <div class="col-md-2"><strong>Proveedor</strong><br><?= $h($compra['proveedor_id']) ?></div>
    <div class="col-md-2"><strong>Tipo/Folio</strong><br><?= $h($compra['tipo_doc'].' '.$compra['folio']) ?></div>
    <div class="col-md-2"><strong>Fecha</strong><br><?= $h($compra['fecha_doc']) ?></div>
    <div class="col-md-2"><strong>Moneda</strong><br><?= $h($compra['moneda']) ?></div>
    <div class="col-md-2"><strong>Estado</strong><br><?= $h($compra['estado']) ?></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><strong>Ítems</strong></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Código</th>
            <th>Descripción</th>
            <th class="text-end">Cant</th>
            <th class="text-end">Precio</th>
            <th class="text-end">Monto</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php $n=1; $sum=0; foreach($items as $it): $m=(float)$it['cantidad']*(float)$it['precio_unitario']; $sum+=$m; ?>
          <tr>
            <td><?= $n++ ?></td>
            <td><?= $h($it['codigo']) ?></td>
            <td><?= $h($it['descripcion']) ?></td>
            <td class="text-end"><?= $h($it['cantidad']) ?></td>
            <td class="text-end"><?= $h($it['precio_unitario']) ?></td>
            <td class="text-end"><?= number_format($m,2,'.','') ?></td>
            <td>
              <a class="btn btn-danger"
                 href="<?= $base ?>/index.php?r=compras/destroy/<?= (int)$compra['id'] ?>"
                 onclick="return confirm('¿Eliminar compra? Se revertirán imputaciones realizadas.');">Eliminar</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="5" class="text-end">Subtotal</th>
            <th class="text-end"><?= number_format($sum,2,'.','') ?></th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php
$subt = (float)($compra['subtotal']  ?? 0);
$desc = (float)($compra['descuento'] ?? 0);
$imp  = (float)($compra['impuesto']  ?? 0);
$total = max($subt - $desc + $imp, 0);
?>
<div class="card">
  <div class="card-header"><strong>Resumen</strong></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-sm-2">
        <div class="form-label">Subtotal</div>
        <input class="form-control text-end" value="<?= number_format($subt,2,'.','') ?>" readonly>
      </div>
      <div class="col-sm-2">
        <div class="form-label">Descuento</div>
        <input class="form-control text-end" value="<?= number_format($desc,2,'.','') ?>" readonly>
      </div>
      <div class="col-sm-2">
        <div class="form-label">Impuesto</div>
        <input class="form-control text-end" value="<?= number_format($imp,2,'.','') ?>" readonly>
      </div>
      <div class="col-sm-2">
        <div class="form-label">Total</div>
        <input class="form-control text-end" value="<?= number_format($total,2,'.','') ?>" readonly>
      </div>
    </div>
  </div>
</div>
</div>