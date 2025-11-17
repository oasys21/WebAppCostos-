<?php
// app/views/imputaciones/form.php
declare(strict_types=1);
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$item          = $item          ?? [];
$itemId        = isset($itemId) ? (int)$itemId : (int)($item['id'] ?? 0);
$impId         = $impId         ?? null; // en create no hay
$proyectoIdSel = isset($proyectoIdSel) ? (int)$proyectoIdSel : (int)($item['imp_proyecto_id'] ?? $item['proyecto_id'] ?? 0);
$pcostoIdSel   = isset($pcostoIdSel)   ? (int)$pcostoIdSel   : (int)($item['imp_pcosto_id'] ?? 0);


/* ========= Helpers seguros (no pisan nada existente) ========= */
$__esc = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$__base = isset($base) && is_string($base) && $base !== ''
  ? rtrim($base, '/')
  : rtrim((string)($GLOBALS['cfg']['BASE_URL'] ?? ''), '/');

/* ========= Normalización de variables de entrada =========
   Esperado:
   - $item: array del ítem de compra (id, compra_id, codigo, descripcion, cantidad, precio_unitario, imp_proyecto_id, imp_pcosto_id, proyecto_id opcional)
   - $proyectos: lista [id, nombre]
   - $pcostos:   lista [id, codigo, nombre]
   - $proyectoIdSel, $pcostoIdSel (opcionales, preferencia UI al precargar)
   - $itemId: id del ítem (si no viene en $item)
   - $impId:  id de la imputación (0 cuando es alta)
*/
$itemId = isset($itemId) ? (int)$itemId : (int)($item['id'] ?? 0);
$impId  = isset($impId)  ? (int)$impId  : 0;
$isEdit = $impId > 0;

// Selecciones por defecto (proyecto e ítem de costo).
$selProyecto = isset($proyectoIdSel) ? (int)$proyectoIdSel
             : (isset($item['imp_proyecto_id']) ? (int)$item['imp_proyecto_id']
             : (isset($item['proyecto_id']) ? (int)$item['proyecto_id'] : 0));

$selPcosto   = isset($pcostoIdSel) ? (int)$pcostoIdSel
             : (isset($item['imp_pcosto_id']) ? (int)$item['imp_pcosto_id'] : 0);

// Acción del formulario (crear vs actualizar)
$action = $isEdit
  ? ($__base . '/index.php?r=imputaciones/actualizar/' . $impId)
  : ($__base . '/index.php?r=imputaciones/store/'      . $itemId);

// Token CSRF básico (si no existe, lo genera)
if (empty($_SESSION['form_token'])) {
  try { $_SESSION['form_token'] = bin2hex(random_bytes(16)); }
  catch (\Throwable $e) { $_SESSION['form_token'] = bin2hex((string)mt_rand()); }
}
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="">
  <div class="card-header d-flex align-items-center justify-content-between">
    <strong><?= $__esc($isEdit ? 'Editar imputación' : 'Imputar ítem de compra') ?></strong>
    <?php if (!empty($itemId)): ?>
      <span class="badge bg-secondary">Ítem #<?= (int)$itemId ?></span>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php if (!empty($item)): ?>
      <div class="mb-3 small text-muted">
        <div>Código compra: <code><?= $__esc($item['codigo'] ?? '') ?></code></div>
        <?php if (!empty($item['descripcion'])): ?>
          <div>Descripción: <?= $__esc($item['descripcion']) ?></div>
        <?php endif; ?>
        <?php
          $cant = isset($item['cantidad']) ? (float)$item['cantidad'] : null;
          $pu   = isset($item['precio_unitario']) ? (float)$item['precio_unitario'] : null;
          if ($cant !== null && $pu !== null):
        ?>
          <div>Cantidad: <?= $__esc(number_format($cant,2)) ?>
             · PU: <?= $__esc(number_format($pu,2)) ?>
             · Monto: <?= $__esc(number_format($cant*$pu,2)) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= $__esc($action) ?>">
      <input type="hidden" name="form_token" value="<?= $__esc($_SESSION['form_token']) ?>">
	  <input type="hidden" name="next" value="<?= $h($base) ?>/imputaciones/index">
		<input type="hidden" name="aplicar_ahora" id="aplicar_ahora" value="0">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="proyecto_id" class="form-label">Proyecto</label>
