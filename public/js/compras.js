// public/js/compras.js
window.Compras = (() => {
  function rowTemplate(idx) {
    return `
      <tr>
        <td class="lin">${idx + 1}</td>
        <td><input name="items[${idx}][codigo]" list="catalogo" required style="min-width:110px"></td>
        <td><input name="items[${idx}][descripcion]" style="min-width:320px"></td>
        <td><input name="items[${idx}][unidad]" value="UND" size="4"></td>
        <td>
          <select name="items[${idx}][tipo_costo]">
            <option>MAT</option><option>MO</option><option>EQ</option><option>SUBC</option>
          </select>
        </td>
        <td><input type="number" step="0.01" name="items[${idx}][cantidad]" value="0" class="qty"></td>
        <td><input type="number" step="0.01" name="items[${idx}][precio_unitario]" value="0" class="pu"></td>
        <td class="monto" style="text-align:right;">0.00</td>
        <td><input type="date" name="items[${idx}][fecha_servicio]"></td>
        <td><button type="button" class="del">✕</button></td>
      </tr>
    `;
  }

  function addRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    if (!tbody) return;
    const idx = tbody.querySelectorAll('tr').length;
    tbody.insertAdjacentHTML('beforeend', rowTemplate(idx));
    recalcLines();
  }

  function ensureOneRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    if (tbody && tbody.querySelectorAll('tr').length === 0) addRow();
  }

  function recalcLines() {
    const rows = document.querySelectorAll('#itemsTable tbody tr');
    let subtotal = 0;
    rows.forEach((tr) => {
      const qty = parseFloat(tr.querySelector('input.qty, input[name*="[cantidad]"]')?.value || '0');
      const pu  = parseFloat(tr.querySelector('input.pu,  input[name*="[precio_unitario]"]')?.value || '0');
      const monto = (qty * pu) || 0;
      subtotal += monto;
      const tdMonto = tr.querySelector('.monto');
      if (tdMonto) tdMonto.textContent = monto.toFixed(2);
    });
    const st = document.querySelector('#subtotal');
    if (st) st.value = subtotal.toFixed(2);
    recalcTotal();
  }

  function recalcTotal() {
    const subt = parseFloat(document.querySelector('#subtotal')?.value || '0');
    const imp  = parseFloat(document.querySelector('#impuesto')?.value || '0');
    const desc = parseFloat(document.querySelector('#descuento')?.value || '0');
    const total = (subt - desc) + imp;
    const el = document.querySelector('#total');
    if (el) el.textContent = total.toFixed(2);
  }

  function onClick(e) {
    if (e.target && e.target.id === 'addItem') addRow();
    if (e.target && e.target.classList && e.target.classList.contains('del')) {
      const tr = e.target.closest('tr');
      if (tr) tr.remove();
      renumber();
      recalcLines();
    }
  }

  function renumber(){
    const rows = document.querySelectorAll('#itemsTable tbody tr');
    rows.forEach((tr, i) => {
      const cell = tr.querySelector('.lin');
      if (cell) cell.textContent = i+1;
    });
  }

  function onInput(e) {
    if (!e.target) return;
    if (e.target.closest('#itemsTable')) recalcLines();
    if (e.target.id === 'impuesto' || e.target.id === 'descuento') recalcTotal();
  }

  function initForm() {
    document.removeEventListener('click', onClick);
    document.removeEventListener('input', onInput);
    document.addEventListener('click', onClick);
    document.addEventListener('input', onInput);
    ensureOneRow();
    recalcLines();
  }

  return { initForm, ensureOneRow };
})();