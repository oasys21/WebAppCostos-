// /costos/public/js/app.js

// Toast flotante simple (arriba a la derecha)
window.showToast = function (msg, type) {
  type = type || 'info';
  const html = `
    <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="1800">
      <div class="d-flex">
        <div class="toast-body">${msg || 'Mensaje'}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>`;
  let holder = document.getElementById('globalToasts');
  if (!holder) {
    holder = document.createElement('div');
    holder.id = 'globalToasts';
    holder.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(holder);
  }
  holder.insertAdjacentHTML('beforeend', html);
  const el = holder.lastElementChild;
  const t = new bootstrap.Toast(el);
  t.show();
};

// Toast inline (en slot fijo, no desplaza el layout)
window.showToastInline = function(toastId, bodyId, msg, type, delay) {
  const toastEl = document.getElementById(toastId);
  const bodyEl  = document.getElementById(bodyId);
  if (!toastEl || !bodyEl) return;
  toastEl.classList.remove('text-bg-danger','text-bg-warning','text-bg-success','text-bg-info');
  toastEl.classList.add('text-bg-' + (type||'danger'));
  bodyEl.textContent = msg || 'Mensaje';
  const t = new bootstrap.Toast(toastEl, {delay: delay||1700});
  t.show();
};

// Helper global BASE_URL si faltara
if (typeof window.BASE_URL === 'undefined') {
  window.BASE_URL = '';
}

(function(){
  const base = (window.APP_BASE || document.body.getAttribute('data-base') || '').replace(/\/$/,'');
  function loadClientesInto(sel, q=''){
    const url = base + '/proyectos/ajaxclientes' + (q ? ('?q='+encodeURIComponent(q)) : '');
    fetch(url).then(r=>r.json()).then(rows=>{
      // limpia
      [...sel.options].forEach((o,i)=>{ if(i>0) sel.remove(1); });
      rows.forEach(r=> sel.add(new Option(r.label, r.rut)));
    }).catch(()=>{});
  }
  // Carga inicial para selects visibles
  document.querySelectorAll('select.js-clientes-select').forEach(sel=>{
    loadClientesInto(sel,'');
  });
  // Si usas Bootstrap Modal: vuelve a cargar cuando el modal se muestra
  document.querySelectorAll('.modal').forEach(m=>{
    m.addEventListener('shown.bs.modal', ()=>{
      m.querySelectorAll('select.js-clientes-select').forEach(sel=> loadClientesInto(sel,''));
    });
  });
})();