<select name="proyecto_id" id="proyecto_id" class="form-select" required>
  <option value="">— Selecciona —</option>
  <?php foreach(($proyectos??[]) as $p): ?>
    <option value="<?= (int)$p['id'] ?>" <?= ((int)$proyectoIdSel===(int)$p['id']?'selected':'') ?>>
      <?= $h($p['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>
          <div class="form-text">Primero selecciona el proyecto para cargar sus ítems de costo.</div>
        </div>

        <div class="col-md-6">
          <label for="proyecto_costo_id" class="form-label">Ítem de costo del proyecto</label>
<select name="proyecto_costo_id" id="proyecto_costo_id" class="form-select" required>
  <option value="">— Selecciona —</option>
  <?php foreach(($pcostos??[]) as $pc): ?>
    <option value="<?= (int)$pc['id'] ?>" <?= ((int)$pcostoIdSel===(int)$pc['id']?'selected':'') ?>>
      <?= $h(($pc['codigo']??'').' · '.($pc['nombre']??$pc['costo_glosa']??'')) ?>
    </option>
  <?php endforeach; ?>
</select>
          <?php if (!$selProyecto): ?>
            <div class="form-text text-danger">Debes elegir un proyecto para habilitar este selector.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
<div class="d-flex justify-content-end gap-2">
  <button type="submit" class="btn btn-primary">Guardar</button>
  <button type="button" class="btn btn-success" onclick="aplicarAhora()">Aplicar ahora</button>
</div>
        <?php
          // Link de retorno: intenta volver al "ver compra"
          $compraId = (int)($item['compra_id'] ?? ($_GET['compra_id'] ?? 0));
          $hrefVolver = $compraId > 0
            ? ($__base . '/compras/ver/' . $compraId)
            : ($__base . '/compras');
        ?>
        <a class="btn btn-outline-secondary" href="<?= $__esc($hrefVolver) ?>">Volver</a>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  const BASE = (window.BASE_URL || '').replace(/\/+$/,'');
  const selProyecto = document.getElementById('proyecto_id');
  const selPcosto   = document.getElementById('proyecto_costo_id');

  async function cargarPcostoProyecto(proyectoId, selectedId){
    if (!selPcosto) return;
    selPcosto.innerHTML = '<option value="">Cargando…</option>';

    try{
      const url = BASE + '/index.php?r=imputaciones/pcostos&proyecto_id=' + encodeURIComponent(proyectoId);
      const res = await fetch(url, { headers: { 'Accept':'application/json' }, credentials: 'same-origin' });

      const text = await res.text();    // ← SIEMPRE leemos como texto
      let payload = null;
      try { payload = JSON.parse(text); } catch(_){ /* no JSON puro */ }

      if (!payload || typeof payload !== 'object') {
        throw new Error('Respuesta no válida del servidor: ' + text.slice(0,200));
      }
      if (!payload.ok) {
        throw new Error(payload.error || 'Error desconocido del servidor');
      }

      const data = Array.isArray(payload.data) ? payload.data : [];
      selPcosto.innerHTML = '<option value="">— Selecciona —</option>';
      if (data.length === 0) {
        selPcosto.insertAdjacentHTML('beforeend','<option value="">Sin ítems para este proyecto</option>');
        return;
      }

      for (const r of data) {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = (r.codigo ? (r.codigo + ' · ') : '') + (r.nombre || '');
        if (selectedId != null && String(selectedId) === String(r.id)) opt.selected = true;
        selPcosto.appendChild(opt);
      }

    } catch (err) {
      console.error('Error cargando ítems de costo:', err);
      selPcosto.innerHTML = '<option value="">Error cargando ítems</option>';
      if (window.toastr) toastr.error('Error cargando ítems: ' + err.message);
      else alert('Error cargando ítems: ' + err.message);
    }
  }

  window.__cargarPcostoProyecto = cargarPcostoProyecto;

  if (selProyecto) {
    selProyecto.addEventListener('change', function(){
      const pid = this.value;
      if (!pid) { selPcosto.innerHTML = '<option value="">— Selecciona —</option>'; return; }
      cargarPcostoProyecto(pid, null);
    });
  }

  if (selProyecto && selPcosto && selProyecto.value && selPcosto.options.length <= 1) {
    const preSel = selPcosto.getAttribute('data-selected-id') || null;
    cargarPcostoProyecto(selProyecto.value, preSel);
  }
})();
</script>
