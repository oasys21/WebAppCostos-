<?php
$__base = rtrim((string)($base ?? ''), '/');
if (class_exists('Session') && !Session::user()) { header('Location: ' . $__base . '/'); exit; }
if (!function_exists('u')) { function u($base,$path){ $url=rtrim($base??'','/').'/'.ltrim($path??'','/'); return preg_replace('~(?<!:)//+~','/',$url);} }

$pageTitle = $pageTitle ?? 'Clientes';
$rows = $rows ?? [];
$q = htmlspecialchars((string)($q ?? ''), ENT_QUOTES, 'UTF-8');
$activoSel = $activoSel;
$ok = (string)($_GET['ok'] ?? '');
$err = (string)($_GET['e'] ?? '');
?>
<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
    <div><a class="btn btn-primary" href="<?= u($__base,'index.php?r=clientes/create') ?>">Nuevo</a></div>
  </div>

  <form class="row g-2 align-items-end mb-3" method="get" action="<?= u($__base,'index.php') ?>">
    <input type="hidden" name="r" value="clientes/index">
    <div class="col-12 col-md-4">
      <label class="form-label" for="q">Buscar</label>
      <input class="form-control" type="text" id="q" name="q" value="<?= $q ?>" placeholder="nombre, RUT, razón, rubro, ciudad">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="activo">Estado</label>
      <select class="form-select" id="activo" name="activo">
        <option value=""  <?= $activoSel===null?'selected':'' ?>>Todos</option>
        <option value="1" <?= $activoSel===1?'selected':'' ?>>Activos</option>
        <option value="0" <?= $activoSel===0?'selected':'' ?>>Inactivos</option>
      </select>
    </div>
    <div class="col-12 col-md-2">
      <button class="btn btn-outline-primary" type="submit">Buscar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th><th>Nombre</th><th>RUT</th><th>Razón</th><th>Rubro</th><th>Ciudad</th><th>Estado</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if(empty($rows)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Sin resultados.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars((string)$r['nombre']) ?></td>
          <td><?= htmlspecialchars((string)($r['rut'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['razon'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['rubro'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['ciudad'] ?? '')) ?></td>
          <td><span class="badge bg-<?= ((int)$r['activo']===1?'success':'secondary') ?>"><?= (int)$r['activo']===1?'Activo':'Inactivo' ?></span></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-warning me-1" href="<?= u($__base,'index.php?r=clientes/edit/'.(int)$r['id']) ?>">Editar</a>
            <form method="post" action="<?= u($__base,'index.php?r=clientes/destroy/'.(int)$r['id']) ?>" class="d-inline" onsubmit="return confirm('¿Eliminar cliente?');">
              <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  var ok = "<?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?>";
  var err = "<?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>";
  if (typeof window.showToast === 'function') {
    if (ok==='created') showToast('registro creado','success');
    else if (ok==='edited') showToast('registro editado','success');
    else if (ok==='deleted') showToast('registro borrado','success');
    else if (err) showToast('ERROR: '+err,'danger');
  }
})();
</script>
