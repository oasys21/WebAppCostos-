<?php
declare(strict_types=1);
/** @var array $oc */
/** @var array $items */
/** @var array $prov */
/** @var array|null $proy */
/** @var array|null $emisor */

/* ===== Helpers CLP sin decimales (half-up) y qty 2 dec ===== */
$r0  = fn($n) => (int)round((float)$n, 0, PHP_ROUND_HALF_UP);
$clp = function($n){ $v = (int)round((float)$n, 0, PHP_ROUND_HALF_UP); return 'CLP$ '.number_format($v, 0, ',', '.'); };
$clps = function($n){ $v = (int)round((float)$n, 0, PHP_ROUND_HALF_UP); return number_format($v, 0, ',', '.'); };
$fmt2 = fn($n) => number_format((float)$n, 2, '.', '');

/* ===== Totales ===== */
$subRaw = (float)($oc['subtotal']  ?? 0);
$desRaw = (float)($oc['descuento'] ?? 0);
$impRaw = (float)($oc['impuesto']  ?? 0);
$subtotal = $r0($subRaw);
$descuento= $r0($desRaw);
$impuesto = $r0($impRaw);
$total    = $r0(($subRaw - $desRaw) + $impRaw);

/* ===== Proveedor (con fallbacks) ===== */
$provNombre = trim((string)($prov['_nombre'] ?? $prov['nombre'] ?? ''));
$provRazon  = trim((string)($prov['_razon']  ?? $prov['razon']  ?? ''));
$provRut    = trim((string)($prov['_rut']    ?? $prov['rut']    ?? ''));
$provDir    = trim((string)($prov['_direccion'] ?? $prov['direccion'] ?? ''));
$provCom    = trim((string)($prov['comuna'] ?? ''));
$provCiu    = trim((string)($prov['ciudad'] ?? ''));
$provRubro  = trim((string)($prov['_rubro'] ?? $prov['rubro'] ?? ''));
$conNom  = trim((string)($prov['con_nom']   ?? ''));
$conMail = trim((string)($prov['con_email'] ?? ''));
$conFono = trim((string)($prov['con_fon']   ?? ''));
$epNom   = trim((string)($prov['ep_nom']    ?? ''));
$epMail  = trim((string)($prov['ep_email']  ?? ''));
$epFono  = trim((string)($prov['ep_fono']   ?? ''));

/* ===== Emisor (proveedores.id = 1) ===== */
$emiNombre = trim((string)($emisor['razon'] ?? $emisor['nombre'] ?? ''));
$emiRut    = trim((string)($emisor['rut']   ?? ''));
$emiRubro  = trim((string)($emisor['rubro'] ?? ''));
$emiDir    = trim((string)($emisor['direccion'] ?? ''));
$emiCom    = trim((string)($emisor['comuna']    ?? ''));
$emiCiu    = trim((string)($emisor['ciudad']    ?? ''));
$emiEpNom  = trim((string)($emisor['ep_nom']    ?? ''));
$emiEpMail = trim((string)($emisor['ep_email']  ?? ''));
$emiEpFono = trim((string)($emisor['ep_fono']   ?? ''));

