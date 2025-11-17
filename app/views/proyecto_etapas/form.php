<?php
declare(strict_types=1);
/** @var array $etapa   Cabecera: id, proyecto_id, item_costo, titulo, estado, fecha_* */
/** @var array $items   Lista de etapas/pasos existentes */
/** @var array $proyectos [{id,nombre}] */
/** @var string|null $form_token */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';

$editing = !empty($etapa['id']);
$action  = $editing ? ($base.'/proyecto-etapas/actualizar/'.(int)$etapa['id']) : ($base.'/proyecto-etapas/guardar');

$unidades    = ['ML','M2','M3','UN','KG','TM','OT'];
$estadosPaso = ['pendiente','en_proceso','terminado','anulado'];
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="mx-auto d-block d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0"><?= $editing ? 'Editar Plan de Etapas' : 'Nuevo Plan de Etapas' ?></h3>
  <div class="d-flex gap-2">
    <?php if($editing): ?>
      <a target="_blank" class="btn btn-primary btn-sm" href="<?= $h($base) ?>/proyecto-etapas/ver/<?= (int)$etapa['id'] ?>">Imprimir</a>
    <?php endif; ?>
    <a class="btn btn-secondary btn-sm" href="<?= $h($base) ?>/proyecto-etapas">Volver</a>
  </div>
</div>

