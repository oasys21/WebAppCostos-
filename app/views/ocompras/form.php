<?php
declare(strict_types=1);
/** @var array $oc */
/** @var array $items */
/** @var array $proveedores */
/** @var array $proyectos */

$h    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';

$round0 = fn($n) => (int)round((float)$n, 0, PHP_ROUND_HALF_UP);           // half-up a entero
$clp    = fn($n) => 'CLP$ '.number_format($round0($n), 0, ',', '.');       // con CLP$ (solo totales)
$intfmt = fn($n) => number_format($round0($n), 0, ',', '.');               // sin CLP$ (ítems)
$qty2   = fn($n) => number_format((float)$n, 2, '.', '');                  // cantidades 2 decimales

// Valores por defecto
$oc   = $oc   ?? [];
$items = $items ?? [];
if (!count($items)) {
  $items = [[
    'id'=>null,'linea'=>1,'codigo'=>'','descripcion'=>'','unidad'=>'UND','tipo_costo'=>'MAT',
    'cantidad'=>'1.00','precio_unitario'=>'0','imp_proyecto_id'=>null,'imp_pcosto_id'=>null
  ]];
}
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0"><?= $h($pageTitle ?? 'Orden de Compra') ?></h3>
  <div class="d-flex gap-2">
    
    <?php if (!empty($oc['id'])): ?>
      <a class="btn btn-primary btn-sm" target="_blank" href="<?= $h($base) ?>/ocompras/print/<?= (int)$oc['id'] ?>">Imprimir</a>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= $h($base) ?>/ocompras/<?= !empty($oc['id']) ? ('actualizar/'.(int)$oc['id']) : 'guardar' ?>" autocomplete="off" id="frm-oc">
  <?php if (!isset($_SESSION)) @session_start(); $_SESSION['form_token']=bin2hex(random_bytes(16)); ?>
  <input type="hidden" name="form_token" value="<?= $h($_SESSION['form_token']) ?>">

  <div class="mx-auto row g-2 " style="width:90%">
    <div class="col-sm-2">
      <label class="form-label mb-1">N° OC</label>
      <div class="input-group input-group-sm">
        <input type="text" name="oc_num" id="oc_num" class="form-control form-control-sm"
               value="<?= $h($oc['oc_num'] ?? '') ?>" placeholder="auto">
        <button class="btn btn-secondary" type="button" id="btn-next-oc">Auto</button>
      </div>
    </div>
    <div class="col-sm-3">
      <label class="form-label mb-1">Proveedor</label>
      <select name="proveedor_id" class="form-select form-select-sm" required>
        <option value="">-- Seleccione --</option>
        <?php foreach(($proveedores ?? []) as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((string)($oc['proveedor_id'] ?? '')===(string)$p['id'])?'selected':'' ?>>
            <?= $h($p['nombre'] ?? $p['razon'] ?? '') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-3">
      <label class="form-label mb-1">Proyecto</label>
      <select name="proyecto_id" id="proyecto_id" class="form-select form-select-sm">
        <option value="">-- (opcional) --</option>
        <?php foreach(($proyectos ?? []) as $pr): ?>
          <option value="<?= (int)$pr['id'] ?>" <?= ((string)($oc['proyecto_id'] ?? '')===(string)$pr['id'])?'selected':'' ?>>
            <?= $h($pr['nombre'] ?? '') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-2">
      <label class="form-label mb-1">Fecha</label>
      <input type="date" name="fecha" class="form-control form-control-sm" value="<?= $h($oc['fecha'] ?? date('Y-m-d')) ?>" required>
    </div>
    <div class="col-sm-2">
      <label class="form-label mb-1">Moneda</label>
      <input type="text" name="moneda" class="form-control form-control-sm" value="<?= $h($oc['moneda'] ?? 'CLP') ?>">
    </div>
    <div class="col-sm-2">
      <label class="form-label mb-1">Tipo Cambio</label>
      <input type="text" name="tipo_cambio" class="form-control form-control-sm" value="<?= $h($oc['tipo_cambio'] ?? '1.000000') ?>">
    </div>
    <div class="col-sm-4">
      <label class="form-label mb-1">Condiciones de pago</label>
      <input type="text" name="condiciones_pago" class="form-control form-control-sm" value="<?= $h($oc['condiciones_pago'] ?? '') ?>">
    </div>
    <div class="col-sm-6">
      <label class="form-label mb-1">Observaciones</label>
      <input type="text" name="observaciones" class="form-control form-control-sm" value="<?= $h($oc['observaciones'] ?? '') ?>">
    </div>
  </div>

  <div class="mt-3 table-responsive mx-auto d-block " style="align:center; width:90%;">
    <table class="table table-sm table-bordered align-middle" id="tbl-items">
      <thead class="table-light">
        <tr>
          <th style="width:45px" class="text-center">#</th>
          <th style="width:110px">Código</th>
          <th style="width:300px">Descripción</th>
          <th style="width:90px" class="text-center">Unidad</th>
          <th style="width:95px" class="text-center">Tipo</th>
          <th style="width:140px" class="text-end">Cantidad</th>
          <th style="width:160px" class="text-end">Precio Unitario</th>
          <th style="width:160px" class="text-end">Total Neto Item</th>
          <th style="width:230px">Proyecto / Ítem de costo</th>
          <th style="width:48px" class="text-center">–</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=0; foreach($items as $it): $i++; ?>
          <tr>
            <td style="background-color:transparent" class="text-center align-middle"><span class="row-idx"><?= $i ?></span></td>
            <td style="background-color:transparent"><input name="items[<?= $i-1 ?>][codigo]" class="form-control form-control-sm" value="<?= $h($it['codigo'] ?? '') ?>"></td>
            <td style="background-color:transparent"><input name="items[<?= $i-1 ?>][descripcion]" class="form-control form-control-sm" value="<?= $h($it['descripcion'] ?? '') ?>"></td>
            <td style="background-color:transparent"><input name="items[<?= $i-1 ?>][unidad]" class="form-control form-control-sm text-center" value="<?= $h($it['unidad'] ?? 'UND') ?>"></td>
            <td style="background-color:transparent">
              <select name="items[<?= $i-1 ?>][tipo_costo]" class="form-select form-select-sm">
                <?php
                  $tc = strtoupper((string)($it['tipo_costo'] ?? 'MAT'));
                  foreach(['MAT'=>'MAT','MO'=>'MO','EQ'=>'EQ','SUBC'=>'SUBC'] as $k=>$lbl){
                    $sel = $tc===$k?'selected':'';
                    echo '<option value="'.$h($k).'" '.$sel.'>'.$h($lbl).'</option>';
                  }
                ?>
              </select>
            </td>
            <td style="background-color:transparent">
              <input name="items[<?= $i-1 ?>][cantidad]" class="form-control form-control-sm text-end it-cant" value="<?= $h($qty2($it['cantidad'] ?? 0)) ?>">
            </td>
            <td style="background-color:transparent">
              <input name="items[<?= $i-1 ?>][precio_unitario]" class="form-control form-control-sm text-end it-precio" value="<?= $h($intfmt($it['precio_unitario'] ?? 0)) ?>">
            </td>
            <td style="background-color:white; color:black;"  class="text-end align-middle">
              <span class="it-monto"><strong><?= $h($intfmt(((float)($it['cantidad'] ?? 0))*((float)($it['precio_unitario'] ?? 0)))) ?></strong></span>
            </td>
            <td style="background-color:transparent">
              <div class="d-flex gap-1">
                <select name="items[<?= $i-1 ?>][imp_proyecto_id]" class="form-select form-select-sm it-proy" data-pid="<?= (int)($it['imp_proyecto_id'] ?? ($oc['proyecto_id'] ?? 0)) ?>">
                  <option value="">Proyecto…</option>
                  <?php foreach(($proyectos ?? []) as $pr): ?>
                    <option value="<?= (int)$pr['id'] ?>" <?= ((string)($it['imp_proyecto_id'] ?? ($oc['proyecto_id'] ?? ''))===(string)$pr['id'])?'selected':'' ?>>
                      <?= $h($pr['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="items[<?= $i-1 ?>][imp_pcosto_id]" class="form-select form-select-sm it-pcosto">
                  <option value="">Ítem costo…</option>
                </select>
              </div>
            </td>
            <td style="background-color:transparent" class="text-center align-middle">
              <button type="button" class="btn btn-sm btn-danger btn-del">&times;</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="row mt-3">
    <div class="col-md-4 ms-auto">
      <?php
        $sub = (float)($oc['subtotal']  ?? 0);
        $des = (float)($oc['descuento'] ?? 0);
        $iva = (float)($oc['impuesto']  ?? 0);
        $tot = $sub - $des + $iva;
      ?>
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>Subtotal</div>
            <div class="fw-semibold" id="lbl-subtotal"><?= $clp($sub) ?></div>
            <input type="hidden" name="subtotal" id="subtotal" value="<?= $h($sub) ?>">
          </div>
          <div class="d-flex justify-content-between">
            <div>Descuento</div>
            <div class="fw-semibold" id="lbl-descuento"><?= $clp($des) ?></div>
            <input type="hidden" name="descuento" id="descuento" value="<?= $h($des) ?>">
          </div>
          <div class="d-flex justify-content-between">
            <div>Impuesto</div>
            <div class="fw-semibold" id="lbl-impuesto"><?= $clp($iva) ?></div>
            <input type="hidden" name="impuesto" id="impuesto" value="<?= $h($iva) ?>">
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between fs-5">
            <div><strong>Total</strong></div>
            <div id="lbl-total"><strong><?= $clp($tot) ?></strong></div>
          </div>
        </div>
      </div>
      <div class="text-end mt-3">
		<a class="btn btn-primary" href="<?= $h($base) ?>/ocompras">Volver</a>
		<a class="btn btn-success" target="_blank" href="<?= $h($base) ?>/ocompras/print/<?= (int)$oc['id'] ?>">Imprimir</a>
	    <button type="button" class="btn btn-secondary" id="btn-add">+ Agregar ítem</button>
        <button class="btn btn-success">Guardar</button>
		
      </div>
    </div>
  </div>
</form>

<script>
(function(){
  const $ = (s,ctx=document)=>ctx.querySelector(s);
  const $$= (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));

  // Parseos
  function parseMoneyInt(v){ // entero sin CLP$, acepta miles
    if (v==null) return 0;
    let s = String(v).replace(/[^\d]/g,'');
    if (s==='') return 0;
    return parseInt(s,10);
  }
  function parseQty2(v){
    if (v==null) return 0;
    let s = String(v).replace(',', '.').replace(/[^0-9.]/g,'');
    if (s==='') return 0;
    // solo un punto decimal
    const p = s.indexOf('.');
    if (p>=0){ s = s.slice(0,p+1)+s.slice(p+1).replace(/\./g,''); }
    return parseFloat(s)||0;
  }
  function round0(n){ return Math.round(Number(n)); } // half-up
  function fmtInt(n){ n = round0(n); return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); } // miles con punto
  function clp(n){ return 'CLP$ '+fmtInt(n); }

  // Recalc
  function recalcRow(tr){
    const q = parseQty2($('input.it-cant',tr).value);
    const p = parseMoneyInt($('input.it-precio',tr).value);
    const monto = q * p;
    $('.it-monto', tr).text(fmtInt(monto));
    return monto;
  }
  function recalcAll(){
    let subtotal=0;
    $$('#tbl-items tbody tr').forEach(tr=> subtotal += recalcRow(tr));
    const descuento = 0;
    // IVA 19% sobre (subtotal - descuento)
    const ivaBase = subtotal - descuento;
    const impuesto = Math.round(ivaBase * 0.19); // half-up a entero

    $('#subtotal').value = String(subtotal);
    $('#descuento').value= String(descuento);
    $('#impuesto').value = String(impuesto);

    $('#lbl-subtotal').textContent = clp(subtotal);
    $('#lbl-descuento').textContent= clp(descuento);
    $('#lbl-impuesto').textContent = clp(impuesto);
    $('#lbl-total').innerHTML = '<strong>'+clp(subtotal - descuento + impuesto)+'</strong>';
  }

  // Eventos
  $$('#tbl-items').forEach(tbl=>{
    tbl.addEventListener('input', (ev)=>{
      const el = ev.target;
      if (el.classList.contains('it-precio')){
        // formateo de miles en vivo (sin CLP$)
        let n = parseMoneyInt(el.value);
        el.value = fmtInt(n);
      }
      if (el.classList.contains('it-cant')){
        // normaliza 2 decimales con punto al salir
        // (en input seguimos mostrando lo que escribe)
      }
      recalcAll();
    });
  });

  // Add / Del
  $('#btn-add').addEventListener('click', ()=>{
    const tbody = $('#tbl-items tbody');
    const idx = tbody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="text-center align-middle"><span class="row-idx"></span></td>
      <td><input name="items[${idx}][codigo]" class="form-control form-control-sm" value=""></td>
      <td><input name="items[${idx}][descripcion]" class="form-control form-control-sm" value=""></td>
      <td><input name="items[${idx}][unidad]" class="form-control form-control-sm text-center" value="UND"></td>
      <td>
        <select name="items[${idx}][tipo_costo]" class="form-select form-select-sm">
          <option value="MAT" selected>MAT</option><option value="MO">MO</option><option value="EQ">EQ</option><option value="SUBC">SUBC</option>
        </select>
      </td>
      <td><input name="items[${idx}][cantidad]" class="form-control form-control-sm text-end it-cant" value="1.00"></td>
      <td><input name="items[${idx}][precio_unitario]" class="form-control form-control-sm text-end it-precio" value="0"></td>
      <td class="text-end align-middle"><span class="it-monto">0</span></td>
      <td>
        <div class="d-flex gap-1">
          <select name="items[${idx}][imp_proyecto_id]" class="form-select form-select-sm it-proy">
            <option value="">Proyecto…</option>
            <?php foreach(($proyectos ?? []) as $pr): ?>
              <option value="<?= (int)$pr['id'] ?>"><?= $h($pr['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="items[${idx}][imp_pcosto_id]" class="form-select form-select-sm it-pcosto">
            <option value="">Ítem costo…</option>
          </select>
        </div>
      </td>
      <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger btn-del">&times;</button></td>
    `;
    tbody.appendChild(tr);
    renum();
    recalcAll();
  });

  $('#tbl-items').addEventListener('click', (ev)=>{
    if (ev.target.classList.contains('btn-del')){
      const tr = ev.target.closest('tr');
      tr.parentNode.removeChild(tr);
      renum();
      recalcAll();
    }
  });

  function renum(){
    $$('#tbl-items tbody tr').forEach((tr,i)=> {
      const span = tr.querySelector('.row-idx'); if (span) span.textContent = (i+1);
    });
  }
  renum();

  // Carga ítems de costo por proyecto (combo dependiente)
  async function loadPcostoFor(tr, proyectoId, selectedPcostoId){
    const selPc = tr.querySelector('select.it-pcosto');
    selPc.innerHTML = '<option value="">(cargando…)</option>';
    if (!proyectoId){
      selPc.innerHTML = '<option value="">Ítem costo…</option>';
      return;
    }
    try{
      const res = await fetch(`${<?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>}/ocompras/pcostos?proyecto_id=${encodeURIComponent(proyectoId)}`);
      const data = await res.json();
      selPc.innerHTML = '<option value="">Ítem costo…</option>';
      if (Array.isArray(data)){
        data.forEach(r=>{
          const opt = document.createElement('option');
          opt.value = r.id;
          opt.textContent = `${r.codigo} · ${r.nombre}`;
          if (String(selectedPcostoId||'')===String(r.id)) opt.selected = true;
          selPc.appendChild(opt);
        });
      }
    }catch(e){
      selPc.innerHTML = '<option value="">Ítem costo…</option>';
      console.error('pcostos fetch', e);
    }
  }
  // Inicializa combos de pcosto según proyecto pre-seleccionado
  $$('#tbl-items tbody tr').forEach(tr=>{
    const proySel = tr.querySelector('select.it-proy');
    const pcSel   = tr.querySelector('select.it-pcosto');
    const selectedPc = pcSel.value || null;
    loadPcostoFor(tr, proySel.value, selectedPc);
    proySel.addEventListener('change', ()=> loadPcostoFor(tr, proySel.value, null));
  });

  // Autonumerar OC
  $('#btn-next-oc').addEventListener('click', async ()=>{
    const pid = $('#proyecto_id').value || '';
    try{
      const r = await fetch(`${<?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>}/ocompras/nextnum?proyecto_id=${encodeURIComponent(pid)}`);
      const j = await r.json();
      if (j && j.oc_num) $('#oc_num').value = j.oc_num;
    }catch(e){ console.error(e); }
  });

  // Recalc inicial
  recalcAll();
})();
</script>
