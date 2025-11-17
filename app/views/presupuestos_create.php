<?php
/** @var int    $proyecto_id */
/** @var string $csrf */
$base = rtrim($this->cfg['BASE_URL'] ?? '', '/');
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h5 class="m-0">Nuevo ítem de presupuesto</h5>
    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(($base?:'').'/presupuestos?proyecto_id='.(int)$proyecto_id) ?>" title="Volver">
      <!-- backward icon -->
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
    </a>
  </div>

  <form class="row g-3" method="post" action="<?= htmlspecialchars(($base?:'').'/presupuestos/store') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="proyecto_id" value="<?= (int)$proyecto_id ?>">

    <div class="col-md-3">
      <label class="form-label">Familia</label>
      <select class="form-select" id="famSel" name="familia" required></select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Grupo</label>
      <select class="form-select" id="grpSel" name="grupo" required></select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Item</label>
      <select class="form-select" id="itemSel" name="item" required></select>
      <div class="form-text">Se mostrará como “#### - descripción”.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Cantidad presupuestada</label>
      <input class="form-control" type="number" step="1" min="0" name="cantidad_presupuestada" value="1" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">P.U. presupuestado</label>
      <div class="input-group">
        <input id="pu_pres" class="form-control" type="number" step="0.01" min="0" name="precio_unitario_presupuestado" value="0.00" required>
        <button id="btnGetPrecio" class="btn btn-outline-secondary" type="button" title="Traer precio">
          <!-- refresh icon -->
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.13-3.36L23 10"/><path d="M20.49 15A9 9 0 0 1 6.36 18.36L1 14"/></svg>
        </button>
      </div>
      <div class="form-text">Se toma de <em>costos_precios</em> o del <em>catálogo</em>. Editable.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Cantidad real</label>
      <input class="form-control" type="number" step="1" min="0" name="cantidad_real" value="0">
    </div>
    <div class="col-md-3">
      <label class="form-label">P.U. real</label>
      <input class="form-control" type="number" step="0.01" min="0" name="precio_unitario_real" value="0.00">
    </div>

    <div class="col-md-3">
      <label class="form-label">Fecha carga</label>
      <input class="form-control" type="date" name="fecha_carga" value="<?= date('Y-m-d') ?>">
    </div>

    <div class="col-12">
      <button class="btn btn-primary" title="Guardar">
        <!-- check icon -->
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
      </button>
    </div>
  </form>
</div>

<script>
(function(){
  const ROOT = <?= json_encode($base ?: '') ?>;
  const famSel = document.getElementById('famSel');
  const grpSel = document.getElementById('grpSel');
  const itemSel= document.getElementById('itemSel');
  const puInp  = document.getElementById('pu_pres');
  const btnGet = document.getElementById('btnGetPrecio');

  async function parse(u){ const r=await fetch(u); const t=await r.text(); try{return JSON.parse(t);}catch{const i=t.indexOf('['),j=t.lastIndexOf(']');return JSON.parse(t.slice(i,j+1));} }
  function urls(p,qs=''){ const q=qs?(qs.startsWith('?')?qs:'?'+qs):''; return [ROOT+p+q, ROOT+'/index.php'+p+q, ROOT+'/?controller=presupuestos&action='+p.split('/').pop()+(q?('&'+q.slice(1)):'')].filter((u,i,a)=>a.indexOf(u)===i); }

  async function loadF(){ for(const u of urls('/presupuestos/ajaxfamilias')){ try{ const d=await parse(u); famSel.innerHTML='<option value="">Familia...</option>'; d.forEach(x=> famSel.add(new Option(x.t,x.v))); return; }catch{} } }
  async function loadG(f){ for(const u of urls('/presupuestos/ajaxgrupos','?f='+encodeURIComponent(f))){ try{ const d=await parse(u); grpSel.innerHTML='<option value="">Grupo...</option>'; d.forEach(x=> grpSel.add(new Option(x.t,x.v))); return; }catch{} } }
  async function loadI(f,g){
    for(const u of urls('/presupuestos/ajaxitems','?f='+encodeURIComponent(f)+'&g='+encodeURIComponent(g))){
      try{ const d=await parse(u); itemSel.innerHTML=''; d.forEach(x=> itemSel.add(new Option(x.t,x.v))); return; }catch{} }
  }
  async function loadP(f,g,i){ for(const u of urls('/presupuestos/ajaxprecio',`?f=${encodeURIComponent(f)}&g=${encodeURIComponent(g)}&i=${encodeURIComponent(i)}`)){ try{ const d=await parse(u); puInp.value = Number(d.p ?? 0).toFixed(2); return; }catch{} } }

  famSel.addEventListener('change', ()=>{ const f=famSel.value; grpSel.innerHTML='<option value="">Grupo...</option>'; itemSel.innerHTML=''; if(f) loadG(f); });
  grpSel.addEventListener('change', ()=>{ const f=famSel.value, g=grpSel.value; itemSel.innerHTML=''; if(f&&g) loadI(f,g); });
  itemSel.addEventListener('change', ()=>{ const f=famSel.value, g=grpSel.value, i=itemSel.value; if(f&&g&&i) loadP(f,g,i); });
  btnGet.addEventListener('click', ()=>{ const f=famSel.value, g=grpSel.value, i=itemSel.value; if(f&&g&&i) loadP(f,g,i); });

  loadF();
})();
</script>