/* ===== Proyecto ===== */
$proyNombre = $proy['nombre'] ?? '';
?>
<style>
@media print {
  @page { size: A4 portrait; margin: 15mm; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: none !important; }
  nav, .btn, .no-print { display: none !important; }
}
.print-doc { background: #fff; padding: 12px; }
.hdr h1 { font-size: 18px; margin: 0 0 6px 0; }
.hdr .meta { font-size: 12px; line-height: 1.35; }
.grid { display: grid; grid-template-columns: 1fr 1.1fr 1fr; gap: 10px; }
.block { margin: 2px 0; }
.pill { display:inline-block; padding:2px 8px; border-radius:999px; background:#f1f5ff; font-size:11px; border:1px solid #dbe7ff; }
.small { font-size: 12px; color: #666; }
.table { width: 100%; border-collapse: collapse; font-size: 12px; }
.table th, .table td { border: 1px solid #555; padding: 6px 8px; vertical-align: top; }
.table thead th { background: #f0f0f0; }
.table thead.print-head th { background: #e6f2ff; font-weight: 600; }
.table thead { display: table-header-group; }
.table tfoot { display: table-row-group; }
.table .num { text-align: right; white-space: nowrap; }
.table .cen { text-align: center; }
.totales { width: 100%; max-width: 420px; margin-left: auto; border-collapse: collapse; font-size: 12px; }
.totales td { padding: 6px 8px; }
.totales .lbl { text-align: right; }
.totales .val { text-align: right; min-width: 140px; border: 1px solid #555; }
.footer-nota { margin-top: 8px; font-size: 11px; color: #666; }
</style>

<div class="print-doc">
  <div class="page-actions no-print">
    <a href="#" class="btn btn-primary btn-sm" onclick="window.print();return false;">Imprimir</a>
    <a href="<?= htmlspecialchars((string)($this->baseUrl() ?? ''), ENT_QUOTES) ?>/ocompras/ver<?= isset($oc['id'])?'/'.(int)$oc['id']:'' ?>" class="btn btn-outline-secondary btn-sm">Volver</a>
  </div>

  <table class="table">
    <thead class="print-head">
      <tr>
        <th colspan="8">
          <div class="hdr">
            <div class="grid">
              <!-- Emisor -->
              <div>
                <h1>Empresa Emisora</h1>
                <div class="meta">
                  <?php if ($emiNombre): ?><div class="block"><strong>Razón Social:</strong> <?= htmlspecialchars($emiNombre) ?></div><?php endif; ?>
                  <?php if ($emiRut):    ?><div class="block"><strong>RUT:</strong> <?= htmlspecialchars($emiRut) ?></div><?php endif; ?>
                  <?php if ($emiRubro):  ?><div class="block"><strong>Rubro/Giro:</strong> <?= htmlspecialchars($emiRubro) ?></div><?php endif; ?>
                  <?php if ($emiDir || $emiCom || $emiCiu): ?>
                    <div class="block"><strong>Dirección:</strong>
                      <?= htmlspecialchars($emiDir) ?>
                      <?php if ($emiCom): ?>, <?= htmlspecialchars($emiCom) ?><?php endif; ?>
                      <?php if ($emiCiu): ?>, <?= htmlspecialchars($emiCiu) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($emiEpNom || $emiEpMail || $emiEpFono): ?>
                    <div class="block"><strong>Encargado Pagos:</strong>
                      <?= htmlspecialchars($emiEpNom) ?>
                      <?php if ($emiEpMail): ?> · <?= htmlspecialchars($emiEpMail) ?><?php endif; ?>
                      <?php if ($emiEpFono): ?> · <?= htmlspecialchars($emiEpFono) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Datos OC al centro -->
              <div>
                <h1>Orden de Compra</h1>
                <div class="meta">
                  <div class="block"><strong>OC:</strong> <?= htmlspecialchars((string)$oc['oc_num']) ?></div>
                  <div class="block"><strong>Fecha:</strong> <?= htmlspecialchars((string)$oc['fecha']) ?></div>
                  <?php if ($proyNombre !== ''): ?>
                    <div class="block"><strong>Proyecto:</strong> <?= htmlspecialchars($proyNombre) ?></div>
                  <?php endif; ?>
                  <div class="block">
                    <strong>Moneda:</strong> <?= htmlspecialchars((string)($oc['moneda'] ?? 'CLP')) ?>
                    <span class="pill">TC: <?= htmlspecialchars((string)($oc['tipo_cambio'] ?? '1.000000')) ?></span>
                  </div>
                  <?php if (!empty($oc['condiciones_pago'])): ?>
                    <div class="block"><strong>Condiciones de pago:</strong> <?= htmlspecialchars((string)$oc['condiciones_pago']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($oc['observaciones'])): ?>
                    <div class="small"><strong>Observaciones:</strong> <?= htmlspecialchars((string)$oc['observaciones']) ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Proveedor -->
              <div>
                <h1>Proveedor</h1>
                <div class="meta">
                  <?php
                  $pNombre = $provRazon ?: $provNombre;
                  ?>
                  <?php if ($pNombre): ?><div class="block"><strong>Razón Social:</strong> <?= htmlspecialchars($pNombre) ?></div><?php endif; ?>
                  <?php if ($provRut):   ?><div class="block"><strong>RUT:</strong> <?= htmlspecialchars($provRut) ?></div><?php endif; ?>
                  <?php if ($provRubro): ?><div class="block"><strong>Rubro/Giro:</strong> <?= htmlspecialchars($provRubro) ?></div><?php endif; ?>
                  <?php if ($provDir || $provCom || $provCiu): ?>
                    <div class="block"><strong>Dirección:</strong>
                      <?= htmlspecialchars($provDir) ?>
                      <?php if ($provCom): ?>, <?= htmlspecialchars($provCom) ?><?php endif; ?>
                      <?php if ($provCiu): ?>, <?= htmlspecialchars($provCiu) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($conNom || $conMail || $conFono): ?>
                    <div class="block"><strong>Contacto:</strong>
                      <?= htmlspecialchars($conNom) ?>
                      <?php if ($conMail): ?> · <?= htmlspecialchars($conMail) ?><?php endif; ?>
                      <?php if ($conFono): ?> · <?= htmlspecialchars($conFono) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($epNom || $epMail || $epFono): ?>
                    <div class="block"><strong>Encargado Pagos:</strong>
                      <?= htmlspecialchars($epNom) ?>
                      <?php if ($epMail): ?> · <?= htmlspecialchars($epMail) ?><?php endif; ?>
                      <?php if ($epFono): ?> · <?= htmlspecialchars($epFono) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </th>
      </tr>

      <tr>
        <th class="cen" style="width:40px">#</th>
        <th style="width:90px">Código</th>
        <th>Descripción</th>
        <th class="cen" style="width:60px">Unidad</th>
        <th class="cen" style="width:60px">Tipo</th>
        <th class="num" style="width:90px">Cantidad</th>
        <th class="num" style="width:110px">Precio Unit.</th>
        <th class="num" style="width:120px">Monto</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i=0; $sum=0.0;
      foreach($items as $it):
        $i++;
        $cant = (float)($it['cantidad'] ?? 0);
        $pu   = (float)($it['precio_unitario'] ?? 0);
        $monto= $cant * $pu;
        $sum += $monto;
      ?>
      <tr>
        <td class="cen"><?= $i ?></td>
        <td><?= htmlspecialchars((string)$it['codigo']) ?></td>
        <td><?= htmlspecialchars((string)($it['descripcion'] ?? '')) ?></td>
        <td class="cen"><?= htmlspecialchars((string)($it['unidad'] ?? '')) ?></td>
        <td class="cen"><?= htmlspecialchars((string)($it['tipo_costo'] ?? '')) ?></td>
        <td class="num"><?= $fmt2($cant) ?></td>
        <td class="num"><?= $clps($pu) ?></td>
        <td class="num"><?= $clps($monto) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top: 10px; display:flex; justify-content:flex-end;">
    <table class="totales">
      <tr><td class="lbl">Subtotal</td><td class="val"><?= $clps($subtotal ?: $sum) ?></td></tr>
      <tr><td class="lbl">Descuento</td><td class="val"><?= $clps($descuento) ?></td></tr>
      <tr><td class="lbl">Impuesto</td><td class="val"><?= $clps($impuesto) ?></td></tr>
      <tr><td class="lbl"><strong>Total</strong></td><td class="val"><strong><?= $clp($total ?: (($subtotal?:$r0($sum)) - $descuento + $impuesto)) ?></strong></td></tr>
    </table>
  </div>

  <div class="footer-nota small">
    Documento generado por Costos · <?= date('Y-m-d H:i') ?>
  </div>
</div>
