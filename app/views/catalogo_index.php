<?php
// /costos/app/views/catalogo_index.php
declare(strict_types=1);

/** Espera: $csrf, $unidades (array), $pageTitle (opcional), $base (desde layout) */
if (!isset($base)) {
  $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  $base = rtrim(str_replace('\\','/', dirname($sn)), '/');
  if ($base === '' || $base === '.') $base = '';
}
$UNITS   = (isset($unidades) && is_array($unidades) && $unidades) ? $unidades
         : ['m','m2','m3','ud','ML','kg','kW','par','jornada','mes','día','semana','-'];
$MONEDAS = ['CLP','UF','USD','EUR'];
?>
<style>
.table-sm td, .table-sm th { padding-top: .32rem; padding-bottom: .32rem; }
.btn-icon { padding: 0; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
.btn-group .btn-icon + .btn-icon { margin-left: .25rem; }
.btn .svg-ico, .btn-icon .svg-ico { width: 16px; height: 16px; display: inline-block; vertical-align: middle; }
#btnNuevo .svg-ico, #btnSearch .svg-ico { width: 16px; height: 16px; margin-right: .25rem; }
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}

/* Subtítulos en encabezados dobles */
th .muted-cap { font-size: .8rem; color: #6c757d; line-height: 1; }
th .head-line { font-weight: 600; line-height: 1.1; }
</style>

<div class="mx-auto d-block " style="align:center; width:70%;">
  <div class="card-header">
    <div class="d-flex flex-wrap align-items-center gap-2" style="height:100px;">
      <strong class="me-auto">Catálogo de Costos</strong>
      <div class="input-group input-group-md" style="max-width:720px;">
        <label class="input-group-text" for="nivel">Nivel</label>
        <select id="nivel" class="form-select">
          <option value="familias">Familias</option>
          <option value="grupos">Grupos</option>
          <option value="items">Ítems</option>
        </select>
        <input id="q" class="form-control" placeholder="Buscar por descripción o código...">
        <button id="btnSearch" class="btn btn-outline-secondary" title="Buscar">
          <span class="svg-ico" aria-hidden="true">
            <svg viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.867-3.834zM12 6.5a5.5 5.5 0 1 1-11 0a5.5 5.5 0 0 1 11 0"/></svg>
          </span>
        </button>
        <button id="btnNuevo" class="btn btn-primary" title="Nuevo">
          <span class="svg-ico" aria-hidden="true">
            <svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 2a.5.5 0 0 1 .5.5V7h4.5a.5.5 0 0 1 0 1H8.5v4.5a.5.5 0 0 1-1 0V8H3a.5.5 0 0 1 0-1h4.5V2.5A.5.5 0 0 1 8 2"/></svg>
          </span>
          <span class="d-none d-md-inline">Nuevo</span>
        </button>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="badge text-bg-light">Familia: <span id="famSel" class="text-primary">—</span></span>
        <span class="badge text-bg-light">Grupo: <span id="grpSel" class="text-primary">—</span></span>
      </div>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light" id="thead"></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div id="errBox" class="alert alert-danger m-3 d-none"></div>
  </div>
</div>

<!-- Modal Edición -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true" style="align:center; width:70%;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="editForm" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title" id="editTitle">Editar</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" data-bs-theme="dark">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="mode" value="update">
          <input type="hidden" id="nivelForm" value="item">
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <label class="form-label">Código</label>
              <input type="text" class="form-control" id="codigo" name="codigo" maxlength="10" pattern="[0-9]{10}" required>
              <div class="form-text">FFFGGGXXXX</div>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Descripción</label>
              <input type="text" class="form-control" id="descripcion" name="descripcion" maxlength="255" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Unidad</label>
              <select class="form-select" id="unidad" name="unidad" required>
                <?php foreach ($UNITS as $uOpt): ?>
                  <option value="<?= htmlspecialchars($uOpt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($uOpt) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text" id="unidadHelp" style="display:none;">En familia/grupo la unidad se fija en “-”.</div>
            </div>
            <!-- Solo Ítems -->
            <div class="col-12 col-md-4 item-only">
              <label class="form-label">Valor</label>
              <input type="number" step="0.0001" class="form-control" id="valor" name="valor" placeholder="0.00">
            </div>
            <div class="col-12 col-md-4 item-only">
              <label class="form-label">Moneda</label>
              <select class="form-select" id="moneda" name="moneda">
                <?php foreach ($MONEDAS as $m): ?>
                  <option value="<?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <div id="formAlert" class="alert alert-warning py-2 px-3 d-none"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button id="btnDelete" type="button" class="btn btn-outline-danger me-auto d-none">
            <span class="svg-ico me-1" aria-hidden="true">
              <svg viewBox="0 0 16 16" fill="currentColor"><path d="M5.5 5.5A.5.5 0 0 1 6 6v7a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0A.5.5 0 0 1 8.5 6v7a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v7a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1h2.5a1 1 0 0 1 1 1M4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4z"/></svg>
            </span>
            Eliminar
          </button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">
            <span class="svg-ico me-1" aria-hidden="true">
              <svg viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7.25 7.25a.5.5 0 0 1-.708 0L2.146 7.854a.5.5 0 1 1 .708-.708L6.25 10.54l6.896-6.896a.5.5 0 0 1 .708 0"/></svg>
            </span>
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
  <div id="toast" class="toast align-items-center text-bg-dark border-0" role="status" aria-live="polite" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastMsg">Hecho.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const BASE = String(window.BASE_URL || "<?= htmlspecialchars($base ?? '', ENT_QUOTES, 'UTF-8') ?>").replace(/\/+$/,'');
  const csrf = "<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>";
  const $err = document.getElementById('errBox');
  const toastEl = document.getElementById('toast');
  const toast = (window.bootstrap && bootstrap.Toast) ? new bootstrap.Toast(toastEl) : null;
  const showToast = (msg) => { if (toast) { document.getElementById('toastMsg').textContent = msg; toast.show(); } else { console.log(msg); } };
  const showError = (msg) => { $err.classList.remove('d-none'); $err.textContent = msg; };

  const $nivel = document.getElementById('nivel');
  const $q = document.getElementById('q');
  const $thead = document.getElementById('thead');
  const $tbody = document.getElementById('tbody');
  const $famSel = document.getElementById('famSel');
  const $grpSel = document.getElementById('grpSel');

  let nivel = 'familias';
  let famSel = null;
  let grpSel = null;
  let familias = [], grupos = [], items = [];
  let famDesc = '', grpDesc = '';

  // Rutas
  const ep = (action) => `${BASE}/catalogo/${action}`;

  async function getJSON(url, params) {
    const qs = params ? ('?' + new URLSearchParams(params).toString()) : '';
    const r = await fetch(url + qs, { credentials: 'same-origin' });
    const ct = (r.headers.get('content-type') || '').toLowerCase();
    const body = await r.text().catch(()=> '');
    if (!r.ok) throw new Error(`HTTP ${r.status} ${body.substring(0,160)}`);
    if (!ct.includes('application/json')) throw new Error(`Respuesta no JSON (CT=${ct}). ${body.substring(0,160)}`);
    try { return JSON.parse(body); } catch(e) { throw new Error(`JSON inválido. ${body.substring(0,160)}`); }
  }
  async function postJSON(url, data) {
    const form = new URLSearchParams();
    for (const k in data) if (data[k] !== undefined && data[k] !== null) form.append(k, data[k]);
    const r = await fetch(url, {
      method:'POST', credentials:'same-origin',
      headers:{ 'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded' },
      body: form.toString()
    });
    const ct = (r.headers.get('content-type') || '').toLowerCase();
    const body = await r.text().catch(()=> '');
    if (!r.ok) throw new Error(`HTTP ${r.status} ${body.substring(0,160)}`);
    if (!ct.includes('application/json')) throw new Error(`Respuesta no JSON (CT=${ct}). ${body.substring(0,160)}`);
    try { return JSON.parse(body); } catch(e) { throw new Error(`JSON inválido. ${body.substring(0,160)}`); }
  }

  function setNivel(n){
    nivel=n; $nivel.value=n;
    renderHead(); // render inicial
    loadData();   // y luego datos (volverá a re-render con descripciones)
  }
  function setFamilia(f){ famSel=f||null; famDesc=''; $famSel.textContent=famSel?('F'+famSel):'—'; if(nivel!=='familias') loadData(); }
  function setGrupo(g){ grpSel=g||null; grpDesc=''; $grpSel.textContent=grpSel?('G'+grpSel):'—'; if(nivel==='items') loadData(); }

  function esc(s){ const d=document.createElement('div'); d.textContent = (s??''); return d.innerHTML; }

  function renderHead(){
    let html='';
    if(nivel==='familias'){
      html = `
        <tr>
          <th style="width:120px">Familia</th>
          <th>Descripción</th>
          <th style="width:100px">Grupos</th>
          <th style="width:140px" class="text-end">Acc.</th>
        </tr>`;
    } else if(nivel==='grupos'){
      html = `
        <tr>
          <th colspan="4">
            <div class="muted-cap">Familia</div>
            <div class="head-line">F${esc(famSel||'—')} — ${esc(famDesc||'')}</div>
          </th>
        </tr>
        <tr>
          <th style="width:120px">Grupo</th>
          <th>Descripción</th>
          <th style="width:100px">Ítems</th>
          <th style="width:140px" class="text-end">Acc.</th>
        </tr>`;
    } else {
      html = `
        <tr>
          <th colspan="6">
            <div class="row g-0">
              <div class="col-12 col-md-6 pe-2">
                <div class="muted-cap">Familia</div>
                <div class="head-line">F${esc(famSel||'—')} — ${esc(famDesc||'')}</div>
              </div>
              <div class="col-12 col-md-6 ps-2">
                <div class="muted-cap">Grupo</div>
                <div class="head-line">G${esc(grpSel||'—')} — ${esc(grpDesc||'')}</div>
              </div>
            </div>
          </th>
        </tr>
        <tr>
          <th style="width:160px">Código</th>
          <th>Descripción</th>
          <th style="width:80px">Unidad</th>
          <th style="width:100px">Valor</th>
          <th style="width:80px">Mon.</th>
          <th style="width:160px" class="text-end">Acc.</th>
        </tr>`;
    }
    $thead.innerHTML = html;
  }

  async function ensureFamDesc(){
    if (famDesc) return;
    // primero buscar en dataset ya cargado
    const frow = (familias||[]).find(r => String(r.familia) === String(famSel));
    if (frow) { famDesc = frow.descripcion || ''; return; }
    // si no, pedir al backend por código FFF0000000
    try {
      const row = await getJSON(ep('get'), {codigo: `${famSel}0000000`});
      famDesc = row?.descripcion || '';
    } catch(_) { /* sin ruido */ }
  }
  async function ensureGrpDesc(){
    if (grpDesc) return;
    // primero buscar en dataset de grupos ya cargado
    const grow = (grupos||[]).find(r => String(r.grupo) === String(grpSel));
    if (grow) { grpDesc = grow.descripcion || ''; return; }
    // si no, pedir al backend por código FFFGGG0000
    try {
      const row = await getJSON(ep('get'), {codigo: `${famSel}${grpSel}0000`});
      grpDesc = row?.descripcion || '';
    } catch(_) { /* sin ruido */ }
  }

  async function loadData(){
    $err.classList.add('d-none'); $err.textContent='';
    $tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Cargando...</td></tr>`;
    const q = ($q.value||'').toLowerCase().trim();

    try {
      if (nivel==='familias') {
        familias = await getJSON(ep('familias'));
        familias = familias || [];
        renderHead();
        renderFamilias(q);
      } else if (nivel==='grupos') {
        if(!famSel){ $tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Seleccione una familia.</td></tr>`; return; }
        grupos = await getJSON(ep('grupos'), {familia:famSel});
        grupos = grupos || [];
        await ensureFamDesc();
        renderHead();
        renderGrupos(q);
      } else {
        if(!famSel || !grpSel){ $tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Seleccione familia y grupo.</td></tr>`; return; }
        items = await getJSON(ep('items'), {familia:famSel, grupo:grpSel});
        items = items || [];
        await ensureFamDesc();
        await ensureGrpDesc();
        renderHead();
        renderItems(q);
      }
    } catch (e) {
      showError(String(e.message||e));
      console.error(e);
    }
  }

  // SVG inline
  const ICON = {
    plus:   '<svg class="svg-ico" viewBox="0 0 16 16" fill="currentColor"><path d="M8 2a.5.5 0 0 1 .5.5V7h4.5a.5.5 0 0 1 0 1H8.5v4.5a.5.5 0 0 1-1 0V8H3a.5.5 0 0 1 0-1h4.5V2.5A.5.5 0 0 1 8 2"/></svg>',
    check:  '<svg class="svg-ico" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7.25 7.25a.5.5 0 0 1-.708 0L2.146 7.854a.5.5 0 1 1 .708-.708L6.25 10.54l6.896-6.896a.5.5 0 0 1 .708 0"/></svg>',
    gear:   '<svg class="svg-ico" viewBox="0 0 16 16" fill="currentColor"><path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.988 1.988l.17.31c.446.82.023 1.84-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.988 1.988l.31-.17a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.988-1.988l-.17-.31a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.988-1.988l-.31.17a1.464 1.464 0 0 1-2.105-.872zM8 11a3 3 0 1 1 0-6a3 3 0 0 1 0 6"/></svg>',
    trash:  '<svg class="svg-ico" viewBox="0 0 16 16" fill="currentColor"><path d="M5.5 5.5A.5.5 0 0 1 6 6v7a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0A.5.5 0 0 1 8.5 6v7a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v7a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1h2.5a1 1 0 0 1 1 1M4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4z"/></svg>'
  };
  const icon = (name) => ICON[name] || '';
  const colorBy = (key)=> key==='trash'?'danger':(key==='gear'?'secondary':(key==='check'?'success':'primary'));
  function actionBtns(kind){
    const map = {
      'f': [['btn-nuevo-f','plus','Nuevo'],['btn-sel-f','check','Seleccionar'],['btn-edit-f','gear','Editar'],['btn-del-f','trash','Borrar']],
      'g': [['btn-nuevo-g','plus','Nuevo'],['btn-sel-g','check','Seleccionar'],['btn-edit-g','gear','Editar'],['btn-del-g','trash','Borrar']],
      'i': [['btn-nuevo-i','plus','Nuevo'],['btn-sel-i','check','Seleccionar'],['btn-edit-i','gear','Editar'],['btn-del-i','trash','Borrar']],
    }[kind];
    return `<div class="btn-group btn-group-sm">` + map.map(([cls,key,title]) =>
      `<button type="button" class="btn btn-outline-${colorBy(key)} btn-icon ${cls}" title="${title}">${icon(key)}</button>`
    ).join('') + `</div>`;
  }

  function renderFamilias(q){
    const rows = (familias||[]).filter(r=> !q || ('F'+r.familia).toLowerCase().includes(q) || (r.descripcion||'').toLowerCase().includes(q));
    if(!rows.length){ $tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Sin resultados</td></tr>`; return; }
    $tbody.innerHTML = rows.map(r=>`
      <tr data-fam="${r.familia}">
        <td class="fw-semibold">F${r.familia}</td>
        <td>${esc(r.descripcion||'')}</td>
        <td>${Number(r.total||0)}</td>
        <td class="text-end">${actionBtns('f')}</td>
      </tr>`).join('');
  }
  function renderGrupos(q){
    const rows = (grupos||[]).filter(r=> !q || ('G'+r.grupo).toLowerCase().includes(q) || (r.descripcion||'').toLowerCase().includes(q));
    if(!rows.length){ $tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Sin resultados</td></tr>`; return; }
    $tbody.innerHTML = rows.map(r=>`
      <tr data-grp="${r.grupo}">
        <td class="fw-semibold">G${r.grupo}</td>
        <td>${esc(r.descripcion||'')}</td>
        <td>${Number(r.total||0)}</td>
        <td class="text-end">${actionBtns('g')}</td>
      </tr>`).join('');
  }
  function renderItems(q){
    const rows = (items||[]).filter(r=> !q || String(r.codigo).toLowerCase().includes(q) || (r.descripcion||'').toLowerCase().includes(q));
    if(!rows.length){ $tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Sin resultados</td></tr>`; return; }
    $tbody.innerHTML = rows.map(r=>{
      const val = (r.valor!==null && r.valor!==undefined) ? r.valor : '';
      const mon = r.moneda || '';
      return `
      <tr data-cod="${r.codigo}">
        <td class="fw-semibold">${r.codigo}</td>
        <td>${esc(r.descripcion)}</td>
        <td>${esc(r.unidad||'-')}</td>
        <td>${esc(val)}</td>
        <td>${esc(mon)}</td>
        <td class="text-end">${actionBtns('i')}</td>
      </tr>`;
    }).join('');
  }

  function nextFamiliaCode(){
    let max = 0; familias.forEach(f=>{ const n = parseInt(f.familia,10); if(!Number.isNaN(n) && n>max) max=n; });
    return String(max+1).padStart(3,'0');
  }

  function setFormNivel(n){
    document.getElementById('nivelForm').value = n;
    const isItem = (n==='item');
    document.querySelectorAll('.item-only').forEach(el=> el.style.display = isItem ? '' : 'none');
    const $unidad = document.getElementById('unidad');
    const $help = document.getElementById('unidadHelp');
    if(isItem){ $unidad.disabled = false; $help.style.display='none'; }
    else { $unidad.value='-'; $unidad.disabled = true; $help.style.display=''; }
  }
  function openModal(mode, n, data){
    document.getElementById('mode').value = mode;
    setFormNivel(n);
    const alert = document.getElementById('formAlert'); alert.classList.add('d-none'); alert.textContent='';

    const $codigo = document.getElementById('codigo');
    const $descripcion = document.getElementById('descripcion');
    const $unidad = document.getElementById('unidad');
    const $valor = document.getElementById('valor');
    const $moneda = document.getElementById('moneda');
    const $btnDelete = document.getElementById('btnDelete');

    if(mode==='create'){
      document.getElementById('editTitle').textContent = 'Nuevo ' + n;
      $btnDelete.classList.add('d-none');
      $codigo.readOnly = false;
      $codigo.value = data?.codigo || '';
      $descripcion.value = '';
      if(n==='item'){ $unidad.value='ud'; $valor.value=''; $moneda.value='CLP'; } else { $unidad.value='-'; }
    }else{
      document.getElementById('editTitle').textContent = 'Editar ' + n;
      $btnDelete.classList.remove('d-none');
      $codigo.readOnly = true;
      $codigo.value = data?.codigo || '';
      $descripcion.value = data?.descripcion || '';
      $unidad.value = data?.unidad || (n==='item' ? 'ud' : '-');
      if(n==='item'){ if (data && ('valor' in data)) $valor.value = data.valor ?? ''; if (data && data.moneda) $moneda.value = data.moneda; }
    }

    const m = (window.bootstrap && bootstrap.Modal)
      ? bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal'))
      : null;
    if (m) m.show(); else document.getElementById('editModal').style.display = 'block';
  }

  // Barra
  document.getElementById('btnSearch').addEventListener('click', loadData);
  $q.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); loadData(); } });
  $nivel.addEventListener('change', ()=>{
    const nv = $nivel.value;
    if(nv==='grupos' && !famSel){ showToast('Selecciona primero una familia.'); $nivel.value='familias'; return; }
    if(nv==='items'  && (!famSel || !grpSel)){ showToast('Selecciona familia y grupo.'); $nivel.value='familias'; return; }
    setNivel(nv);
  });
  document.getElementById('btnNuevo').addEventListener('click', async ()=>{
    try{
      if(nivel==='familias'){
        const f = nextFamiliaCode();
        openModal('create','familia',{codigo:`${f}0000000`});
      } else if(nivel==='grupos'){
        if(!famSel) return showToast('Selecciona una familia.');
        const res = await getJSON(ep('nextcode'), {type:'grupo', familia:famSel});
        const g = res?.grupo ? String(res.grupo) : '001';
        openModal('create','grupo',{codigo:`${famSel}${g}0000`});
      } else {
        if(!famSel || !grpSel) return showToast('Selecciona familia y grupo.');
        const res = await getJSON(ep('nextcode'), {type:'item', familia:famSel, grupo:grpSel});
        const x = res?.item ? String(res.item) : '0001';
        openModal('create','item',{codigo:`${famSel}${grpSel}${x}`});
      }
    }catch(e){ showError(String(e.message||e)); }
  });

  // Delegación en tbody
  $tbody.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('button'); if(!btn) return;
    const tr = ev.target.closest('tr'); if(!tr) return;

    try {
      if (btn.classList.contains('btn-sel-f')) {
        const f = String(tr.dataset.fam||''); setFamilia(f); setNivel('grupos');
      } else if (btn.classList.contains('btn-edit-f')) {
        const f = String(tr.dataset.fam||''); const row = await getJSON(ep('get'), {codigo:`${f}0000000`}); openModal('update','familia',row);
      } else if (btn.classList.contains('btn-del-f')) {
        const f = String(tr.dataset.fam||''); const rows = await getJSON(ep('grupos'), {familia:f});
        if ((rows||[]).length>0) return showToast('No se puede eliminar: tiene grupos asociados.');
        if (!confirm(`Eliminar familia F${f}?`)) return;
        const r = await postJSON(ep('delete'), {codigo:`${f}0000000`, csrf});
        if (r?.ok){ showToast('Familia eliminada'); setFamilia(null); setNivel('familias'); loadData(); } else showToast(r?.error||'Error al eliminar');
      } else if (btn.classList.contains('btn-nuevo-f')) {
        const f = nextFamiliaCode(); openModal('create','familia',{codigo:`${f}0000000`});
      }
      else if (btn.classList.contains('btn-sel-g')) {
        const g = String(tr.dataset.grp||''); setGrupo(g); setNivel('items');
      } else if (btn.classList.contains('btn-edit-g')) {
        const g = String(tr.dataset.grp||''); const row = await getJSON(ep('get'), {codigo:`${famSel}${g}0000`}); openModal('update','grupo',row);
      } else if (btn.classList.contains('btn-del-g')) {
        const g = String(tr.dataset.grp||''); const rows = await getJSON(ep('items'), {familia:famSel, grupo:g});
        if ((rows||[]).length>0) return showToast('No se puede eliminar: tiene ítems asociados.');
        if (!confirm(`Eliminar grupo G${g}?`)) return;
        const r = await postJSON(ep('delete'), {codigo:`${famSel}${g}0000`, csrf});
        if (r?.ok){ showToast('Grupo eliminado'); setGrupo(null); loadData(); } else showToast(r?.error||'Error al eliminar');
      } else if (btn.classList.contains('btn-nuevo-g')) {
        if(!famSel) return showToast('Selecciona una familia.');
        const res = await getJSON(ep('nextcode'), {type:'grupo', familia:famSel});
        const g = res?.grupo ? String(res.grupo) : '001';
        openModal('create','grupo',{codigo:`${famSel}${g}0000`});
      }
      else if (btn.classList.contains('btn-sel-i') || btn.classList.contains('btn-edit-i')) {
        const cod = String(tr.dataset.cod||''); const row = await getJSON(ep('get'), {codigo:cod}); openModal('update','item',row);
      } else if (btn.classList.contains('btn-del-i')) {
        const cod = String(tr.dataset.cod||''); if (!confirm(`Eliminar ítem ${cod}?`)) return;
        const r = await postJSON(ep('delete'), {codigo:cod, csrf});
        if (r?.ok){ showToast('Ítem eliminado'); loadData(); } else showToast(r?.error||'Error al eliminar');
      } else if (btn.classList.contains('btn-nuevo-i')) {
        if(!famSel || !grpSel) return showToast('Selecciona familia y grupo.');
        const res = await getJSON(ep('nextcode'), {type:'item', familia:famSel, grupo:grpSel});
        const x = res?.item ? String(res.item) : '0001';
        openModal('create','item',{codigo:`${famSel}${grpSel}${x}`});
      }
    } catch(e){ showError(String(e.message||e)); }
  });

  // Guardar / Eliminar en modal
  document.getElementById('editForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const alert = document.getElementById('formAlert'); alert.classList.add('d-none'); alert.textContent='';

    const mode  = document.getElementById('mode').value;
    const nform = document.getElementById('nivelForm').value;
    const codigo = String(document.getElementById('codigo').value||'').replace(/\D/g,'');
    const descripcion = String(document.getElementById('descripcion').value||'').trim();
    let unidad = String(document.getElementById('unidad').value||'-').trim();
    let valor  = String(document.getElementById('valor').value||'').trim();
    let moneda = String(document.getElementById('moneda').value||'').trim();

    if(!/^\d{10}$/.test(codigo)) { alert.classList.remove('d-none'); alert.textContent='El código debe tener 10 dígitos.'; return; }
    if(descripcion.length < 1)   { alert.classList.remove('d-none'); alert.textContent='La descripción es obligatoria.'; return; }
    if(nform!=='item'){ unidad='-'; valor=''; moneda=''; }

    const payload = { codigo, descripcion, unidad, csrf };
    if(nform==='item'){ if(valor!=='') payload.valor=valor; if(moneda!=='') payload.moneda=moneda; }

    try{
      const call = (mode==='create') ? 'create' : 'update';
      const r = await postJSON(ep(call), payload);
      if (r?.ok){
        showToast('Guardado correctamente');
        const m = (window.bootstrap && bootstrap.Modal) ? bootstrap.Modal.getInstance(document.getElementById('editModal')) : null;
        if (m) m.hide(); else document.getElementById('editModal').style.display='none';
        const f = codigo.substring(0,3), g=codigo.substring(3,6), it=codigo.substring(6,10);
        if(it==='0000'){ if(g==='000'){ setFamilia(f); setNivel('familias'); } else { setFamilia(f); setNivel('grupos'); } }
        else { setFamilia(f); setGrupo(g); setNivel('items'); }
        loadData();
      } else {
        alert.classList.remove('d-none');
        alert.textContent = r?.error || 'Error al guardar.';
      }
    }catch(e){
      alert.classList.remove('d-none');
      alert.textContent = String(e.message||e);
    }
  });

  document.getElementById('btnDelete').addEventListener('click', async ()=>{
    const codigo = String(document.getElementById('codigo').value||'').replace(/\D/g,'');
    if(!confirm(`Eliminar ${codigo}?`)) return;
    try{
      const r = await postJSON(ep('delete'), {codigo, csrf});
      if (r?.ok){
        showToast('Eliminado');
        const m = (window.bootstrap && bootstrap.Modal) ? bootstrap.Modal.getInstance(document.getElementById('editModal')) : null;
        if (m) m.hide(); else document.getElementById('editModal').style.display='none';
        const f = codigo.substring(0,3), g=codigo.substring(3,6), it=codigo.substring(6,10);
        if(it==='0000'){ if(g==='000'){ setFamilia(null); setNivel('familias'); } else { setGrupo(null); setNivel('grupos'); } }
        loadData();
      } else {
        showToast(r?.error || 'Error al eliminar');
      }
    }catch(e){ showToast(String(e.message||e)); }
  });

  // Init
  renderHead();
  setNivel('familias');
  loadData();
});
</script>
