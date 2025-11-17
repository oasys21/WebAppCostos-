<?php
declare(strict_types=1);

$__base = rtrim((string)($base ?? ''), '/');
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Control de sesión (salida a index)
if (class_exists('Session')) {
    if (!Session::user()) { header('Location: ' . ($__base ?: '/')); exit; }
} else {
    if (empty($_SESSION['user'])) { header('Location: ' . ($__base ?: '/')); exit; }
}

$pageTitle = (string)($pageTitle ?? 'Nuevo proyecto');
$ownerIdDefault = (int)($ownerIdDefault ?? 0);
$clientes = (isset($clientes) && is_array($clientes)) ? $clientes : [];
?>
 <style>
 <!--
body{padding-top:4.5rem; 	
background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); 
background-color: transparent;	background-repeat: repeat;}	
-->
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
  <h1 class="h5 mb-3"><?= h($pageTitle) ?></h1>

  <form id="formCreate" method="post" action="<?= h($__base) ?>/index.php?r=proyectos/store" class="row g-3">

    <div class="col-12 col-md-6">
      <label class="form-label" for="nombre">Nombre</label>
      <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="160">
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="codigo_proy">Código del Proyecto</label>
      <input type="text" class="form-control" id="codigo_proy" name="codigo_proy" required maxlength="50" placeholder="PR-UNO045">
      <div class="form-text">Usa letras, números y guiones. Ej: PR-UNO045</div>
    </div>

    <!-- Selector simple de cliente -->
    <div class="col-12 col-md-6">
      <label class="form-label" for="rut_cliente">Cliente</label>
      <select id="rut_cliente" name="rut_cliente" class="form-select" required>
        <option value="">-- Seleccione cliente --</option>
        <?php foreach ($clientes as $c): 
              $rut = (string)($c['rut'] ?? '');
              $nom = (string)($c['nombre'] ?? '');
        ?>
          <option value="<?= h($rut) ?>"><?= h($nom.' — '.$rut) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="owner_user_id">Dueño</label>
      <select class="form-select" id="owner_user_id" name="owner_user_id" required></select>
    </div>

    <div class="col-12">
      <label class="form-label" for="descripcion">Descripción</label>
      <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
    </div>

    <div class="col-6 col-md-3">
      <label class="form-label" for="fecha_inicio">Inicio</label>
      <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio">
    </div>

    <div class="col-6 col-md-3">
      <label class="form-label" for="fecha_termino">Término</label>
      <input type="date" class="form-control" id="fecha_termino" name="fecha_termino">
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Estado</label>
      <select name="activo" class="form-select">
        <option value="1" selected>Activo</option>
        <option value="0">Inactivo</option>
      </select>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">Crear</button>
      <a class="btn btn-outline-secondary" href="<?= h($__base) ?>/index.php?r=proyectos/index">Cancelar</a>
    </div>
  </form>
</div>

<script>
(function(){
  function domReady(fn){ if(document.readyState!=='loading'){fn();} else {document.addEventListener('DOMContentLoaded', fn);} }
  domReady(function(){
    const base = <?= json_encode((string)$__base, JSON_UNESCAPED_SLASHES) ?>;

    // Normaliza código de proyecto
    document.getElementById('codigo_proy')?.addEventListener('input', function(){
      let v = this.value.toUpperCase().replace(/\s+/g,'-').replace(/[^A-Z0-9\-_.]/g,'');
      this.value = v;
    });

    // Cargar dueños
    (function loadOwners(){
      const sel = document.getElementById('owner_user_id');
      fetch(base + '/index.php?r=proyectos/ajaxusuarios')
        .then(r=>r.json())
        .then(rows=>{
          sel.innerHTML = '';
          rows.forEach(r=>{
            const o = new Option(String(r.label ?? r.name ?? r.id), String(r.id));
            sel.add(o);
          });
          const def = <?= (int)$ownerIdDefault ?>;
          if (def) sel.value = String(def);
        }).catch(()=>{});
    })();

    // Validación mínima en submit
    const form = document.getElementById('formCreate');
    form.addEventListener('submit', function(ev){
      const nom = form.nombre.value.trim();
      const cod = form.codigo_proy.value.trim();
      const rut = form.rut_cliente.value.trim();
      if (!nom || !cod || !rut){
        ev.preventDefault();
        alert('Nombre, Código y Cliente son obligatorios.');
      }
    });
  });
})();
</script>
