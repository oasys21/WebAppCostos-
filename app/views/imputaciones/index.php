<?php
// app/views/imputaciones/index.php
declare(strict_types=1);
$h    = fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base = rtrim((string)($GLOBALS['cfg']['BASE_URL'] ?? ''),'/');

$pageTitle = 'Imputaciones';
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="m-0">Imputaciones</h3>
  <a class="btn btn-success" href="<?= $h($base) ?>/compras">Volver a Compras</a>
</div>

<form class="row g-2 mb-3" method="get" action="<?= $h($base) ?>/imputaciones/index">
  <div class="col-md-3">
    <label class="form-label">Proveedor</label>
    <select name="proveedor_id" class="form-select">
      <option value="">— Todos —</option>
      <?php foreach(($proveedores??[]) as $pv): ?>
        <option value="<?= (int)$pv['id'] ?>" <?= (!empty($filters['proveedor_id']) && (int)$filters['proveedor_id']===(int)$pv['id']?'selected':'') ?>>
          <?= $h($pv['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Proyecto</label>
    <select name="proyecto_id" class="form-select">
      <option value="">— Todos —</option>
      <?php foreach(($proyectos??[]) as $pr): ?>
        <option value="<?= (int)$pr['id'] ?>" <?= (!empty($filters['proyecto_id']) && (int)$filters['proyecto_id']===(int)$pr['id']?'selected':'') ?>>
          <?= $h($pr['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" value="<?= $h($filters['desde'] ?? '') ?>" class="form-control">
  </div>
  <div class="col-md-2">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" value="<?= $h($filters['hasta'] ?? '') ?>" class="form-control">
  </div>
  <div class="col-md-2">
    <label class="form-label">Buscar</label>
    <input type="text" name="q" value="<?= $h($filters['q'] ?? '') ?>" class="form-control" placeholder="folio, desc, código">
  </div>
  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary">Filtrar</button>
    <a class="btn btn-outline-secondary" href="<?= $h($base) ?>/imputaciones/index">Limpiar</a>
  </div>
</form>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPend" type="button" role="tab">
      Pendientes <?= isset($pendientes)?'('.count($pendientes).')':'' ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabApl" type="button" role="tab">
      Aplicadas <?= isset($realizadas)?'('.count($realizadas).')':'' ?>
    </button>
  </li>
</ul>

<div class="tab-content">
  <!-- PENDIENTES -->
  <div class="tab-pane fade show active" id="tabPend" role="tabpanel">
    <?php if (empty($pendientes)): ?>
      <div class="alert alert-success">No hay imputaciones pendientes con los filtros actuales.</div>
    <?php else: ?>
      <form method="post" action="<?= $h($base) ?>/index.php?r=imputaciones/procesar">
        <input type="hidden" name="form_token" value="<?= $h($_SESSION['form_token'] ?? bin2hex(random_bytes(8))) ?>">

        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary" title="Procesa solo las seleccionadas">Procesar seleccionadas</button>
            <button type="submit" name="scope" value="all" class="btn btn-sm btn-success" title="Procesa todas las pendientes completas">Procesar todo listo</button>
          </div>
          <div class="small text-muted">
            <span class="badge bg-success">Listo</span>: tiene Proyecto + Ítem de costo ·
            <span class="badge bg-warning text-dark">Incompleto</span>: falta definir alguno.
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th style="width:28px;"><input type="checkbox" onclick="document.querySelectorAll('.chk-imp').forEach(cb=>cb.checked=this.checked)"></th>
                <th>#</th>
                <th>Fecha Doc</th>
                <th>Doc</th>
                <th>Proveedor</th>
                <th>Ítem compra</th>
                <th>Monto</th>
                <th>Proyecto</th>
                <th>Ítem costo</th>
                <th style="width:160px">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($pendientes as $r): ?>
              <?php
                $monto = (float)($r['monto_imputado'] ?? 0);
                $labelPc = trim(($r['pcodigo'] ?? $r['codigo'] ?? '').' · '.($r['pcosto_glosa'] ?? ''));
                $completo = (int)($r['completo'] ?? 0) === 1;
              ?>
              <tr>
                <td>
                  <?php if ($completo): ?>
                    <input type="checkbox" class="form-check-input chk-imp" name="ids[]" value="<?= (int)$r['id'] ?>">
                  <?php endif; ?>
                </td>
                <td>
                  <?= (int)$r['id'] ?>
                  <?php if ($completo): ?>
                    <span class="badge bg-success ms-1">Listo</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark ms-1">Incompleto</span>
                  <?php endif; ?>
                </td>
                <td><?= $h($r['fecha_doc'] ?? '') ?></td>
                <td><?= $h(($r['tipo_doc'] ?? '').' '.$r['folio']) ?></td>
                <td><?= $h($r['proveedor'] ?? '') ?></td>
                <td>
                  <div><code><?= $h($r['item_codigo'] ?? '') ?></code> · L<?= (int)($r['linea'] ?? 0) ?></div>
                  <?php if (!empty($r['item_desc'])): ?>
                    <div class="small text-muted"><?= $h($r['item_desc']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= $h(number_format($monto,2)) ?></td>
                <td><?= $h($r['proyecto_nombre'] ?? '(sin proyecto)') ?></td>
                <td><?= $h($labelPc !== ' · ' ? $labelPc : '(sin ítem)') ?></td>
                <td class="d-flex gap-2">
                  <a class="btn btn-sm btn-primary"
                     href="<?= $h($base) ?>/imputaciones/create/<?= (int)$r['compra_item_id'] ?><?= !empty($r['proyecto_id']) ? '?proyecto_id='.(int)$r['proyecto_id'] : '' ?>">
                    Imputar
                  </a>
                  <a class="btn btn-sm btn-outline-secondary"
                     href="<?= $h($base) ?>/imputaciones/edit/<?= (int)$r['id'] ?>">
                    Editar
                  </a>
                  <a class="btn btn-sm btn-outline-dark"
                     href="<?= $h($base) ?>/compras/ver/<?= (int)$r['compra_id'] ?>">
                    Ver compra
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
    <?php endif; ?>
  </div>

<!-- APLICADAS -->
  <div class="tab-pane fade" id="tabApl" role="tabpanel">
    <?php if (empty($realizadas)): ?>
      <div class="alert alert-info">No hay imputaciones aplicadas con los filtros actuales.</div>
    <?php else: ?>
      <form method="post" action="<?= $h($base) ?>/index.php?r=imputaciones/revertir" onsubmit="return validarMotivoRev(this);">
        <input type="hidden" name="form_token" value="<?= $h($_SESSION['form_token'] ?? bin2hex(random_bytes(8))) ?>">
        <div class="d-flex align-items-end justify-content-between mb-2">
          <div class="me-3" style="min-width:340px;">
            <label class="form-label">Motivo de reversión (obligatorio)</label>
            <input type="text" name="motivo" class="form-control" maxlength="240" required placeholder="Ej.: Nota de crédito, error de imputación, etc.">
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-danger">Revertir seleccionadas</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th style="width:28px;"><input type="checkbox" onclick="document.querySelectorAll('.chk-apl').forEach(cb=>cb.checked=this.checked)"></th>
                <th>#</th>
                <th>Procesado</th>
                <th>Doc</th>
                <th>Proveedor</th>
                <th>Proyecto</th>
                <th>Ítem costo</th>
                <th>Ítem compra</th>
                <th>Monto</th>
                <th style="width:140px">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($realizadas as $r): ?>
              <?php
                $monto = (float)($r['monto_imputado'] ?? 0);
                $labelPc = trim(($r['pcodigo'] ?? $r['codigo'] ?? '').' · '.($r['pcosto_glosa'] ?? ''));
              ?>
              <tr>
                <td><input type="checkbox" class="form-check-input chk-apl" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
                <td><?= (int)$r['id'] ?></td>
                <td>
                  <div><?= $h($r['fecha_imputacion'] ?? '') ?></div>
                  <span class="badge bg-success">Aplicada</span>
                </td>
                <td><?= $h(($r['tipo_doc'] ?? '').' '.$r['folio']) ?></td>
                <td><?= $h($r['proveedor'] ?? '') ?></td>
                <td><?= $h($r['proyecto_nombre'] ?? '') ?></td>
                <td><?= $h($labelPc !== ' · ' ? $labelPc : '') ?></td>
                <td>
                  <div><code><?= $h($r['item_codigo'] ?? '') ?></code> · L<?= (int)($r['linea'] ?? 0) ?></div>
                  <?php if (!empty($r['item_desc'])): ?>
                    <div class="small text-muted"><?= $h($r['item_desc']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= $h(number_format($monto,2)) ?></td>
                <td class="d-flex gap-2">
                  <!-- Reversión individual con prompt de motivo -->
                  <button type="button" class="btn btn-sm btn-outline-danger"
                          onclick="revertirUno(<?= (int)$r['id'] ?>)">
                    Revertir
                  </button>
                  <a class="btn btn-sm btn-outline-dark"
                     href="<?= $h($base) ?>/compras/ver/<?= (int)$r['compra_id'] ?>">
                    Ver compra
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
  
      <script>
      function validarMotivoRev(f){
        const m = (f.motivo?.value || '').trim();
        if(!m){ alert('Debes indicar un motivo de reversión.'); return false; }
        return true;
      }
      function revertirUno(id){
        const m = prompt('Motivo de reversión (obligatorio):');
        if(!m) return;
        // construye y envía un form POST temporal
        const f = document.createElement('form');
        f.method='post';
        f.action='<?= $h($base) ?>/index.php?r=imputaciones/revertir';
        const t=document.createElement('input'); t.type='hidden'; t.name='motivo'; t.value=m; f.appendChild(t);
        const i=document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=id; f.appendChild(i);
        document.body.appendChild(f);
        f.submit();
      }
      </script>
    <?php endif; ?>
  </div>
  
  
  
</div>