<form method="post" action="<?= $h($action) ?>" id="frmPet" autocomplete="off">
  <?php if(!empty($form_token ?? '')): ?>
    <input type="hidden" name="form_token" value="<?= $h($form_token) ?>">
  <?php endif; ?>

  <!-- Cabecera -->
  <div class=" mb-3">
    <div class="card-header py-2"><strong>Cabecera</strong></div>
    <div class="card-body">
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label mb-1">Proyecto</label>
          <select name="proyecto_id" id="proyecto_id" class="form-select form-select-sm" required>
            <option value="">-- Seleccione --</option>
            <?php foreach(($proyectos ?? []) as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((string)($etapa['proyecto_id'] ?? '')===(string)$p['id'])?'selected':'' ?>>
                <?= $h($p['nombre'] ?? ('ID '.$p['id'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Ítem de costo (FFFGGGIIII)</label>
          <select name="item_costo" id="item_costo" class="form-select form-select-sm" required>
            <?php if(!empty($etapa['item_costo'])): ?>
              <option value="<?= $h($etapa['item_costo']) ?>" selected>
                <?= $h($etapa['item_costo']) ?>
              </option>
            <?php else: ?>
              <option value="">-- Seleccione proyecto primero --</option>
            <?php endif; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Estado</label>
          <select name="estado" class="form-select form-select-sm">
            <?php foreach(['borrador','planificado','en_proceso','completado','anulado'] as $e): ?>
              <option value="<?= $h($e) ?>" <?= ((string)($etapa['estado'] ?? '')===$e)?'selected':'' ?>><?= $h(ucfirst($e)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Título</label>
          <input type="text" name="titulo" class="form-control form-control-sm" maxlength="120" value="<?= $h($etapa['titulo'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Fecha inicio (Prog.)</label>
          <input type="date" name="fecha_inicio_prog" class="form-control form-control-sm" value="<?= $h($etapa['fecha_inicio_prog'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Fecha fin (Prog.)</label>
          <input type="date" name="fecha_fin_prog" class="form-control form-control-sm" value="<?= $h($etapa['fecha_fin_prog'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Fecha inicio (Real)</label>
          <input type="date" name="fecha_inicio_real" class="form-control form-control-sm" value="<?= $h($etapa['fecha_inicio_real'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Fecha fin (Real)</label>
          <input type="date" name="fecha_fin_real" class="form-control form-control-sm" value="<?= $h($etapa['fecha_fin_real'] ?? '') ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- Ítems (etapas/pasos) -->
<div class="mx-auto d-block " style="align:center; width:120%;">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
      <strong>Etapas / Pasos</strong>
      <button class="btn btn-sm btn-success" type="button" id="btnAdd">Agregar etapa</button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0" id="tblItems">
          <thead class="table-light">
            <tr class="text-center">
              <th style="width:60px">Línea</th>
              <th style="min-width:220px">Descripción</th>
              <th style="width:110px">Unidad</th>
              <th style="width:120px">Cantidad</th>
              <th style="width:140px">Valor</th>
              <th style="width:100px">% Peso</th>
              <th style="width:140px">Monto</th>
              <th style="width:140px">Estado</th>
              <th style="width:120px">Avance %</th>
              <th style="width:160px">F. Prog (I-F)</th>
              <th style="width:160px">F. Real (I-F)</th>
              <th style="width:70px">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $idx = 0;
            foreach(($items ?? []) as $it):
              $idx++;
            ?>
            <tr data-idx="<?= $idx ?>">
              <td class="text-center">
                <input type="hidden" name="items[<?= $idx ?>][id]" value="<?= $h($it['id'] ?? '') ?>">
                <input type="number" name="items[<?= $idx ?>][linea]" class="form-control form-control-sm text-center linea" value="<?= $h($it['linea'] ?? $idx) ?>">
              </td>
              <td>
                <textarea name="items[<?= $idx ?>][descripcion]" rows="2" class="form-control form-control-sm"><?= $h($it['descripcion'] ?? '') ?></textarea>
              </td>
              <td>
                <select name="items[<?= $idx ?>][unidad_med]" class="form-select form-select-sm">
                  <?php foreach($unidades as $u): ?>
                    <option value="<?= $h($u) ?>" <?= ((string)($it['unidad_med'] ?? 'UN')===$u)?'selected':'' ?>><?= $h($u) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" name="items[<?= $idx ?>][cantidad]" class="form-control form-control-sm qty" value="<?= number_format((float)($it['cantidad'] ?? 0), 2, ',', '.') ?>" inputmode="decimal" pattern="[0-9\.,]*">
              </td>
              <td>
                <input type="text" name="items[<?= $idx ?>][valor]" class="form-control form-control-sm money" value="<?= number_format((float)($it['valor'] ?? 0), 2, ',', '.') ?>" inputmode="decimal" pattern="[0-9\.,]*">
              </td>
              <td>
                <input type="number" name="items[<?= $idx ?>][porcentaje]" class="form-control form-control-sm text-end porc" value="<?= (int)($it['porcentaje'] ?? 0) ?>" min="0" max="100">
              </td>
              <td class="text-end">
                <input type="text" class="form-control form-control-sm text-end monto" value="<?= number_format((float)($it['monto'] ?? ((float)($it['cantidad'] ?? 0) * (float)($it['valor'] ?? 0))), 2, ',', '.') ?>" readonly tabindex="-1">
              </td>
              <td>
                <select name="items[<?= $idx ?>][estado_paso]" class="form-select form-select-sm">
                  <?php foreach($estadosPaso as $e): ?>
                    <option value="<?= $h($e) ?>" <?= ((string)($it['estado_paso'] ?? 'pendiente')===$e)?'selected':'' ?>><?= $h(ucfirst($e)) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="number" name="items[<?= $idx ?>][avance_pct]" class="form-control form-control-sm text-end av" value="<?= number_format((float)($it['avance_pct'] ?? 0), 2, '.', '') ?>" min="0" max="100" step="0.01">
              </td>
              <td>
                <div class="d-flex gap-1">
                  <input type="date" name="items[<?= $idx ?>][fecha_inicio_prog]" class="form-control form-control-sm" value="<?= $h($it['fecha_inicio_prog'] ?? '') ?>">
                  <input type="date" name="items[<?= $idx ?>][fecha_fin_prog]"   class="form-control form-control-sm" value="<?= $h($it['fecha_fin_prog'] ?? '') ?>">
                </div>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <input type="date" name="items[<?= $idx ?>][fecha_inicio_real]" class="form-control form-control-sm" value="<?= $h($it['fecha_inicio_real'] ?? '') ?>">
                  <input type="date" name="items[<?= $idx ?>][fecha_fin_real]"   class="form-control form-control-sm" value="<?= $h($it['fecha_fin_real'] ?? '') ?>">
                </div>
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btnDel">✕</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <th colspan="6" class="text-end">Total</th>
              <th class="text-end" id="ftTotal">CLP$ 0</th>
              <th colspan="5"></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-success">Guardar</button>
    <a class="btn btn-secondary" href="<?= $h($base) ?>/proyecto-etapas">Volver</a>
  </div>
</form>
</div>
<script>
// ===== Helpers LATAM =====
function parseLatam(s){
  if(!s) return 0;
  s = String(s).trim();
  s = s.replace(/\./g,'').replace(',', '.');
  s = s.replace(/[^0-9.\-]/g,'');
  const n = parseFloat(s);
  return isNaN(n) ? 0 : n;
}
function formatLatam(n, dec=2){
  n = Number(n)||0;
  const sign = n<0 ? '-' : '';
  n = Math.abs(n);
  const ent = Math.trunc(n);
  const deci = Math.round((n - ent)*Math.pow(10,dec));
  let entStr = String(ent).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  let decStr = String(deci).padStart(dec,'0');
  return sign + entStr + (dec>0?','+decStr:'');
}
function clp(n){ return 'CLP$ ' + formatLatam(Math.round(Number(n)||0), 0); }

// ===== Totales =====
function recalcularFila(tr){
  const qty = parseLatam(tr.querySelector('.qty')?.value || '0');
  const val = parseLatam(tr.querySelector('.money')?.value || '0');
  const monto = Math.round((qty*val)*100)/100;
  const mEl = tr.querySelector('.monto');
  if (mEl) mEl.value = formatLatam(monto,2);
  return monto;
}
function recalcularTotales(){
  let total = 0;
  document.querySelectorAll('#tblItems tbody tr').forEach(tr=>{
    total += recalcularFila(tr);
  });
  const ft = document.getElementById('ftTotal');
  if (ft) ft.textContent = clp(total);
}
recalcularTotales();

// ===== Add / Del filas =====
document.getElementById('btnAdd').addEventListener('click', ()=>{
  const tbody = document.querySelector('#tblItems tbody');
  const next = (Array.from(tbody.querySelectorAll('tr')).length + 1);
  const unidades = <?= json_encode($unidades) ?>;
  const estados  = <?= json_encode($estadosPaso) ?>;

  const tr = document.createElement('tr');
  tr.setAttribute('data-idx', String(next));
  tr.innerHTML = `
    <td class="text-center">
      <input type="hidden" name="items[${next}][id]" value="">
      <input type="number" name="items[${next}][linea]" class="form-control form-control-sm text-center linea" value="${next}">
    </td>
    <td><textarea name="items[${next}][descripcion]" rows="2" class="form-control form-control-sm"></textarea></td>
    <td>
      <select name="items[${next}][unidad_med]" class="form-select form-select-sm">
        ${unidades.map(u=>`<option value="${u}">${u}</option>`).join('')}
      </select>
    </td>
    <td><input type="text" name="items[${next}][cantidad]" class="form-control form-control-sm qty" value="0,00" inputmode="decimal" pattern="[0-9\\.,]*"></td>
    <td><input type="text" name="items[${next}][valor]" class="form-control form-control-sm money" value="0,00" inputmode="decimal" pattern="[0-9\\.,]*"></td>
    <td><input type="number" name="items[${next}][porcentaje]" class="form-control form-control-sm text-end porc" value="0" min="0" max="100"></td>
    <td class="text-end"><input type="text" class="form-control form-control-sm text-end monto" value="0,00" readonly tabindex="-1"></td>
    <td>
      <select name="items[${next}][estado_paso]" class="form-select form-select-sm">
        ${estados.map(e=>`<option value="${e}">${e.charAt(0).toUpperCase()+e.slice(1)}</option>`).join('')}
      </select>
    </td>
    <td><input type="number" name="items[${next}][avance_pct]" class="form-control form-control-sm text-end av" value="0.00" min="0" max="100" step="0.01"></td>
    <td>
      <div class="d-flex gap-1">
        <input type="date" name="items[${next}][fecha_inicio_prog]" class="form-control form-control-sm">
        <input type="date" name="items[${next}][fecha_fin_prog]" class="form-control form-control-sm">
      </div>
    </td>
    <td>
      <div class="d-flex gap-1">
        <input type="date" name="items[${next}][fecha_inicio_real]" class="form-control form-control-sm">
        <input type="date" name="items[${next}][fecha_fin_real]" class="form-control form-control-sm">
      </div>
    </td>
    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btnDel">✕</button></td>
  `;
  tbody.appendChild(tr);
  recalcularFila(tr);
  recalcularTotales();
});

// Delegación: borrar fila
document.querySelector('#tblItems').addEventListener('click', (e)=>{
  if (e.target.classList.contains('btnDel')) {
    const tr = e.target.closest('tr');
    tr.parentNode.removeChild(tr);
    recalcularTotales();
  }
});

// Recalcular al cambiar qty/valor
document.querySelector('#tblItems').addEventListener('input', (e)=>{
  if (e.target.classList.contains('qty') || e.target.classList.contains('money')) {
    const tr = e.target.closest('tr');
    recalcularFila(tr);
    recalcularTotales();
  }
});

// Normalizar antes de enviar (a punto decimal)
document.getElementById('frmPet').addEventListener('submit', (e)=>{
  // Validaciones básicas
  const pj = document.getElementById('proyecto_id')?.value;
  const ic = document.getElementById('item_costo')?.value;
  if (!pj || !ic) {
    alert('Debes seleccionar Proyecto e Ítem de costo.');
    e.preventDefault(); return;
  }

  document.querySelectorAll('#tblItems tbody tr').forEach(tr=>{
    const qtyEl = tr.querySelector('.qty');
    const valEl = tr.querySelector('.money');
    const mEl   = tr.querySelector('.monto');
    if (qtyEl) qtyEl.value = parseLatam(qtyEl.value).toFixed(2);
    if (valEl) valEl.value = parseLatam(valEl.value).toFixed(2);
    if (mEl)   mEl.value   = parseLatam(mEl.value).toFixed(2);
  });
});

// Carga de ítems de costo por proyecto (endpoint de Compras)
const selProyecto = document.getElementById('proyecto_id');
const selItemCst  = document.getElementById('item_costo');

function cargarPcosto(pid, selected=''){
  if (!pid){
    selItemCst.innerHTML = `<option value="">-- Seleccione proyecto primero --</option>`;
    return;
  }
  const url = (window.BASE_URL || '<?= $h($base) ?>') + '/compras/pcostos?proyecto_id=' + encodeURIComponent(pid);
  fetch(url, {headers:{'Accept':'application/json'}})
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(rows=>{
      selItemCst.innerHTML = `<option value="">-- Seleccione --</option>`;
      (rows||[]).forEach(r=>{
        const val = r.codigo || r.id;
        const txt = (r.codigo ? r.codigo+' — ' : '') + (r.nombre || '');
        const opt = document.createElement('option');
        opt.value = val; opt.textContent = txt;
        if (String(selected)===String(val)) opt.selected = true;
        selItemCst.appendChild(opt);
      });
    })
    .catch(()=>{
      selItemCst.innerHTML = `<option value="">(error cargando ítems de costo)</option>`;
    });
}
if (selProyecto) {
  selProyecto.addEventListener('change', e=>{ cargarPcosto(e.target.value); });
  // Carga inicial si corresponde
  <?php if(empty($etapa['item_costo'])): ?>
    if (selProyecto.value) cargarPcosto(selProyecto.value);
  <?php else: ?>
    if (selProyecto.value) cargarPcosto(selProyecto.value, <?= json_encode($etapa['item_costo']) ?>);
  <?php endif; ?>
}
</script>
