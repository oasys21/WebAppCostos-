<?php
// /app/views/presupuestos_index.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) { header('Location: /index.php'); exit; }
require_once __DIR__ . '/layout/header.php';

$base = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$rows = $rows ?? [];
$proyecto_id = (int)($proyecto_id ?? ($_GET['proyecto_id'] ?? 0));
$proyecto = $proyecto ?? [];
$proy_list = $proy_list ?? [];
$csrf = $csrf ?? ($_SESSION['csrf'] ?? '');

// Etiqueta de proyecto (se muestra SOLO en nivel familias)
$proy_label = trim(
  (($proyecto['codigo_proy'] ?? ($proyecto['codigo'] ?? ('#'.$proyecto_id))) . ' — ' . ($proyecto['nombre'] ?? ''))
);
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="mx-auto d-block container-fluid py-3" style="align:center; width:70%;">
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h4 class="mb-0">Presupuestos</h4>
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
        <button class="btn btn-sm btn-primary">Abrir</button>
      </form>
      <?php if ((int)$proyecto_id > 0): ?>
      <a class="btn btn-sm btn-success" href="<?= $base ?>/presupuestos/cloner?proyecto_id=<?= (int)$proyecto_id ?>">
        Clonar desde Catálogo
      </a>
	    <a class="btn btn-sm btn-dark" target="_blank"
     href="<?= $base ?>/presupuestos/imprimir?proyecto_id=<?= (int)$proyecto_id ?>">
    Imprimir
  </a>
	  
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($_GET['e'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string)$_GET['e']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['ok'])): ?>
    <div class="alert alert-success">Operación realizada correctamente.</div>
  <?php endif; ?>

  <?php if ((int)$proyecto_id <= 0): ?>
    <div class="alert alert-info">Seleccione un proyecto para ver su presupuesto.</div>
    <?php require_once __DIR__ . '/layout/footer.php'; return; endif; ?>

  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <!-- Scope dinámico (Proyecto/Familia/Grupo) -->
      <div id="scopeBox" class="flex-grow-1 text-truncate" style="min-width:220px;"></div>

      <!-- Totales dinámicos -->
      <div id="totalsBox" class="d-flex flex-wrap gap-2 ms-auto"></div>

      <!-- Navegación -->
      <div class="d-flex gap-2">
        <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" id="proyecto_id" value="<?= (int)$proyecto_id ?>">
        <button id="navFamilias" class="btn btn-outline-secondary btn-sm">Familias</button>
        <button id="navGrupos"   class="btn btn-outline-secondary btn-sm" disabled>Grupos</button>
        <button id="navItems"    class="btn btn-outline-secondary btn-sm" disabled>Ítems</button>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12">
      <div id="errBox" class="alert alert-danger d-none"></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle table-hover">
          <thead id="thead"></thead>
          <tbody id="tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal edición ítem -->
