<?php
// /costos/app/views/doc_categorias_index.php
$base = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$rows = $rows ?? [];
$modulos = $modulos ?? [];
$q = $q ?? '';
$mod = $mod ?? '';
$a = isset($a) ? $a : '';
$limit  = isset($limit) ? (int)$limit : 50;
$offset = isset($offset)? (int)$offset: 0;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 m-0">Categorías de Documentos</h1>
  <div>
    <a href="<?= $base ?>/doc-categorias/create" class="btn btn-primary">Nueva</a>
    <a href="<?= $base ?>/usuarios/index" class="btn btn-outline-secondary">Volver</a>
  </div>
</div>

<!-- Filtro -->
<form class="row g-2 mb-3" method="get" action="<?= $base ?>/doc-categorias/index">
  <div class="col-sm-4">
    <input type="text" name="q" value="<?= htmlspecialchars((string)$q) ?>" class="form-control" placeholder="Buscar por nombre o descripción">
  </div>
  <div class="col-sm-3">
    <select name="mod" class="form-select">
      <option value="">-- Módulo --</option>
      <?php foreach($modulos as $m): ?>
        <option value="<?= htmlspecialchars($m) ?>" <?= $mod===$m?'selected':'' ?>><?= htmlspecialchars($m) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-sm-3">
    <select name="a" class="form-select">
      <option value="">-- Estado --</option>
      <option value="1" <?= ($a==='1' || $a===1)?'selected':'' ?>>Activas</option>
      <option value="0" <?= ($a==='0' || $a===0)?'selected':'' ?>>Inactivas</option>
    </select>
  </div>
  <div class="col-sm-2 d-grid">
    <button class="btn btn-outline-primary">Filtrar</button>
  </div>
</form>

<!-- Mensajes -->
<div id="toastSlot" class="position-relative mb-2" style="height:56px;">
  <div id="toast" class="toast align-items-center text-bg-success border-0 position-absolute top-0 start-50 translate-middle-x"
       role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="1700" style="min-width: 280px;">
    <div class="d-flex">
      <div id="toastBody" class="toast-body">OK</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Módulo</th>
        <th>Nombre</th>
        <th>Descripción</th>
        <th>Activo</th>
        <th style="width:160px;">Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php if(!$rows): ?>
      <tr><td colspan="6" class="text-center py-4 text-muted">Sin resultados</td></tr>
    <?php endif; ?>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['modulo']) ?></td>
        <td><?= htmlspecialchars($r['nombre']) ?></td>
        <td><?= htmlspecialchars((string)($r['descripcion'] ?? '')) ?></td>
        <td>
          <?php if(!empty($r['activo'])): ?>
            <span class="badge bg-success">Sí</span>
          <?php else: ?>
            <span class="badge bg-secondary">No</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="<?= $base ?>/doc-categorias/edit/<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
          <form method="post" action="<?= $base ?>/doc-categorias/delete/<?= (int)$r['id'] ?>" class="d-inline"
                onsubmit="return confirm('¿Borrar categoría?');">
            <button class="btn btn-sm btn-outline-danger">Borrar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
(function(){
  const params = new URLSearchParams(window.location.search);
  if (params.has('ok')) {
    showToast('Operación exitosa','success',1700);
  } else if (params.has('e')) {
    showToast(decodeURIComponent(params.get('e')||'Error'),'danger',2000);
  }

  function showToast(msg, type, delay){
    const t = document.getElementById('toast');
    const b = document.getElementById('toastBody');
    if (!t || !b) return;
    t.className = 'toast align-items-center text-bg-' + (type||'success') + ' border-0 position-absolute top-0 start-50 translate-middle-x';
    b.textContent = msg;
    if (delay) t.setAttribute('data-bs-delay', String(delay));
    new bootstrap.Toast(t).show();
  }
})();
</script>
