<?php
/** @var string $base */
/** @var array  $periodo */
/** @var array  $movimientos */
/** @var bool   $editable */
/** @var array  $filters */
/** @var float  $sub_ingresos */
/** @var float  $sub_egresos */
/** @var float  $sub_saldo */
/** @var int    $result_count */
/** @var bool   $can_ingresar */

$base = rtrim((string)($base ?? ''), '/');
$fmt = function($n){ return number_format((float)$n, 0, ',', ','); };
$anioSel = (int)($periodo['anio'] ?? date('Y'));
$mesSel  = (int)($periodo['mes']  ?? date('n'));
?>
 <style>
 <!--
body{padding-top:4.5rem; 	
background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); 
background-color: transparent;	background-repeat: repeat;}	
-->
</style>
<div class="mx-auto d-block " style="align:center;">
<div class="container my-4" data-base="<?= htmlspecialchars($base, ENT_QUOTES) ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Caja chica — <?= (int)$periodo['anio']; ?>/<?= str_pad((string)$periodo['mes'],2,'0',STR_PAD_LEFT) ?></h4>
    <div class="d-flex gap-2">
      <a class="btn btn-success"
         href="<?= $base ?>/caja/imprimir?anio=<?= (int)$periodo['anio'] ?>&mes=<?= (int)$periodo['mes'] ?>">Imprimir</a>
      <a class="btn btn-primary" href="<?= $base ?>/caja/create?caja_id=<?= (int)$periodo['id'] ?>">Nuevo movimiento</a>
      <?php if ($can_ingresar): ?>
        <a class="btn btn-success" href="<?= $base ?>/caja/ingresos">Ingresar a caja de usuario...</a>
      <?php endif; ?>
    </div>
  </div>

  <form id="frm-filtros" class="row g-2 align-items-end mb-3" method="get" action="<?= $base ?>/caja">
    <div class="col-auto">
      <label class="form-label">Año</label>
      <input type="number" min="2000" max="2099" name="anio" class="form-control"
             value="<?= (int)($filters['anio'] ?? $anioSel) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label">Mes</label>
      <input type="number" min="1" max="12" name="mes" class="form-control"
             value="<?= (int)($filters['mes'] ?? $mesSel) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="glosa / Nº doc / código">
    </div>
    <div class="col-md-2">
      <label class="form-label">Tipo</label>
      <select name="tipo" class="form-select">
        <option value="">Todos</option>
        <?php foreach (['INGRESO','EGRESO','TRASPASO_IN','TRASPASO_OUT','AJUSTE'] as $t): ?>
          <option value="<?= $t ?>" <?= (($filters['tipo']??'')===$t?'selected':'') ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Doc</label>
      <select name="doc_tipo" class="form-select">
        <option value="">Todos</option>
        <?php foreach (['BOLETA','FACTURA','RECIBO','OTRO'] as $t): ?>
          <option value="<?= $t ?>" <?= (($filters['doc_tipo']??'')===$t?'selected':'') ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-select">
        <option value="">Todos</option>
        <?php foreach (['PENDIENTE','APROBADO','ANULADO'] as $t): ?>
          <option value="<?= $t ?>" <?= (($filters['estado']??'')===$t?'selected':'') ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1">
      <label class="form-label">Orden</label>
      <select name="orden" class="form-select">
        <option value="DESC" <?= (($filters['orden']??'')==='DESC'?'selected':'') ?>>↓</option>
        <option value="ASC"  <?= (($filters['orden']??'')==='ASC'?'selected':'') ?>>↑</option>
      </select>
    </div>
    <div class="col-auto">
      <button id="btn-filtrar" class="btn btn-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:110px;">Fecha</th>
          <th>Tipo</th>
          <th>Documento</th>
          <th>Imputación</th>
          <th class="text-end" style="width:140px;">Monto</th>
          <th style="width:110px;">Estado</th>
          <th style="width:160px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($movimientos)): ?>
        <tr><td colspan="7" class="text-muted text-center">Sin movimientos.</td></tr>
      <?php else: foreach ($movimientos as $r): ?>
        <tr>
          <td><?= htmlspecialchars(date('Y-m-d', strtotime($r['fecha_mov']))) ?></td>
          <td><?= htmlspecialchars($r['tipo']) ?></td>
          <td>
            <?php
              $doc = ($r['documento_tipo'] ?? 'OTRO');
              if (!empty($r['numero_doc'])) $doc .= ' #'. $r['numero_doc'];
              echo htmlspecialchars($doc);
            ?>
          </td>
          <td>
            <?php if (!empty($r['cod_imputacion'])): ?>
              <span class="badge bg-secondary"><?= htmlspecialchars($r['cod_imputacion']) ?></span>
              <small class="text-muted"><?= htmlspecialchars($r['glosa_imputacion'] ?? '') ?></small>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-end fw-semibold">$ <?= $fmt($r['monto'] ?? 0) ?></td>
          <td><?= htmlspecialchars($r['estado']) ?></td>
          <td>
            <div class="btn-group btn-group-sm">
              <a class="btn btn-outline-secondary" href="<?= $base ?>/caja/show/<?= (int)$r['id'] ?>">Ver</a>
              <?php if ($editable): ?>
                <a class="btn btn-outline-primary" href="<?= $base ?>/caja/edit/<?= (int)$r['id'] ?>">Editar</a>
                <form method="post" action="<?= $base ?>/caja/delete/<?= (int)$r['id'] ?>" onsubmit="return confirm('¿Eliminar movimiento?');" style="display:inline;">
                  <button class="btn btn-outline-danger">Borrar</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="4" class="text-end">Subtotal ingresos</th>
          <th class="text-end">$ <?= $fmt($sub_ingresos) ?></th>
          <th colspan="2"></th>
        </tr>
        <tr class="table-light">
          <th colspan="4" class="text-end">Subtotal egresos</th>
          <th class="text-end">$ <?= $fmt($sub_egresos) ?></th>
          <th colspan="2"></th>
        </tr>
        <tr class="table-secondary">
          <th colspan="4" class="text-end">Saldo (ingresos - egresos)</th>
          <th class="text-end">$ <?= $fmt($sub_saldo) ?></th>
          <th colspan="2"></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
</div>
<script>
  // Habilitar submit por botón "Filtrar" si no tienes jQuery cargado acá
  document.getElementById('btn-filtrar')?.addEventListener('click', function(e){
    e.preventDefault(); document.getElementById('frm-filtros').submit();
  });
</script>