<div class="modal fade" id="editModal" data-bs-theme="dark" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editForm">
        <div class="modal-header">
          <h5 class="modal-title">Editar ítem</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body ">
          <div id="mErr" class="alert alert-danger d-none"></div>
          <input type="hidden" name="id" id="mId">
          <div class="mb-2">
            <label class="form-label">Código</label>
            <input type="text" class="form-control form-control-sm" id="mCodigo" disabled>
          </div>
          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control form-control-sm" id="mDesc" rows="2" disabled></textarea>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Cantidad presup.</label>
              <input type="number" min="0" class="form-control form-control-sm" name="cantidad_presupuestada" id="mCant" required>
            </div>
            <div class="col-6">
              <label class="form-label">Precio unit. presup.</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" class="form-control" name="precio_unitario_presupuestado" id="mPrecio" required>
                <button class="btn btn-outline-secondary" type="button" id="btnPrecioVenta" title="Cargar último Precio Venta">PV</button>
                <button class="btn btn-outline-secondary" type="button" id="btnPrecioDirecto" title="Cargar último Costo Directo">CD</button>
              </div>
              <div class="form-text">Carga último precio de <code>costos_precios</code> o valor del catálogo si no hay.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-danger" id="btnDelItem" title="Borrar ítem">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" aria-hidden="true" viewBox="0 0 16 16"><path d="M2 4h12v1H2z"/><path d="M6 2h4v2H6z"/><path d="M3 5h10l-1 9H4z"/></svg>
            Eliminar
          </button>
		  <div>
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  const BASE = "<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>";
  const pid  = document.getElementById('proyecto_id').value;
  const csrf = document.getElementById('csrf').value;
  const $err = document.getElementById('errBox');
  const $thead = document.getElementById('thead');
  const $tbody = document.getElementById('tbody');

  const $navF = document.getElementById('navFamilias');
  const $navG = document.getElementById('navGrupos');
  const $navI = document.getElementById('navItems');

  const $scopeBox  = document.getElementById('scopeBox');
  const $totalsBox = document.getElementById('totalsBox');

  const PROY_LABEL = "<?= htmlspecialchars($proy_label, ENT_QUOTES, 'UTF-8') ?>";

  // SVG inline
  const ICONS = {
    ticket: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" aria-hidden="true" viewBox="0 0 16 16"><rect x="1" y="3" width="14" height="10" rx="2" ry="2"></rect><circle cx="5" cy="8" r="0.7"></circle><circle cx="8" cy="8" r="0.7"></circle><circle cx="11" cy="8" r="0.7"></circle></svg>',
    gear:   '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" aria-hidden="true" viewBox="0 0 16 16"><circle cx="8" cy="8" r="2.5"></circle><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.2 3.2l1.4 1.4M11.4 11.4l1.4 1.4M12.8 3.2L11.4 4.6M4.6 11.4L3.2 12.8" stroke="currentColor" stroke-width="1" fill="none"/></svg>',
    trash:  '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" aria-hidden="true" viewBox="0 0 16 16"><path d="M2 4h12v1H2z"/><path d="M6 2h4v2H6z"/><path d="M3 5h10l-1 9H4z"/></svg>'
  };

  let nivel='familias', famSel=null, grpSel=null;
  let famDesc='', grpDesc='';
  let lastGrupos = new Map(); // grp => { ... , descripcion }

  function ep(a){ return `${BASE}/presupuestos/${a}`; }
  function showErr(msg){ $err.textContent = (msg||'Error'); $err.classList.remove('d-none'); }
  function hideErr(){ $err.classList.add('d-none'); $err.textContent=''; }

  function setScopeText(txt){ $scopeBox.textContent = txt || ''; }
  function setTotals({grp=null, fam=null, total=null}={}) {
    const pills = [];
    if (grp !== null)  pills.push(`<span class="badge rounded-pill text-bg-light border">Subtotal grupo: <span class="fw-semibold">$${grp.toLocaleString('es-CL')}</span></span>`);
    if (fam !== null)  pills.push(`<span class="badge rounded-pill text-bg-light border">Total familia: <span class="fw-semibold">$${fam.toLocaleString('es-CL')}</span></span>`);
    if (total !== null) pills.push(`<span class="badge rounded-pill text-bg-primary">Total proyecto: <span class="fw-semibold">$${total.toLocaleString('es-CL')}</span></span>`);
    $totalsBox.innerHTML = pills.join('');
  }

  // Dataset base (incluye cabeceras para hallar descripciones)
  const rows = <?php
    $out=[];
    foreach ($rows as $r) {
      $out[]=[
        'id'=>(int)($r['id'] ?? 0),
        'codigo'=>($r['codigo'] ?? ''),
        'descripcion'=>($r['descripcion'] ?? ''),
        'cant'=>(float)($r['cantidad_presupuestada'] ?? 0),
        'precio'=>(float)($r['precio_unitario_presupuestado'] ?? 0),
        'subp'=>(float)($r['subtotal_pres'] ?? 0),
        'subr'=>(float)($r['subtotal_real'] ?? 0),
      ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>;

  function norm(s){ return String(s||'').replace(/[-\s]/g,'').toUpperCase(); }
  function famOf(code){ return norm(code).substring(0,3); }
  function grpOf(code){ return norm(code).substring(3,6); }
  function itemOf(code){ return norm(code).substring(6,10); }

  function getFamDesc(f){
    const F = String(f||'').toUpperCase();
    for (const r of rows){
      if (famOf(r.codigo)===F && itemOf(r.codigo)==='0000' && r.descripcion){
        return r.descripcion;
      }
    }
    return '';
  }

  function sumFamilia(f){
    let s=0, F=String(f||'').toUpperCase();
    for (const r of rows){
      if (famOf(r.codigo)===F && itemOf(r.codigo)!=='0000'){
        s += (+r.subp||0);
      }
    }
    return s;
  }

  function sumGrupo(f,g){
    let s=0, F=String(f||'').toUpperCase(), G=String(g||'').toUpperCase();
    for (const r of rows){
      if (famOf(r.codigo)===F && grpOf(r.codigo)===G && itemOf(r.codigo)!=='0000'){
        s += (+r.subp||0);
      }
    }
    return s;
  }

  function sumProyecto(){
    let s=0;
    for (const r of rows){
      if (itemOf(r.codigo)!=='0000'){ s += (+r.subp||0); }
    }
    return s;
  }

  function renderHead(){
    let html='';
    if(nivel==='familias'){
      html = `<tr><th style="width:140px">Familia</th><th>Descripción</th><th class="text-end" style="width:140px">Presup.</th><th class="text-end" style="width:140px">Real</th><th class="text-end" style="width:160px">Acciones</th></tr>`;
    }else if(nivel==='grupos'){
      html = `<tr><th style="width:140px">Grupo</th><th>Descripción</th><th class="text-end" style="width:140px">Presup.</th><th class="text-end" style="width:140px">Real</th><th class="text-end" style="width:160px">Acciones</th></tr>`;
    }else{
      /* Aquí se agrega la columna Subtotal Real */
      html = `<tr>
        <th style="width:160px">Código</th>
        <th>Descripción</th>
        <th class="text-end" style="width:120px">Cant. Pres.</th>
        <th class="text-end" style="width:160px">P.Unit Pres.</th>
        <th class="text-end" style="width:160px">Subtotal</th>
        <th class="text-end" style="width:160px">Subtotal Real</th>
        <th class="text-end" style="width:200px">Acciones</th>
      </tr>`;
    }
    $thead.innerHTML = html;
  }

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
    const form = new URLSearchParams(Object.entries(data||{}));
    const r = await fetch(url, {
      method:'POST', credentials:'same-origin',
      headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},
      body: form.toString()
    });
    const ct = (r.headers.get('content-type')||'').toLowerCase();
    theBody = await r.text();
    if(!r.ok) throw new Error(theBody||('HTTP '+r.status));
    if(!ct.includes('application/json')) throw new Error('Respuesta no JSON');
    return JSON.parse(theBody);
  }

  function renderFamilias(){
    hideErr(); renderHead();
    // Mostrar solo el proyecto en el scope
    setScopeText(PROY_LABEL || '');
    // Total proyecto (sumatoria de todas las familias a partir de ítems)
    const totalProy = sumProyecto();
    setTotals({ total: totalProy });

    const map = new Map(); // fam => {p,r,desc}
    rows.forEach(r=>{
      const f=famOf(r.codigo), i=itemOf(r.codigo);
      if(!map.has(f)) map.set(f,{p:0,r:0,desc:''});
      const o=map.get(f);
      if(i!=='0000'){ // sumar solo ítems
        o.p += (+r.subp||0);
        o.r += (+r.subr||0);
      }
      if(i==='0000' && r.descripcion && !o.desc) o.desc = r.descripcion;
    });
    const arr = Array.from(map.entries()).sort((a,b)=>a[0].localeCompare(b[0]));
    if(arr.length===0){ $tbody.innerHTML = `<tr><td colspan="5" class="text-muted">Sin datos de presupuesto.</td></tr>`; return; }
    $tbody.innerHTML = arr.map(([f,o])=>{
      const code = `${f}0000000`;
      return `<tr>
        <td><span class="badge text-bg-secondary">F${f}</span></td>
        <td>${(o.desc||'(sin descripción)')}</td>
        <td class="text-end">$${(o.p||0).toLocaleString('es-CL')}</td>
        <td class="text-end">$${(o.r||0).toLocaleString('es-CL')}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary" data-act="ver-grupos" data-f="${f}" title="Seleccionar">
            ${ICONS.ticket}
          </button>
          <button class="btn btn-sm btn-outline-danger"  data-act="del-scope"  data-code="${code}" title="Borrar familia">
            ${ICONS.trash}
          </button>
        </td>
      </tr>`;
    }).join('');
  }

  async function renderGrupos(){
    hideErr(); renderHead();
    $navG.disabled=false; $navI.disabled=true;

    try{
      const data = await getJSON(ep('ajaxgrupos'), {proyecto_id:pid, f:famSel});
      lastGrupos.clear();
      (data||[]).forEach(g => { const k=(g.grupo||g.grp||'').toString(); lastGrupos.set(k, g); });

      // Descripción de familia y totales (solo ítems)
      famDesc = getFamDesc(famSel) || '';
      setScopeText(famDesc || '');
      setTotals({ fam: sumFamilia(famSel) });

      if(!data || data.length===0){
        $tbody.innerHTML = `<tr><td colspan="5" class="text-muted">No hay grupos para la familia seleccionada.</td></tr>`;
        return;
      }
      $tbody.innerHTML = data.map(g=>{
        const code = String(g.codigo||''); // FFFGGG0000
        const grp  = g.grupo || g.grp || '';
        return `<tr>
          <td><span class="badge text-bg-secondary">G${grp}</span></td>
          <td>${g.descripcion||'(sin descripción)'}</td>
          <td class="text-end">$${(+g.subtotal_pres||0).toLocaleString('es-CL')}</td>
          <td class="text-end">$${(+g.subtotal_real||0).toLocaleString('es-CL')}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" data-act="ver-items" data-f="${g.familia}" data-g="${grp}" title="Seleccionar">
              ${ICONS.ticket}
            </button>
            <button class="btn btn-sm btn-outline-danger"  data-act="del-scope" data-code="${code}" title="Borrar grupo">
              ${ICONS.trash}
            </button>
          </td>
        </tr>`;
      }).join('');
    }catch(e){ showErr(e.message); }
  }

  async function renderItems(){
    hideErr(); renderHead();
    grpDesc = (lastGrupos.get(String(grpSel))?.descripcion) || grpDesc || '';
    setScopeText(grpDesc || '');

    const grpTotal = sumGrupo(famSel, grpSel);
    const famTotal = sumFamilia(famSel);
    setTotals({ grp: grpTotal, fam: famTotal });

    try{
      const data = await getJSON(ep('ajaxitems'), {proyecto_id:pid, f:famSel, g:grpSel});
      if(!data || data.length===0){
        /* Ajuste de colspan por nueva columna “Subtotal Real” */
        $tbody.innerHTML = `<tr><td colspan="7" class="text-muted">No hay ítems en el grupo seleccionado.</td></tr>`;
        return;
      }
      $tbody.innerHTML = data.map(it=>{
        const subP = (+it.subtotal_pres||0);
        const subR = (+it.subtotal_real || ((+it.cantidad_real||0) * (+it.precio_unitario_real||0)) || 0);
        return `<tr>
          <td><code>${it.codigo}</code></td>
          <td>${it.descripcion||'(sin descripción)'}</td>
          <td class="text-end">${(+it.cantidad_presupuestada||0).toLocaleString('es-CL')}</td>
          <td class="text-end">$${(+it.precio_unitario_presupuestado||0).toLocaleString('es-CL')}</td>
          <td class="text-end fw-semibold">$${subP.toLocaleString('es-CL')}</td>
          <td class="text-end fw-semibold">$${subR.toLocaleString('es-CL')}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" data-act="edit-item" data-id="${it.id}" title="Editar">
              ${ICONS.gear}
            </button>
            <button class="btn btn-sm btn-outline-danger"    data-act="del-scope" data-code="${it.codigo}" title="Borrar ítem">
              ${ICONS.trash}
            </button>
          </td>
        </tr>`;
      }).join('');
    }catch(e){ showErr(e.message); }
  }

  // Navegación
  $navF.addEventListener('click', ()=>{
    nivel='familias'; famSel=null; grpSel=null;
    famDesc=''; grpDesc=''; lastGrupos.clear();
    $navG.disabled=true; $navI.disabled=true;
    renderFamilias();
  });
  $navG.addEventListener('click', ()=>{
    if(!famSel) return;
    nivel='grupos'; grpSel=null; grpDesc=''; lastGrupos.clear();
    $navG.disabled=false; $navI.disabled=true;
    renderGrupos();
  });
  $navI.addEventListener('click', ()=>{
    if(!famSel||!grpSel) return;
    nivel='items';
    renderItems();
  });

  $tbody.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('button'); if(!btn) return;
    const act = btn.getAttribute('data-act');
    if(act==='ver-grupos'){
      famSel = btn.getAttribute('data-f');
      nivel='grupos'; grpSel=null; grpDesc=''; lastGrupos.clear();
      $navG.disabled=false; $navI.disabled=true;
      renderGrupos();
    } else if(act==='ver-items'){
      famSel = btn.getAttribute('data-f'); grpSel = btn.getAttribute('data-g');
      nivel='items';
      $navI.disabled=false;
      renderItems();
    } else if(act==='del-scope'){
      const code = btn.getAttribute('data-code');
      if(!confirm('¿Eliminar alcance seleccionado?')) return;
      try{
        await postJSON(ep('ajaxdelscope'), {proyecto_id:pid, codigo:code, csrf:csrf});
        if(nivel==='familias'){ renderFamilias(); }
        else if(nivel==='grupos'){ renderGrupos(); }
        else { renderItems(); }
      }catch(e){ showErr(e.message); }
    } else if(act==='edit-item'){
      const id = btn.getAttribute('data-id');
      try{
        const r = await getJSON(ep('ajaxgetitem'), {id:id});
        openModal(r);
      }catch(e){ showErr(e.message); }
    }
  });

  // ===== Modal edición (con fallback si no hay Bootstrap JS) =====
  const mEl  = document.getElementById('editModal');
  const mErr = document.getElementById('mErr');
  const mId  = document.getElementById('mId');
  const mCodigo = document.getElementById('mCodigo');
  const mDesc   = document.getElementById('mDesc');
  const mCant   = document.getElementById('mCant');
  const mPrecio = document.getElementById('mPrecio');

  const hasBs = !!(window.bootstrap && bootstrap.Modal);
  let bsModal = hasBs ? new bootstrap.Modal(mEl) : null;

  function fallbackOpen(){
    if (!document.getElementById('__modal_bd')) {
      const bd = document.createElement('div');
      bd.id = '__modal_bd';
      bd.className = 'modal-backdrop fade show';
      document.body.appendChild(bd);
    }
    mEl.classList.add('show');
    mEl.style.display = 'block';
    mEl.removeAttribute('aria-hidden');
    mEl.setAttribute('aria-modal','true');
    document.body.classList.add('modal-open');
  }
  function fallbackClose(){
    const bd = document.getElementById('__modal_bd');
    if (bd) bd.remove();
    mEl.classList.remove('show');
    mEl.style.display = 'none';
    mEl.setAttribute('aria-hidden','true');
    mEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
  }
  function showModal(){ if (bsModal) bsModal.show(); else fallbackOpen(); }
  function hideModal(){ if (bsModal) bsModal.hide(); else fallbackClose(); }

  const xBtn = mEl.querySelector('.btn-close');
  if (xBtn) xBtn.addEventListener('click', hideModal);

  function openModal(d){
    mErr.classList.add('d-none'); mErr.textContent='';
    mId.value = d.id;
    mCodigo.value = d.codigo || '';
    mDesc.value = d.descripcion || '';
    mCant.value = (d.cantidad_presupuestada ?? 0);
    mPrecio.value = (d.precio_unitario_presupuestado ?? 0);
    showModal();
  }

  document.getElementById('btnPrecioVenta').addEventListener('click', async ()=>{
    const codigo = mCodigo.value;
    try{
      const r = await getJSON(ep('ajaxprecio'), {codigo:codigo});
      mPrecio.value = r?.precio ?? mPrecio.value;
    }catch(e){ mErr.textContent=e.message; mErr.classList.remove('d-none'); }
  });
  document.getElementById('btnPrecioDirecto').addEventListener('click', async ()=>{
    const codigo = mCodigo.value;
    try{
      const r = await getJSON(ep('ajaxprecio'), {codigo:codigo});
      mPrecio.value = r?.precio ?? mPrecio.value;
    }catch(e){ mErr.textContent=e.message; mErr.classList.remove('d-none'); }
  });

  document.getElementById('btnDelItem').addEventListener('click', async ()=>{
    if(!confirm('¿Eliminar este ítem?')) return;
    try{
      await postJSON(ep('ajaxdelitem'), {id:mId.value, csrf:csrf});
      hideModal();
      renderItems();
    }catch(e){ mErr.textContent=e.message; mErr.classList.remove('d-none'); }
  });

  document.getElementById('editForm').addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    try{
      await postJSON(ep('ajaxsaveitem'), {
        id: mId.value,
        cantidad_presupuestada: mCant.value,
        precio_unitario_presupuestado: mPrecio.value,
        csrf: csrf
      });
      hideModal();
      renderItems();
    }catch(e){ mErr.textContent=e.message; mErr.classList.remove('d-none'); }
  });

  // Inicio
  renderFamilias();
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
