<?php
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? (isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'],'/') : '');
$isEdit  = !empty($compra['id']);
$action  = $isEdit
  ? ($base.'/index.php?r=compras/actualizar/'.(int)$compra['id'])
  : ($base.'/index.php?r=compras/guardar');

if (!isset($_SESSION)) @session_start();
if (empty($_SESSION['form_token'])) $_SESSION['form_token'] = bin2hex(random_bytes(16));

// valores iniciales seguros
$initSubt = (float)($compra['subtotal']  ?? 0);
$initDesc = (float)($compra['descuento'] ?? 0);
$initImp  = (float)($compra['impuesto']  ?? 0);
$baseGrav = max($initSubt - $initDesc, 0.0);
$initIvaPct = $baseGrav > 0 ? round(($initImp*100)/$baseGrav, 2) : 19.00;
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
<div class="">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><?= $isEdit ? 'Editar compra' : 'Nueva compra' ?></strong>
  </div>
  <div class="card-body">
    <form method="post" action="<?= $h($action) ?>" id="frmCompra" autocomplete="off" novalidate>
      <input type="hidden" name="form_token" value="<?= $h($_SESSION['form_token']) ?>">

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Proveedor</label>
          <select name="proveedor_id" class="form-select" required>
            <option value="">— seleccionar —</option>
            <?php foreach ($proveedores as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((int)($compra['proveedor_id'] ?? 0)===(int)$p['id']?'selected':'') ?>>
                <?= $h($p['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Tipo Doc</label>
          <select name="tipo_doc" class="form-select" required>
            <?php foreach (['FAC','BOL','NC','ND','OC'] as $td): ?>
              <option value="<?= $h($td) ?>" <?= (($compra['tipo_doc']??'FAC')===$td?'selected':'') ?>><?= $h($td) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Folio</label>
          <input name="folio" class="form-control" value="<?= $h($compra['folio'] ?? '') ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha_doc" class="form-control" value="<?= $h($compra['fecha_doc'] ?? date('Y-m-d')) ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Proyecto (cabecera, opcional)</label>
          <select name="proyecto_id" id="proyectoCab" class="form-select">
            <option value="">— ninguno —</option>
            <?php foreach ($proyectos as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((int)($compra['proyecto_id'] ?? 0)===(int)$p['id']?'selected':'') ?>>
                <?= $h($p['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Ítem costo por defecto (opcional)</label>
          <select name="pcosto_defecto_id" id="pcostoCab" class="form-select">
            <option value="">— ninguno —</option>
          </select>
          <div class="form-text">Si eliges uno aquí, se usará en los ítems que no indiquen su propio ítem.</div>
        </div>

        <div class="col-md-2">
          <label class="form-label">Moneda</label>
          <select name="moneda" class="form-select">
            <?php foreach (['CLP','USD','EUR'] as $m): ?>
              <option value="<?= $h($m) ?>" <?= (($compra['moneda']??'CLP')===$m?'selected':'') ?>><?= $h($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Tipo cambio</label>
          <input name="tipo_cambio" class="form-control" value="<?= $h($compra['tipo_cambio'] ?? '1.000000') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Observaciones</label>
          <textarea name="observaciones" class="form-control" rows="4"><?= $h($compra['observaciones'] ?? '') ?></textarea>
        </div>

        <!-- ===== Resumen de Totales (normalizado) ===== -->
        <div class="col-sm-2">
          <label class="form-label">Subtotal</label>
          <input id="vSubtotal" name="subtotal" class="form-control text-end" value="<?= number_format($initSubt, 2, '.', '') ?>" readonly>
        </div>
        <div class="col-sm-1">
          <label class="form-label">Descuento</label>
          <input id="vDescuento" name="descuento" class="form-control text-end" value="<?= number_format($initDesc, 2, '.', '') ?>">
          <div class="form-text">Monto (no %)</div>
        </div>
        <div class="col-sm-1">
          <label class="form-label">IVA %</label>
          <input id="vIvaPct" class="form-control text-end" value="<?= number_format($initIvaPct, 2, '.', '') ?>">
          <div class="form-text">Ej: 19,00</div>
        </div>
        <div class="col-sm-2">
          <label class="form-label">Impuesto</label>
          <input id="vImpuesto" name="impuesto" class="form-control text-end" value="<?= number_format($initImp, 2, '.', '') ?>" readonly>
        </div>
        <div class="col-sm-2">
          <label class="form-label">Total</label>
          <input id="vTotal" class="form-control text-end" value="<?= number_format(max($initSubt - $initDesc + $initImp,0), 2, '.', '') ?>" readonly>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-2 mt-3">
        <h6 class="mb-0">Ítems de la compra</h6>
        <div class="btn-group">
          <button type="button" class="btn btn-sm btn-secondary" id="btnAddRow">Agregar ítem</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle" id="itemsTbl">
          <thead class="table-light">
            <tr>
              <th style="width:120px">Código</th>
              <th style="width:360px">Descripción</th>
              <th style="width:90px">Unidad</th>
              <th style="width:120px" class="text-end">Cantidad</th>
              <th style="width:140px" class="text-end">Precio</th>
              <th style="width:140px" class="text-end">Valor Neto</th>
              <th style="width:220px">Proyecto</th>
              <th style="width:260px">Ítem de costo</th>
              <th style="width:80px"></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $rows = is_array($items) && count($items) ? $items : [
              ['codigo'=>'','descripcion'=>'','unidad'=>'UND','cantidad'=>'1.00','precio_unitario'=>'0.00','subtotal'=>'0.00','imp_proyecto_id'=>'','imp_pcosto_id'=>'']
            ];
            foreach ($rows as $i => $it):
              $m = (float)($it['cantidad'] ?? 0)*(float)($it['precio_unitario'] ?? 0);
            ?>
            <tr>
              <td><input name="items[<?= $i ?>][codigo]" class="form-control form-control-sm" value="<?= $h($it['codigo'] ?? '') ?>" required></td>
              <td><input name="items[<?= $i ?>][descripcion]" class="form-control form-control-sm" value="<?= $h($it['descripcion'] ?? '') ?>"></td>
              <td><input name="items[<?= $i ?>][unidad]" class="form-control form-control-sm" value="<?= $h($it['unidad'] ?? 'UND') ?>"></td>
              <td><input name="items[<?= $i ?>][cantidad]" class="form-control form-control-sm text-end item-cant" value="<?= $h($it['cantidad'] ?? '1.00') ?>"></td>
              <td><input name="items[<?= $i ?>][precio_unitario]" class="form-control form-control-sm text-end item-precio" value="<?= $h($it['precio_unitario'] ?? '0.00') ?>"></td>
              <td><input name="items[<?= $i ?>][subtotal]" class="form-control form-control-sm text-end item-subtotal" value="<?= number_format($m,2,'.','') ?>" readonly></td>
              <td>
                <select name="items[<?= $i ?>][imp_proyecto_id]" class="form-select form-select-sm imp-proy">
                  <option value="">— (usar cabecera o ninguno) —</option>
                  <?php foreach ($proyectos as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= ((string)($it['imp_proyecto_id'] ?? '')===(string)$p['id']?'selected':'') ?>><?= $h($p['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select name="items[<?= $i ?>][imp_pcosto_id]" class="form-select form-select-sm imp-pcosto" data-selected="<?= $h($it['imp_pcosto_id'] ?? '') ?>">
                  <option value="">— (usar defecto o ninguno) —</option>
                </select>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger btnDelRow">&times;</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-secondary" href="<?= $h($base) ?>/compras">Cancelar</a>
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Guardar' ?></button>
      </div>
    </form>
  </div>
</div>
</div>
<script>
(function(){
  const BASE   = <?= json_encode($base) ?>;
  const frm    = document.getElementById('frmCompra');
  const tbl    = document.getElementById('itemsTbl').querySelector('tbody');
  const btnAdd = document.getElementById('btnAddRow');

  const $subt = document.getElementById('vSubtotal');
  const $desc = document.getElementById('vDescuento');
  const $ivaP = document.getElementById('vIvaPct');
  const $imp  = document.getElementById('vImpuesto');
  const $tot  = document.getElementById('vTotal');

  // --------- Helpers numéricos (locale-safe) ----------
  const parseN = (raw)=> {
    let v = String(raw ?? '').trim();
    if (!v) return 0;
    v = v.replace(/\s+/g,'');             // quita espacios
    // Si contiene coma y punto: el separador decimal es el ÚLTIMO que aparezca
    const hasComma = v.includes(',');
    const hasDot   = v.includes('.');
    if (hasComma && hasDot){
      const lastComma = v.lastIndexOf(',');
      const lastDot   = v.lastIndexOf('.');
      const decSep = lastComma > lastDot ? ',' : '.';
      const thouSep = decSep === ',' ? '.' : ',';
      v = v.split(thouSep).join('');      // elimina miles
      if (decSep === ',') v = v.replace(/,/g,'.');
    } else if (hasComma && !hasDot){
      // sólo coma: úsala como decimal
      v = v.replace(/,/g,'.');
    } else if (hasDot && !hasComma){
      // sólo puntos: si hay más de uno, el último es decimal
      const cnt = (v.match(/\./g) || []).length;
      if (cnt > 1){
        const last = v.lastIndexOf('.');
        v = v.slice(0,last).replace(/\./g,'') + '.' + v.slice(last+1);
      }
    }
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
  };

  const clamp = (n,min,max)=> Math.min(Math.max(n,min),max);
  const fmt2 = (n)=> (Math.round((n + Number.EPSILON)*100)/100).toFixed(2);

  // --------- Plantilla de fila ----------
  function rowTemplate(idx){
    return `
      <tr>
        <td><input name="items[${idx}][codigo]" class="form-control form-control-sm" required></td>
        <td><input name="items[${idx}][descripcion]" class="form-control form-control-sm"></td>
        <td><input name="items[${idx}][unidad]" class="form-control form-control-sm" value="UND"></td>
        <td><input name="items[${idx}][cantidad]" class="form-control form-control-sm text-end item-cant" value="1.00"></td>
        <td><input name="items[${idx}][precio_unitario]" class="form-control form-control-sm text-end item-precio" value="0.00"></td>
        <td><input name="items[${idx}][subtotal]" class="form-control form-control-sm text-end item-subtotal" value="0.00" readonly></td>
        <td>
          <select name="items[${idx}][imp_proyecto_id]" class="form-select form-select-sm imp-proy">
            <option value="">— (usar cabecera o ninguno) —</option>
            <?php foreach ($proyectos as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= $h($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select name="items[${idx}][imp_pcosto_id]" class="form-select form-select-sm imp-pcosto">
            <option value="">— (usar defecto o ninguno) —</option>
          </select>
        </td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-danger btnDelRow">&times;</button>
        </td>
      </tr>`;
  }

  // --------- Carga ítems de costo por proyecto (AJAX) ----------
  async function fetchPcosto(proyectoId){
    if(!proyectoId) return [];
    const url = BASE + '/index.php?r=compras/pcostos&proyecto_id=' + encodeURIComponent(proyectoId);
    const res = await fetch(url);
    if(!res.ok) return [];
    return await res.json();
  }

  async function populatePcostoSelect(sel, proyectoId, selectedId){
    if(!sel) return;
    sel.innerHTML = '<option value="">— (usar defecto o ninguno) —</option>';
    if(!proyectoId) return;
    try{
      const rows = await fetchPcosto(proyectoId);
      let html = '<option value="">— (usar defecto o ninguno) —</option>';
      rows.forEach(r=>{
        const selAttr = (selectedId && String(selectedId)===String(r.id)) ? ' selected' : '';
        html += `<option value="${r.id}"${selAttr}>${r.codigo} · ${r.nombre}</option>`;
      });
      sel.innerHTML = html;
    }catch(e){ console.error(e); }
  }

  // --------- Cálculo por fila y totales ----------
  function calcRow(tr){
    const q   = parseN(tr.querySelector('.item-cant')?.value || 0);
    const pu  = parseN(tr.querySelector('.item-precio')?.value || 0);
    const net = Math.max(q * pu, 0);
    const $st = tr.querySelector('.item-subtotal');
    if ($st) $st.value = fmt2(net);
    return net;
  }

  function calcAll(){
    let subtotal = 0;
    tbl.querySelectorAll('tr').forEach(tr => { subtotal += calcRow(tr); });

    const descuento = Math.max(parseN($desc.value), 0);
    const baseGrav  = Math.max(subtotal - descuento, 0);
    let ivaPct      = parseN($ivaP.value);
    ivaPct          = clamp(ivaPct, 0, 100);    // evita 1900% o similares

    const impuesto  = Math.max(baseGrav * (ivaPct/100.0), 0);
    const total     = Math.max(baseGrav + impuesto, 0);

    $subt.value = fmt2(subtotal);
    $imp.value  = fmt2(impuesto);
    $tot.value  = fmt2(total);
  }

  function attachRowHandlers(tr){
    const sProy   = tr.querySelector('.imp-proy');
    const sPcosto = tr.querySelector('.imp-pcosto');
    const selId   = sPcosto?.getAttribute('data-selected') || null;

    if (sProy && sProy.value) populatePcostoSelect(sPcosto, sProy.value, selId);
    sProy?.addEventListener('change', ()=> populatePcostoSelect(sPcosto, sProy.value, null));

    tr.querySelector('.btnDelRow')?.addEventListener('click', ()=>{
      tr.remove();
      renumerar();
      calcAll();
    });

    tr.querySelectorAll('.item-cant,.item-precio').forEach(el=>{
      el.addEventListener('input', calcAll);
      el.addEventListener('change', calcAll);
    });
  }

  function renumerar(){
    const rows = tbl.querySelectorAll('tr');
    rows.forEach((tr, idx)=>{
      tr.querySelectorAll('input,select,textarea').forEach(el=>{
        const name = el.getAttribute('name');
        if (!name) return;
        el.setAttribute('name', name.replace(/items\[\d+\]/, 'items['+idx+']'));
      });
    });
  }

  // Botón agregar fila
  btnAdd?.addEventListener('click', ()=>{
    const idx = tbl.querySelectorAll('tr').length;
    const tmp = document.createElement('tbody');
    tmp.innerHTML = rowTemplate(idx);
    const tr = tmp.firstElementChild;
    tbl.appendChild(tr);
    attachRowHandlers(tr);
    calcAll();
  });

  // Al cambiar descuento/iva => recalcular
  $desc?.addEventListener('input', calcAll);
  $desc?.addEventListener('change', calcAll);
  $ivaP?.addEventListener('input', calcAll);
  $ivaP?.addEventListener('change', calcAll);

  // Cargar pcosto por cabecera al cambiar
  async function loadCabeceraPcosto(){
    const proySel = document.getElementById('proyectoCab');
    const pcSel   = document.getElementById('pcostoCab');
    if (!proySel || !pcSel) return;
    const pid = proySel.value || '';
    pcSel.innerHTML = '<option value="">— ninguno —</option>';
    if(!pid) return;
    try{
      const rows = await fetchPcosto(pid);
      let html = '<option value="">— ninguna —</option>';
      const selected = <?= json_encode($compra['__pcosto_defecto_id'] ?? null) ?>;
      rows.forEach(r=>{
        const sel = (selected && String(selected)===String(r.id)) ? ' selected' : '';
        html += `<option value="${r.id}"${sel}>${r.codigo} · ${r.nombre}</option>`;
      });
      pcSel.innerHTML = html;
    }catch(e){ console.error(e); }
  }
  document.getElementById('proyectoCab')?.addEventListener('change', loadCabeceraPcosto);
  loadCabeceraPcosto();

  // Inicializa handlers en filas existentes y calcula
  tbl.querySelectorAll('tr').forEach(attachRowHandlers);
  calcAll();

  // Asegura que antes de enviar queden los números recalculados y formateados
  frm?.addEventListener('submit', (e)=>{
    calcAll();
    if (parseN($tot.value) < 0) {
      e.preventDefault();
      alert('El total no puede ser negativo.');
    }
  });
})();
</script>