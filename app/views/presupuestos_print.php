<?php
// /app/views/presupuestos_print.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) { header('Location: /index.php'); exit; }
require_once __DIR__ . '/layout/header.php';

$base     = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$proy     = $proyecto ?? [];
$familias = $familias ?? [];
$tp = (float)($total_proy_pres ?? 0);
$tr = (float)($total_proy_real ?? 0);

function fnum($n, $dec=0){ return number_format((float)$n, $dec, ',', '.'); }
function fmon($n){ return '$'.number_format((float)$n, 0, ',', '.'); }
function fporc($num, $den){
  $num = (float)$num; $den = (float)$den;
  if ($den == 0) return '—';
  return fnum(($num / $den) * 100, 1) . ' %';
}
$proyecto_id = (int)($proyecto_id ?? 0);
$proy_label  = trim(($proy['codigo_proy'] ?? ($proy['codigo'] ?? ('#'.$proyecto_id))) . ' — ' . ($proy['nombre'] ?? ''));
?>
<style>
  .print-table-wrap{
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: auto;
    border: 1px solid #dee2e6;
  }
  .table.table-sticky thead th{
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
  }
  /* Si hay dos filas de cabecera, pegamos la segunda justo debajo */
  .table.table-sticky thead tr.head-top th { top: 0; }
  .table.table-sticky thead tr.head-bottom th { top: 34px; } /* ajusta si cambias altura */

  /* Líneas verticales AL FINAL de columnas 3, 6 y 9 (solo fila inferior de cabecera y cuerpo) */
  .table.vlines thead tr.head-bottom th:nth-child(3),
  .table.vlines thead tr.head-bottom th:nth-child(6),
  .table.vlines thead tr.head-bottom th:nth-child(9),
  .table.vlines tbody td:nth-child(3),
  .table.vlines tbody td:nth-child(6),
  .table.vlines tbody td:nth-child(9){
    border-right: 3px double #343a40 !important;
  }

  .row-head-fam { background: #e9ecef; }
  .row-head-grp { background: #f8f9fa; }
  .small-muted { font-size: .85rem; color: #6c757d; }

  /* Bloque de totales finales (aparece una sola vez al final) */
  .final-totals{
    border: 1px solid #dee2e6;
    border-top: 0;
    padding: .75rem 1rem;
    background: #f8f9fa;
  }
  .final-totals .label{ color:#6c757d; }
  .final-totals .val{ font-weight: 600; }

  @media print {
    .no-print { display: none !important; }
    .print-table-wrap{
      overflow: visible !important;
      max-height: none !important;
      border: 0;
    }
    .table.table-sticky thead th{
      position: static !important;
      background: #f8f9fa !important;
    }
    thead { display: table-header-group; }
    /* No usamos tfoot para evitar repetición por página */
    .table th, .table td { font-size: 12px; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center gap-3 mb-3">
    <div>
      <h4 class="mb-0">Presupuesto — <span class="text-muted"><?= htmlspecialchars($proy_label) ?></span></h4>
      <div class="small-muted">
        Total proyecto (Pres): <strong><?= fmon($tp) ?></strong> ·
        Real: <strong><?= fmon($tr) ?></strong> ·
        Variación: <strong><?= fmon($tr - $tp) ?></strong> ·
        Avance: <strong><?= fporc($tr, $tp) ?></strong>
      </div>
    </div>
    <div class="ms-auto no-print d-flex gap-2">
      <a class="btn btn-sm btn-secondary" href="<?= $base ?>/presupuestos?proyecto_id=<?= (int)$proyecto_id ?>">Volver</a>
      <button class="btn btn-sm btn-primary" onclick="window.print()">Imprimir</button>
    </div>
  </div>

  <?php if (empty($familias)): ?>
    <div class="alert alert-info">No hay ítems para imprimir en este proyecto.</div>
  <?php else: ?>

  <div class="table-responsive print-table-wrap">
    <table class="table table-bordered table-hover table-sm align-middle vlines table-sticky">
      <thead class="table-light">
        <!-- Fila superior: 4 grupos 3-3-3-2 -->
        <tr class="head-top">
          <th class="text-center" colspan="3">ITEM'S DE COSTO</th>
          <th class="text-center" colspan="3">TARGET</th>
          <th class="text-center" colspan="3">REAL</th>
          <th class="text-center" colspan="2">VARIACIÓN</th>
        </tr>
        <!-- Fila inferior: 11 columnas -->
        <tr class="head-bottom">
          <th style="width:120px">Código</th>
          <th>Descripción</th>
          <th style="width:70px"  class="text-center">Unidad</th>
          <th style="width:100px" class="text-end">Cant. Pres.</th>
          <th style="width:120px" class="text-end">P.Unit Pres.</th>
          <th style="width:120px" class="text-end">Subtotal Pres.</th>
          <th style="width:100px" class="text-end">Cant. Real</th>
          <th style="width:120px" class="text-end">P.Unit Real</th>
          <th style="width:120px" class="text-end">Subtotal Real</th>
          <th style="width:120px" class="text-end">Variación</th>
          <th style="width:90px"  class="text-end">% Avance</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($familias as $f => $F): ?>
          <?php
            $famPres = (float)$F['pres']; $famReal = (float)$F['real'];
            $famVar  = $famReal - $famPres;
          ?>
          <!-- Cabecera FAMILIA -->
          <tr class="row-head-fam fw-semibold">
            <td><?= htmlspecialchars($f) ?>0000000</td>
            <td>F<?= htmlspecialchars($f) ?> — <?= htmlspecialchars($F['descripcion'] ?: '(sin descripción)') ?></td>
            <td class="text-center">—</td>
            <td class="text-end">—</td>
            <td class="text-end">—</td>
            <td class="text-end"><?= fmon($famPres) ?></td>
            <td class="text-end">—</td>
            <td class="text-end">—</td>
            <td class="text-end"><?= fmon($famReal) ?></td>
            <td class="text-end"><?= fmon($famVar) ?></td>
            <td class="text-end"><?= fporc($famReal, $famPres) ?></td>
          </tr>

          <?php foreach ($F['grupos'] as $g => $G): ?>
            <?php
              $grpPres = (float)$G['pres']; $grpReal = (float)$G['real'];
              $grpVar  = $grpReal - $grpPres;
            ?>
            <!-- Cabecera GRUPO -->
            <tr class="row-head-grp">
              <td><?= htmlspecialchars($f.$g) ?>0000</td>
              <td>G<?= htmlspecialchars($g) ?> — <?= htmlspecialchars($G['descripcion'] ?: '(sin descripción)') ?></td>
              <td class="text-center">—</td>
              <td class="text-end">—</td>
              <td class="text-end">—</td>
              <td class="text-end"><?= fmon($grpPres) ?></td>
              <td class="text-end">—</td>
              <td class="text-end">—</td>
              <td class="text-end"><?= fmon($grpReal) ?></td>
              <td class="text-end"><?= fmon($grpVar) ?></td>
              <td class="text-end"><?= fporc($grpReal, $grpPres) ?></td>
            </tr>

            <?php foreach ($G['items'] as $it): ?>
              <?php
                $cantPres  = (float)($it['cant_pres']  ?? 0);
                $punitPres = (float)($it['punit_pres'] ?? 0);
                $cantReal  = (float)($it['cant_real']  ?? 0);
                $punitReal = (float)($it['punit_real'] ?? 0);

                $subPres = isset($it['sub_pres']) ? (float)$it['sub_pres'] : ($cantPres * $punitPres);
                $subReal = isset($it['sub_real']) ? (float)$it['sub_real'] : ($cantReal * $punitReal);
                $var     = $subReal - $subPres;
              ?>
              <tr>
                <td><code><?= htmlspecialchars($it['codigo']) ?></code></td>
                <td><?= htmlspecialchars($it['descripcion'] ?: '') ?></td>
                <td class="text-center"><?= htmlspecialchars($it['unidad'] ?: '') ?></td>
                <td class="text-end"><?= fnum($cantPres, 2) ?></td>
                <td class="text-end"><?= fmon($punitPres) ?></td>
                <td class="text-end"><?= fmon($subPres) ?></td>
                <td class="text-end"><?= fnum($cantReal, 2) ?></td>
                <td class="text-end"><?= fmon($punitReal) ?></td>
                <td class="text-end"><?= fmon($subReal) ?></td>
                <td class="text-end"><?= fmon($var) ?></td>
                <td class="text-end"><?= fporc($subReal, $subPres) ?></td>
              </tr>
            <?php endforeach; ?>

          <?php endforeach; ?>

        <?php endforeach; ?>
      </tbody>
      <!-- Sin tfoot: evitamos repetición en cada página -->
    </table>
  </div>

  <!-- Totales de proyecto SOLO al final del listado (última página en impresión) -->
  <div class="final-totals mt-2">
    <div class="d-flex flex-wrap gap-3">
      <div><span class="label">Total proyecto (Pres):</span> <span class="val"><?= fmon($tp) ?></span></div>
      <div><span class="label">Real:</span> <span class="val"><?= fmon($tr) ?></span></div>
      <div><span class="label">Variación:</span> <span class="val"><?= fmon($tr - $tp) ?></span></div>
      <div><span class="label">Avance:</span> <span class="val"><?= fporc($tr, $tp) ?></span></div>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
