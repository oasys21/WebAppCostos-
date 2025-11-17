<?php
/** @var array  $row */
/** @var string $csrf */
$base = rtrim($this->cfg['BASE_URL'] ?? '', '/');
$f = htmlspecialchars((string)$row['familia'] ?? '');
$g = htmlspecialchars((string)$row['grupo'] ?? '');
$i = htmlspecialchars((string)$row['item'] ?? '');
$proyId = (int)($row['proyecto_id'] ?? 0);
?>
<style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h5 class="m-0">Editar ítem de presupuesto</h5>
    <a class="btn btn-secondary" href="<?= htmlspecialchars(($base?:'').'/presupuestos?proyecto_id='.$proyId) ?>" title="Volver">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
    </a>
  </div>

  <form class="row g-3" method="post" action="<?= htmlspecialchars(($base?:'').'/presupuestos/update/'.(int)$row['id']) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

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
    </div>

    <div class="col-md-3">
      <label class="form-label">Cantidad presupuestada</label>
      <input class="form-control" type="number" step="1" min="0" name="cantidad_presupuestada" value="<?= (int)$row['cantidad_presupuestada'] ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">P.U. presup.</label>
      <input class="form-control" type="number" step="0.01" min="0" name="precio_unitario_presupuestado" value="<?= number_format((float)$row['precio_unitario_presupuestado'],2,'.','') ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Cantidad real</label>
      <input class="form-control" type="number" step="1" min="0" name="cantidad_real" value="<?= (int)$row['cantidad_real'] ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">P.U. real</label>
      <input class="form-control" type="number" step="0.01" min="0" name="precio_unitario_real" value="<?= number_format((float)$row['precio_unitario_real'],2,'.','') ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">Fecha carga</label>
      <input class="form-control" type="date" name="fecha_carga" value="<?= htmlspecialchars((string)($row['fecha_carga'] ?? date('Y-m-d'))) ?>">
    </div>

    <div class="col-12">
      <button class="btn btn-primary" title="Guardar">
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
  const F_INIT = <?= json_encode($f) ?>, G_INIT = <?= json_encode($g) ?>, I_INIT = <?= json_encode($i) ?>;

  async function parse(u){ const r=await fetch(u); const t=await r.text(); try{return JSON.parse(t);}catch{const i=t.indexOf('['),j=t.lastIndexOf(']');return JSON.parse(t.slice(i,j+1));} }
  function urls(p,qs=''){ const q=qs?(qs.startsWith('?')?qs:'?'+qs):''; return [ROOT+p+q, ROOT+'/index.php'+p+q, ROOT+'/?controller=presupuestos&action='+p.split('/').pop()+(q?('&'+q.slice(1)):'')].filter((u,i,a)=>a.indexOf(u)===i); }

  async function loadF(){ for(const u of urls('/presupuestos/ajaxfamilias')){ try{ const d=await parse(u); famSel.innerHTML='<option value="">Familia...</option>'; d.forEach(x=> famSel.add(new Option(x.t,x.v))); famSel.value=F_INIT; if (F_INIT) loadG(F_INIT,true); return; }catch{} } }
  async function loadG(f,pre){ for(const u of urls('/presupuestos/ajaxgrupos','?f='+encodeURIComponent(f))){ try{ const d=await parse(u); grpSel.innerHTML='<option value="">Grupo...</option>'; d.forEach(x=> grpSel.add(new Option(x.t,x.v))); grpSel.value = pre?G_INIT:''; if (pre && F_INIT && G_INIT) loadI(F_INIT,G_INIT,true); return; }catch{} } }
  async function loadI(f,g,pre){ for(const u of urls('/presupuestos/ajaxitems','?f='+encodeURIComponent(f)+'&g='+encodeURIComponent(g))){ try{ const d=await parse(u); itemSel.innerHTML=''; d.forEach(x=> itemSel.add(new Option(x.t,x.v))); if (pre) itemSel.value = I_INIT; return; }catch{} } }

  famSel.addEventListener('change', ()=>{ const f=famSel.value; grpSel.innerHTML='<option value="">Grupo...</option>'; itemSel.innerHTML=''; if(f) loadG(f,false); });
  grpSel.addEventListener('change', ()=>{ const f=famSel.value, g=grpSel.value; itemSel.innerHTML=''; if(f&&g) loadI(f,g,false); });

  loadF();
})();
</script>
