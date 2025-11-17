<?php
declare(strict_types=1);

$__base = rtrim((string)($base ?? ''), '/');

// Control de sesión (salida a index)
if (class_exists('Session')) {
    if (!Session::user()) { header('Location: ' . $__base . '/'); exit; }
} else {
    if (empty($_SESSION['user'])) { header('Location: ' . $__base . '/'); exit; }
}

$categorias = $categorias ?? [];
$proyectos  = $proyectos  ?? [];   // [{codigo_proy, nombre}, ...]
$modulos    = $modulos    ?? [];
$pageTitle  = $pageTitle  ?? 'Nuevo documento';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<div class="container py-3">
  <h1 class="h5 mb-3"><?= h($pageTitle) ?></h1>

  <form method="post" enctype="multipart/form-data" action="<?= $__base ?>/index.php?r=documentos/store" class="row g-3">

    <div class="col-12 col-md-4">
      <label class="form-label" for="modulo">Módulo</label>
      <select class="form-select" id="modulo" name="modulo" required>
        <option value="">Seleccione…</option>
        <?php foreach ($modulos as $m): $m=(string)$m; ?>
          <option value="<?= h($m) ?>"><?= h($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label" for="proyecto">Proyecto</label>
      <select class="form-select" id="proyecto" name="proyecto" required>
        <!-- Default que evita NULL -->
        <option value="Sin-Proyecto" selected>Sin-Proyecto</option>
        <?php foreach ($proyectos as $p): ?>
          <?php $cp=(string)($p['codigo_proy']??''); $nm=(string)($p['nombre']??''); if($cp==='') continue; ?>
          <option value="<?= h($cp) ?>"><?= h($nm) ?> (<?= h($cp) ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Se listan proyectos activos donde eres dueño/miembro.</div>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label" for="itemcosto">Ítem de costo</label>
      <select class="form-select" id="itemcosto" name="itemcosto">
        <!-- Default que evita NULL -->
        <option value="Sin-Item-Costo" selected>Sin-Item-Costo</option>
      </select>
      <div class="form-text">Al elegir un proyecto, se cargan sus ítems (nivel ítem).</div>
    </div>

    <div class="col-12">
      <label class="form-label" for="titulo">Título</label>
      <input type="text" class="form-control" id="titulo" name="titulo" required>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="categoria_id">Categoría</label>
      <select class="form-select" id="categoria_id" name="categoria_id">
        <option value="0" selected>Sin categoría</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label d-block">&nbsp;</label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1" id="privado" name="privado">
        <label class="form-check-label" for="privado">Privado</label>
      </div>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="emitido_en">Emitido en</label>
      <input type="date" class="form-control" id="emitido_en" name="emitido_en">
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="vence_en">Vence en</label>
      <input type="date" class="form-control" id="vence_en" name="vence_en">
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label" for="estado">Estado</label>
      <select class="form-select" id="estado" name="estado">
        <option value="vigente" selected>Vigente</option>
        <option value="vencido">Vencido</option>
      </select>
    </div>

    <div class="col-12">
      <label class="form-label" for="archivo">Archivo</label>
      <input type="file" class="form-control" id="archivo" name="archivo" required>
      <div class="form-text">Formatos permitidos según política del sistema.</div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">Crear</button>
      <a href="<?= $__base ?>/index.php?r=documentos/index" class="btn btn-outline-secondary">Volver</a>
    </div>
  </form>
</div>

<script>
(function(){
  function domReady(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

  domReady(function(){
    var selProy = document.getElementById('proyecto');
    var selItem = document.getElementById('itemcosto');

    function setDefaultItem(){
      if (!selItem) return;
      selItem.innerHTML = '<option value="Sin-Item-Costo" selected>Sin-Item-Costo</option>';
    }

    function loadItemsFetch(codigo){
      if (!selItem) return;
      if (!codigo || codigo === 'Sin-Proyecto') { setDefaultItem(); return; }
      selItem.innerHTML = '<option value="">Cargando…</option>';
      fetch('<?= $__base ?>/index.php?r=documentos/items_api&proyecto=' + encodeURIComponent(codigo), {credentials:'same-origin'})
        .then(r=>r.json())
        .then(function(j){
          var html = '<option value="Sin-Item-Costo">Sin-Item-Costo</option>';
          if (j && j.ok && Array.isArray(j.data) && j.data.length){
            j.data.forEach(function(it){
              var val = (it.codigo || '').trim();
              var txt = (it.descripcion || val);
              if (!val) return;
              html += '<option value="'+val.replace(/"/g,'&quot;')+'">'+
                        (txt.replace(/</g,'&lt;'))+' ('+val.replace(/</g,'&lt;')+')' +
                      '</option>';
            });
          }
          selItem.innerHTML = html;
          // Default seleccionado
          selItem.value = 'Sin-Item-Costo';
        })
        .catch(function(){ setDefaultItem(); });
    }

    function loadItemsJQ(codigo){
      if (!selItem) return;
      if (!codigo || codigo === 'Sin-Proyecto') { setDefaultItem(); return; }
      selItem.innerHTML = '<option value="">Cargando…</option>';
      window.jQuery.getJSON('<?= $__base ?>/index.php?r=documentos/items_api', {proyecto: codigo})
        .done(function(j){
          var html = '<option value="Sin-Item-Costo">Sin-Item-Costo</option>';
          if (j && j.ok && Array.isArray(j.data) && j.data.length){
            j.data.forEach(function(it){
              var val = (it.codigo || '').trim();
              var txt = (it.descripcion || val);
              if (!val) return;
              html += '<option value="'+val.replace(/"/g,'&quot;')+'">'+
                        (txt.replace(/</g,'&lt;'))+' ('+val.replace(/</g,'&lt;')+')' +
                      '</option>';
            });
          }
          selItem.innerHTML = html;
          selItem.value = 'Sin-Item-Costo';
        })
        .fail(function(){ setDefaultItem(); });
    }

    function loadItems(codigo){
      if (window.jQuery && typeof window.jQuery.getJSON === 'function') loadItemsJQ(codigo);
      else loadItemsFetch(codigo);
    }

    if (selProy) {
      selProy.addEventListener('change', function(){ loadItems(this.value); });
      // Estado inicial: Sin-Proyecto → set default item
      setDefaultItem();
    }
  });
})();
</script>
