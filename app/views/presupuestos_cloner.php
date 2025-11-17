<?php
// /app/views/presupuestos_cloner.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) { header('Location: /index.php'); exit; }
require_once __DIR__ . '/layout/header.php';

$base = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$proyecto_id = (int)($proyecto_id ?? ($_GET['proyecto_id'] ?? 0));
$proyecto = $proyecto ?? [];
$proy_list = $proy_list ?? [];
$csrf = $csrf ?? ($_SESSION['csrf'] ?? '');
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(../public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h4 class="mb-0">Clonar desde Catálogo</h4>
    <div class="ms-auto d-flex flex-wrap gap-2">
      <form method="get" action="<?= htmlspecialchars($base) ?>/presupuestos" class="d-flex gap-2">
        <select class="form-select form-select-sm" name="proyecto_id" required>
          <option value="">— Seleccione proyecto —</option>
          <?php foreach ($proy_list as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= ((int)$proyecto_id === (int)$p['id'] ? 'selected' : '') ?>>
              <?= htmlspecialchars(($p['codigo_proy'] ?? ($p['codigo'] ?? $p['id'])) . ' — ' . ($p['nombre'] ?? ($p['descripcion'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-secondary">Volver a Presupuesto</button>
      </form>
    </div>
  </div>

  <?php if ((int)$proyecto_id <= 0): ?>
    <div class="alert alert-info">Seleccione un proyecto para clonar a su presupuesto.</div>
    <?php require_once __DIR__ . '/layout/footer.php'; return; endif; ?>

  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div>
        <span class="text-muted">Proyecto:</span>
        <strong><?= htmlspecialchars((string)($proyecto['codigo_proy'] ?? '#'.$proyecto_id)) ?> — <?= htmlspecialchars((string)($proyecto['nombre'] ?? '')) ?></strong>
      </div>
      <div class="ms-auto">
        <div class="input-group input-group-sm" style="max-width:320px;">
          <span class="input-group-text">Cant. def. ítems</span>
          <input type="number" step="0.01" min="0" id="cantidad_default" class="form-control" value="1">
        </div>
      </div>
    </div>
  </div>

  <div id="errBox" class="alert alert-danger d-none"></div>
  <div id="okBox" class="alert alert-success d-none"></div>

  <div class="row g-3">
    <!-- Familias -->
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-header py-2 d-flex align-items-center gap-2">
          <strong>Familias</strong>
          <div class="ms-auto input-group input-group-sm" style="max-width: 220px;">
            <span class="input-group-text">Buscar</span>
            <input type="text" id="qFamilias" class="form-control" placeholder="código o texto">
          </div>
        </div>
        <div class="card-body p-2">
          <div id="listFamilias" class="list-group small" style="max-height: 52vh; overflow:auto;"></div>
        </div>
        <div class="card-footer py-2 d-flex justify-content-between">
          <button id="btnReloadFam" class="btn btn-outline-secondary btn-sm">Recargar</button>
          <button id="btnCloneFam"  class="btn btn-success btn-sm">Clonar familias seleccionadas</button>
        </div>
      </div>
    </div>

    <!-- Grupos -->
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-header py-2 d-flex align-items-center gap-2">
          <strong>Grupos</strong>
          <select id="selFamGrupos" class="form-select form-select-sm ms-auto" style="max-width:200px">
            <option value="">— Elegir familia —</option>
          </select>
        </div>
        <div class="card-body p-2">
          <div id="listGrupos" class="list-group small" style="max-height: 52vh; overflow:auto;"></div>
        </div>
        <div class="card-footer py-2 d-flex justify-content-between">
          <button id="btnReloadGrp" class="btn btn-outline-secondary btn-sm">Recargar</button>
          <button id="btnCloneGrp"  class="btn btn-success btn-sm" disabled>Clonar grupos seleccionados</button>
        </div>
      </div>
    </div>

    <!-- Ítems -->
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
          <strong>Ítems</strong>
          <select id="selFamItems" class="form-select form-select-sm ms-auto" style="max-width:150px">
            <option value="">Familia…</option>
          </select>
          <select id="selGrpItems" class="form-select form-select-sm" style="max-width:150px" disabled>
            <option value="">Grupo…</option>
          </select>
          <div class="input-group input-group-sm" style="max-width:220px;">
            <span class="input-group-text">Buscar</span>
            <input type="text" id="qItems" class="form-control" placeholder="código o texto" disabled>
          </div>
        </div>
        <div class="card-body p-2">
          <div id="listItems" class="list-group small" style="max-height: 52vh; overflow:auto;"></div>
        </div>
        <div class="card-footer py-2 d-flex justify-content-between">
          <button id="btnReloadItems" class="btn btn-outline-secondary btn-sm" disabled>Recargar</button>
          <button id="btnCloneItems"  class="btn btn-success btn-sm" disabled>Clonar ítems seleccionados</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(() => {
  const BASE = "<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>";
  const pid  = "<?= (int)$proyecto_id ?>";
  const csrf = "<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>";
  const $err = document.getElementById('errBox');
  const $ok  = document.getElementById('okBox');

  function ep(a){ return `${BASE}/presupuestos/${a}`; }
  function showErr(msg){ $err.textContent = msg||'Error'; $err.classList.remove('d-none'); $ok.classList.add('d-none'); }
  function showOK(msg){ $ok.textContent  = msg||'Operación realizada.'; $ok.classList.remove('d-none'); $err.classList.add('d-none'); }

  async function getJSON(url, params){
    const qs = params ? ('?'+new URLSearchParams(params)) : '';
    const r = await fetch(url+qs, {credentials:'same-origin'});
    const ct = (r.headers.get('content-type')||'').toLowerCase();
    const body = await r.text();
    if(!r.ok) throw new Error(body||('HTTP '+r.status));
    if(!ct.includes('application/json')) throw new Error('Respuesta no JSON');
    return JSON.parse(body);
  }
  async function postJSON(url, data){
    const form = new URLSearchParams();
    for (const [k, v] of Object.entries(data||{})) {
      if (Array.isArray(v)) {
        // Enviar como arreglo PHP: field[]=a&field[]=b
        for (const x of v) { form.append(k + '[]', x); }
      } else {
        form.append(k, v ?? '');
      }
    }
    const r = await fetch(url, {
      method:'POST', credentials:'same-origin',
      headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},
      body: form.toString()
    });
    const ct = (r.headers.get('content-type')||'').toLowerCase();
    const body = await r.text();
    if(!r.ok) throw new Error(body||('HTTP '+r.status));
    if(!ct.includes('application/json')) throw new Error('Respuesta no JSON');
    return JSON.parse(body);
  }

  // ===== Familias =====
  const $qFamilias = document.getElementById('qFamilias');
  const $listFamilias = document.getElementById('listFamilias');
  const $btnReloadFam = document.getElementById('btnReloadFam');
  const $btnCloneFam  = document.getElementById('btnCloneFam');

  function renderFamilias(rows){
    $listFamilias.innerHTML = rows.map(r => `
      <label class="list-group-item d-flex align-items-center gap-2">
        <input class="form-check-input me-1 famCheck" type="checkbox" value="${r.familia}">
        <span class="badge text-bg-secondary">F${r.familia}</span>
        <span>${r.descripcion || '(sin descripción)'}</span>
      </label>
    `).join('');
    // Popular selectores dependientes
    const $selFamGrupos = document.getElementById('selFamGrupos');
    const $selFamItems  = document.getElementById('selFamItems');
    const opts = ['<option value="">— Elegir familia —</option>'].concat(
      rows.map(r=>`<option value="${r.familia}">F${r.familia} — ${r.descripcion||''}</option>`)
    );
    $selFamGrupos.innerHTML = opts.join('');
    $selFamItems.innerHTML  = ['<option value="">Familia…</option>'].concat(
      rows.map(r=>`<option value="${r.familia}">F${r.familia} — ${r.descripcion||''}</option>`)
    ).join('');
  }
  async function loadFamilias(){
    try{
      const rows = await getJSON(ep('ajaxcatfamilias'), {q:$qFamilias.value});
      renderFamilias(rows||[]);
    }catch(e){ showErr(e.message); }
  }
  $qFamilias.addEventListener('input', ()=>{ loadFamilias(); });
  $btnReloadFam.addEventListener('click', ()=>{ loadFamilias(); });

  $btnCloneFam.addEventListener('click', async ()=>{
    const sel = Array.from(document.querySelectorAll('.famCheck:checked')).map(i=>i.value);
    if(sel.length===0){ showErr('Seleccione al menos una familia.'); return; }
    try{
      const r = await postJSON(ep('do_clone'), {
        csrf: csrf, proyecto_id: pid, scope: 'familias',
        cantidad_default: document.getElementById('cantidad_default').value,
        familias: sel
      });
      showOK(`Familias clonadas: ${r.clonados}`);
    }catch(e){ showErr(e.message); }
  });

  // ===== Grupos =====
  const $selFamGrupos = document.getElementById('selFamGrupos');
  const $listGrupos   = document.getElementById('listGrupos');
  const $btnReloadGrp = document.getElementById('btnReloadGrp');
  const $btnCloneGrp  = document.getElementById('btnCloneGrp');

  async function loadGrupos(){
    const f = $selFamGrupos.value;
    if(!f){ $listGrupos.innerHTML=''; $btnCloneGrp.disabled=true; return; }
    try{
      const rows = await getJSON(ep('ajaxcatgrupos'), {f});
      $listGrupos.innerHTML = (rows||[]).map(g => `
        <label class="list-group-item d-flex align-items-center gap-2">
          <input class="form-check-input me-1 grpCheck" type="checkbox" value="${g.grupo}">
          <span class="badge text-bg-secondary">G${g.grupo}</span>
          <span>${g.descripcion || '(sin descripción)'}</span>
        </label>
      `).join('');
      $btnCloneGrp.disabled=false;
    }catch(e){ showErr(e.message); }
  }
  $selFamGrupos.addEventListener('change', loadGrupos);
  $btnReloadGrp.addEventListener('click', loadGrupos);

  $btnCloneGrp.addEventListener('click', async ()=>{
    const f = $selFamGrupos.value;
    const sel = Array.from(document.querySelectorAll('.grpCheck:checked')).map(i=>i.value);
    if(!f){ showErr('Seleccione una familia.'); return; }
    if(sel.length===0){ showErr('Seleccione al menos un grupo.'); return; }
    try{
      const r = await postJSON(ep('do_clone'), {
        csrf: csrf, proyecto_id: pid, scope: 'grupos',
        cantidad_default: document.getElementById('cantidad_default').value,
        familia: f, grupos: sel
      });
      showOK(`Grupos clonados: ${r.clonados}`);
    }catch(e){ showErr(e.message); }
  });

  // ===== Ítems =====
  const $selFamItems  = document.getElementById('selFamItems');
  const $selGrpItems  = document.getElementById('selGrpItems');
  const $qItems       = document.getElementById('qItems');
  const $listItems    = document.getElementById('listItems');
  const $btnReloadItems = document.getElementById('btnReloadItems');
  const $btnCloneItems  = document.getElementById('btnCloneItems');

  async function loadItems(){
    const f = $selFamItems.value, g = $selGrpItems.value, q = $qItems.value;
    if(!f || !g){ $listItems.innerHTML=''; $btnCloneItems.disabled=true; return; }
    try{
      const rows = await getJSON(ep('ajaxcatitems'), {f,g,q});
      $listItems.innerHTML = (rows||[]).map(it => `
        <label class="list-group-item d-flex align-items-center gap-2">
          <input class="form-check-input me-1 itCheck" type="checkbox" value="${it.codigo}">
          <code class="me-1">${it.codigo}</code>
          <span class="flex-grow-1">${it.descripcion || ''}</span>
          <span class="text-nowrap">$${(+it.valor||0).toLocaleString('es-CL')}</span>
        </label>
      `).join('');
      $btnCloneItems.disabled = !(rows && rows.length);
    }catch(e){ showErr(e.message); }
  }
  $selFamItems.addEventListener('change', async ()=>{
    const f = $selFamItems.value;
    $selGrpItems.innerHTML = '<option value="">Grupo…</option>';
    $selGrpItems.disabled = true;
    $qItems.value=''; $qItems.disabled = true;
    $btnReloadItems.disabled=true; $btnCloneItems.disabled=true; $listItems.innerHTML='';
    if(!f) return;
    try{
      const gs = await getJSON(ep('ajaxcatgrupos'), {f});
      $selGrpItems.innerHTML = ['<option value="">Grupo…</option>'].concat(
        (gs||[]).map(g=>`<option value="${g.grupo}">G${g.grupo} — ${g.descripcion||''}</option>`)
      ).join('');
      $selGrpItems.disabled = false;
    }catch(e){ showErr(e.message); }
  });
  $selGrpItems.addEventListener('change', ()=>{
    $qItems.disabled = !$selGrpItems.value;
    $btnReloadItems.disabled = !$selGrpItems.value;
    loadItems();
  });
  $qItems.addEventListener('input', loadItems);
  $btnReloadItems.addEventListener('click', loadItems);

  $btnCloneItems.addEventListener('click', async ()=>{
    const sel = Array.from(document.querySelectorAll('.itCheck:checked')).map(i=>i.value);
    if(sel.length===0){ showErr('Seleccione al menos un ítem.'); return; }
    try{
      const r = await postJSON(ep('do_clone'), {
        csrf: csrf, proyecto_id: pid, scope: 'items',
        cantidad_default: document.getElementById('cantidad_default').value,
        items: sel
      });
      showOK(`Ítems clonados: ${r.clonados}`);
    }catch(e){ showErr(e.message); }
  });

  // Init
  loadFamilias();
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
