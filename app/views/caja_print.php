<?php
/** @var string $base */
/** @var array $periodo */
/** @var array $movimientos */
/** @var array $filters */
/** @var float $sub_ingresos */
/** @var float $sub_egresos */
/** @var float $sub_saldo */
/** @var string $generated_at */
/** @var array $usuario */
$base = rtrim((string)($base ?? ''), '/');
$fmt = function($n){ return number_format((float)$n, 0, ',', ','); };
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Impresión — Caja <?= (int)$periodo['anio'] ?>/<?= str_pad((string)$periodo['mes'],2,'0',STR_PAD_LEFT) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @page { size: A4 landscape; margin: 10mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; }
  .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
  .muted { color:#555; font-size:11px; }
  table { width:100%; border-collapse: collapse; }
  th, td { border:1px solid #000; padding:4px 6px; }
  th { background:#f4f4f4; }
  .text-end { text-align:right; }
  .text-center { text-align:center; }
  .nowrap { white-space:nowrap; }
  .tot { font-weight:bold; background:#eee; }
  .sub { background:#f7f7f7; }
  thead { display: table-header-group; }
  tfoot { display: table-row-group; }
  tr { page-break-inside: avoid; }
</style>
</head>
<body onload="window.print()">
  <div class="header">
    <div>
      <div><strong>Caja chica</strong> — Periodo <?= (int)$periodo['anio'] ?>/<?= str_pad((string)$periodo['mes'],2,'0',STR_PAD_LEFT) ?></div>
      <?php if(!empty($usuario['nombre'])): ?><div class="muted">Usuario: <?= htmlspecialchars($usuario['nombre']) ?></div><?php endif; ?>
    </div>
    <div class="muted">Emitido: <?= htmlspecialchars($generated_at) ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:90px;">Fecha</th>
        <th style="width:90px;">Tipo</th>
        <th style="width:180px;">Documento</th>
        <th>Imputación</th>
        <th class="text-end" style="width:130px;">Monto</th>
        <th style="width:100px;">Estado</th>
        <th>Glosa</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($movimientos)): ?>
        <tr><td colspan="7" class="text-center muted">Sin movimientos</td></tr>
      <?php else: foreach ($movimientos as $r): ?>
        <tr>
          <td class="nowrap"><?= htmlspecialchars(date('Y-m-d', strtotime($r['fecha_mov']))) ?></td>
          <td><?= htmlspecialchars($r['tipo']) ?></td>
          <td>
            <?php
              $doc = ($r['documento_tipo'] ?? 'OTRO');
              if (!empty($r['numero_doc'])) $doc .= ' #'. $r['numero_doc'];
              echo htmlspecialchars($doc);
            ?>
          </td>
          <td>
            <?php
              if (!empty($r['cod_imputacion'])) {
                echo htmlspecialchars($r['cod_imputacion']);
                if (!empty($r['glosa_imputacion'])) echo ' - '. htmlspecialchars($r['glosa_imputacion']);
              } else {
                echo '—';
              }
            ?>
          </td>
          <td class="text-end">$ <?= $fmt($r['monto'] ?? 0) ?></td>
          <td><?= htmlspecialchars($r['estado']) ?></td>
          <td><?= htmlspecialchars($r['descripcion'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <tfoot>
      <tr class="sub">
        <td colspan="4" class="text-end">Subtotal ingresos</td>
        <td class="text-end">$ <?= $fmt($sub_ingresos) ?></td>
        <td colspan="2"></td>
      </tr>
      <tr class="sub">
        <td colspan="4" class="text-end">Subtotal egresos</td>
        <td class="text-end">$ <?= $fmt($sub_egresos) ?></td>
        <td colspan="2"></td>
      </tr>
      <tr class="tot">
        <td colspan="4" class="text-end">Saldo</td>
        <td class="text-end">$ <?= $fmt($sub_saldo) ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
</body>
</html>
