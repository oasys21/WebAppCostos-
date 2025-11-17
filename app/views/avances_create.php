<?php
/** @var int    $proyecto_id */
/** @var string $csrf */
$base = rtrim($this->cfg['BASE_URL'] ?? '', '/');
?>
<div class="container py-3">
  <h5 class="mb-3">Registrar avance</h5>

  <form class="row g-3" method="post" action="<?= htmlspecialchars(($base?:'').'/avances/store') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="proyecto_id" value="<?= (int)$proyecto_id ?>">

    <div class="col-md-3">
      <label class="form-label">Familia</label>
      <select class="form-select" id="famSel"></select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Grupo</label>
      <select class="form-select" id="grpSel"></select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Ítem (código - descripción)</label>
      <select class="form-select" id="itemSel" name="codigo" required></select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Fecha avance</label>
      <input class="form-control" type="date" name="fecha_avance" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Cantidad ejecutada</label>
      <input class="form-control" type="number" step="0.0001" name="cantidad_ejecutada" value="0.0000" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Monto ejecutado</label>
      <input class="form-control" type="number" step="0.01" name="monto_ejecutado" value="0.00" required>
    </div>

    <div class="col-12">
      <label class="form-label">Observaciones</label>
      <textarea class="form-control" name="observaciones" rows="3"></textarea>
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary">Guardar</button>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(($base?:'').'/avances/index?proyecto_id='.(int)$proyecto_id) ?>">Volver</a>
    </div>
  </form>
</div>

<script>
(function(){
  const ROOT = <?= json_encode($base ?: '') ?>;
  const famSel = document.getElementById('famSel');
  const grpSel = document.getElementById('grpSel');
  const itemSel= document.getElementById('itemSel');

  async function j(u){ const r=await fetch(u); const t=await r.text(); try{return JSON.parse(t);}catch{const i=t.indexOf('['),j=t.lastIndexOf(']');return JSON.parse(t.slice(i,j+1));} }
  function urls(p){ return [ROOT+p, ROOT+'/index.php'+p, ROOT+'/?controller=presupuestos&action='+p.split('/').pop()].filter((u,i,a)=>a.indexOf(u)===i); }

  async function loadF(){ for(const u of urls('/presupuestos/ajaxfamilias')){ try{ const d=await j(u); famSel.innerHTML='<option value="">Familia...</option>'; d.forEach(x=> famSel.add(new Option(x.t,x.v))); return; }catch{} } }
  async function loadG(f){ for(const u of urls('/presupuestos/ajaxgrupos?f='+encodeURIComponent(f))){ try{ const d=await j(u); grpSel.innerHTML='<option value="">Grupo...</option>'; d.forEach(x=> grpSel.add(new Option(x.t,x.v))); return; }catch{} } }
  async function loadI(f,g){ const qs='?f='+encodeURIComponent(f)+'&g='+encodeURIComponent(g); for(const u of urls('/presupuestos/ajaxitems'+qs)){ try{ const d=await j(u); itemSel.innerHTML=''; d.forEach(x=> itemSel.add(new Option(x.t,x.v))); return; }catch{} } }

  famSel.addEventListener('change', ()=>{ const f=famSel.value; grpSel.innerHTML='<option value="">Grupo...</option>'; itemSel.innerHTML=''; if(f) loadG(f); });
  grpSel.addEventListener('change', ()=>{ const f=famSel.value, g=grpSel.value; itemSel.innerHTML=''; if(f && g) loadI(f,g); });

  loadF();
})();
</script>
