<?php
declare(strict_types=1);
/** @var array $oc */
/** @var array $items */
/** @var array|null $prov */
/** @var array|null $proy */

$h    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';
$u    = class_exists('Session') ? (Session::user() ?? null) : null;
$isADM = (!!$u && ($u['perfil'] ?? '') === 'ADM');

$round0 = fn($n) => (int)round((float)$n, 0, PHP_ROUND_HALF_UP);
$clp    = fn($n) => 'CLP$ '.number_format($round0($n), 0, ',', '.');   // CLP$ solo en totales
$intfmt = fn($n) => number_format($round0($n), 0, ',', '.');           // sin CLP$ para ítems
$qty2   = fn($n) => number_format((float)$n, 2, '.', '');

$sub = (float)($oc['subtotal']  ?? 0);
$des = (float)($oc['descuento'] ?? 0);
$iva = (float)($oc['impuesto']  ?? 0);
$tot = ($oc['total'] ?? null) !== null ? (float)$oc['total'] : ($sub - $des + $iva);

$provNombre = $prov['razon'] ?? $prov['nombre'] ?? '';
$provRut    = $prov['rut']    ?? '';
$proyNombre = $proy['nombre'] ?? '';
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">OC <?= $h($oc['oc_num'] ?? '') ?></h3>
  <div class="d-flex gap-2">
    <a class="btn btn-success btn-sm" href="<?= $h($base) ?>/ocompras">Volver</a>
    <?php if (($oc['estado'] ?? '') === 'borrador'): ?>
      <a class="btn btn-success btn-sm" href="<?= $h($base) ?>/ocompras/editar/<?= (int)$oc['id'] ?>">Editar</a>
    <?php endif; ?>
    <a class="btn btn-primary btn-sm" target="_blank" href="<?= $h($base) ?>/ocompras/print/<?= (int)$oc['id'] ?>">Imprimir</a>
    <?php if ($isADM): ?>
      <form action="<?= $h($base) ?>/ocompras/destroy/<?= (int)$oc['id'] ?>" method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta OC?');">
        <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-5">
  <div class="col-md-12">
    <div class="card">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-sm-6">
            <strong>Proveedor:</strong>
            <?= $h($provNombre ?: '—') ?>
            <?php if ($provRut): ?>
              <span class="text-muted">· RUT <?= $h($provRut) ?></span>
            <?php endif; ?>
          </div>
          <div class="col-sm-3"><strong>Fecha:</strong> <?= $h($oc['fecha'] ?? '') ?></div>
          <div class="col-sm-3"><strong>Estado:</strong> <span class="badge bg-secondary"><?= $h($oc['estado'] ?? '') ?></span></div>

          <div class="col-sm-6"><strong>Proyecto:</strong> <?= $h($proyNombre ?: '—') ?></div>
          <div class="col-sm-6"><strong>Moneda / TC:</strong> <?= $h($oc['moneda'] ?? 'CLP') ?> · <?= $h($oc['tipo_cambio'] ?? '1.000000') ?></div>

          <?php if (!empty($oc['condiciones_pago'])): ?>
            <div class="col-12"><strong>Condiciones de pago:</strong> <?= $h($oc['condiciones_pago']) ?></div>
          <?php endif; ?>
          <?php if (!empty($oc['observaciones'])): ?>
            <div class="col-12"><strong>Observaciones:</strong> <?= $h($oc['observaciones']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="table-responsive mt-5">
      <table class="table table-hover table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:60px" class="text-center">#</th>
            <th style="width:100px">Código</th>
            <th>Descripción</th>
            <th style="width:80px" class="text-center">Unidad</th>
            <th style="width:90px" class="text-center">Tipo</th>
            <th style="width:110px" class="text-end">Cantidad</th>
            <th style="width:130px" class="text-end">Precio Unitario</th>
            <th style="width:130px" class="text-end">Total Neto Item</th>
            <?php if ($isADM && ($oc['estado'] ?? '') === 'borrador'): ?>
              <th style="width:80px" class="text-center">Acción</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if(!empty($items)): $i=0; foreach($items as $it): $i++;
            $q = (float)($it['cantidad'] ?? 0);
            $pu= (float)($it['precio_unitario'] ?? 0);
            $mt= $q * $pu;
          ?>
          <tr>
            <td style="background-color:transparent" class="text-center"><?= $i ?></td>
            <td style="background-color:transparent"><?= $h($it['codigo'] ?? '') ?></td>
            <td style="background-color:transparent"><?= $h($it['descripcion'] ?? '') ?></td>
            <td style="background-color:transparent" class="text-center"><?= $h($it['unidad'] ?? '') ?></td>
            <td style="background-color:transparent" class="text-center"><?= $h($it['tipo_costo'] ?? '') ?></td>
            <td class="text-end"><strong><?= $qty2($q) ?></strong></td>
            <td class="text-end"><strong><?= $intfmt($pu) ?></strong></td>
            <td class="text-end"><strong><?= $intfmt($mt) ?></strong></td>
            <?php if ($isADM && ($oc['estado'] ?? '') === 'borrador'): ?>
              <td class="text-center">
                <form action="<?= $h($base) ?>/ocompras/destroyItem/<?= (int)$oc['id'] ?>/<?= (int)($it['id'] ?? 0) ?>" method="post" onsubmit="return confirm('¿Eliminar ítem?');">
                  <button class="btn btn-sm btn-danger" type="submit">&times;</button>
                </form>
              </td>
            <?php endif; ?>
			
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="<?= ($isADM && ($oc['estado'] ?? '')==='borrador') ? 9 : 8 ?>" class="text-center text-muted">Sin ítems</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-md-12">
    <div class="card">
      <div class="card-body">
        <h6 class="mb-3">Totales</h6>
        <div class="d-flex justify-content-between">
          <div>Subtotal</div><div class="fw-semibold"><?= $clp($sub) ?></div>
        </div>
        <div class="d-flex justify-content-between">
          <div>Descuento</div><div class="fw-semibold"><?= $clp($des) ?></div>
        </div>
        <div class="d-flex justify-content-between">
          <div>Impuesto</div><div class="fw-semibold"><?= $clp($iva) ?></div>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between fs-5">
          <div><strong>Total</strong></div><div><strong><?= $clp($tot) ?></strong></div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>