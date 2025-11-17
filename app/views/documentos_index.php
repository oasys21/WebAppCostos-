<?php
$__base = rtrim((string)($base ?? ''), '/');
if (class_exists('Session') && !Session::user()) { header('Location: ' . $__base . '/'); exit; }

if (!function_exists('u')) {
  function u($base, $path){
    $url=rtrim($base??'','/').'/'.ltrim($path??'','/');
    return preg_replace('~(?<!:)//+~','/',$url);
  }
}

$pageTitle  = $pageTitle ?? 'Documentos';
$rows       = $rows ?? [];
$q          = htmlspecialchars((string)($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8');
$proySel    = (string)($proySel ?? ($_GET['proyecto'] ?? '')); // codigo_proy
$estado     = htmlspecialchars((string)($_GET['estado'] ?? ''), ENT_QUOTES, 'UTF-8');
$modulo     = htmlspecialchars((string)($_GET['modulo'] ?? ''), ENT_QUOTES, 'UTF-8');
$categoriaId= (int)($_GET['categoria_id'] ?? 0);
$modulos    = $modulos ?? [];
$categorias = $categorias ?? [];
$proyectos  = $proyectos ?? []; // lista (codigo_proy, nombre)
?>
<style> body{padding-top:4.5rem;} </style>

<div class="container py-3">
  <div class="d-flex align-items-center mb-2">
    <h1 class="h4 mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
    <div class="ms-auto">
      <a class="btn btn-sm btn-primary" href="<?= u($__base,'index.php?r=documentos/create') ?>">Nuevo</a>
    </div>
  </div>

  <form class="row g-2 align-items-end mb-3" method="get" action="<?= u($__base, 'index.php') ?>" id="filtrosForm" novalidate>
    <input type="hidden" name="r" value="documentos/index">

    <div class="col-12 col-md-3">
      <label class="form-label" for="q">Buscar</label>
      <input type="text" class="form-control" id="q" name="q" value="<?= $q ?>" placeholder="título o nombre original">
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label" for="proyecto">Proyecto</label>
      <select class="form-select" id="proyecto" name="proyecto">
        <option value="">— Todos —</option>
        <?php foreach ($proyectos as $p):
          $cod = (string)($p['codigo_proy'] ?? '');
          $nom = (string)($p['nombre'] ?? '');
          if ($cod === '' || $nom === '') continue;
          $sel = ($proySel !== '' && $proySel === $cod) ? 'selected' : '';
        ?>
          <option value="<?= htmlspecialchars($cod) ?>" <?= $sel ?>><?= htmlspecialchars($cod.' — '.$nom) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label" for="modulo">Módulo</label>
      <select class="form-select" id="modulo" name="modulo">
        <option value="">— Todos —</option>
        <?php foreach ($modulos as $m): ?>
          <option value="<?= htmlspecialchars((string)$m) ?>" <?= ($modulo===(string)$m?'selected':'') ?>><?= htmlspecialchars((string)$m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label" for="estado">Estado</label>
      <select class="form-select" id="estado" name="estado">
        <option value="">— Todos —</option>
        <option value="vigente" <?= $estado==='vigente'?'selected':'' ?>>Vigente</option>
        <option value="vencido"  <?= $estado==='vencido' ?'selected':'' ?>>Vencido</option>
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label" for="categoria_id">Categoría</label>
      <select class="form-select" id="categoria_id" name="categoria_id">
        <option value="0">— Todas —</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($categoriaId===(int)$c['id']?'selected':'') ?>><?= htmlspecialchars((string)$c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-2">
      <label class="form-label d-none d-md-block">&nbsp;</label>
      <div>
        <button class="btn btn-secondary">Filtrar</button>
        <a class="btn btn-outline-secondary" href="<?= u($__base,'index.php?r=documentos/index') ?>">Limpiar</a>
      </div>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm align-middle table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>Módulo</th>
          <th>Proyecto</th>
          <th>Ítem</th>
          <th>Título</th>
          <th>Categoría</th>
          <th>Estado</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars((string)($r['modulo'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['proyecto'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['itemcosto'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['titulo'] ?? $r['nombre_original'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['categoria'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['estado'] ?? '')) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary me-1" href="<?= u($__base,'index.php?r=documentos/preview/'.(int)$r['id']) ?>" title="Ver/Preview" target="_blank" rel="noopener">Ver</a>
            <a class="btn btn-sm btn-outline-primary me-1" href="<?= u($__base,'index.php?r=documentos/download/'.(int)$r['id']) ?>" title="Descargar">Descargar</a>
            <a class="btn btn-sm btn-outline-warning me-1" href="<?= u($__base,'index.php?r=documentos/edit/'.(int)$r['id']) ?>" title="Editar">Editar</a>
            <a class="btn btn-sm btn-outline-dark" href="<?= u($__base,'index.php?r=documentos/versions/'.(int)$r['id']) ?>" title="Versiones">Versiones</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Fallback: si por alguna razón el combo trae solo 1-2 opciones, recarga desde la API (método de ETAPAS)
(function(){
  function fillProyectos(list){
    var $sel = document.getElementById('proyecto');
    if (!$sel) return;
    var selValue = "<?= htmlspecialchars($proySel) ?>";
    $sel.innerHTML = '<option value="">— Todos —</option>';
    list.forEach(function(p){
      var opt = document.createElement('option');
      opt.value = (p.codigo_proy||'').toString();
      opt.textContent = (p.codigo_proy||'')+' — '+(p.nombre||'');
      if (selValue && selValue===opt.value) opt.selected = true;
      if (opt.value) $sel.appendChild(opt);
    });
  }
  var sel = document.getElementById('proyecto');
  if (sel && sel.options.length <= 2) {
    fetch('<?= $__base ?>/index.php?r=documentos/proyectos_json')
      .then(r=>r.json()).then(j=>{ if(j&&j.ok&&Array.isArray(j.data)){ fillProyectos(j.data); } })
      .catch(()=>{ /* silencioso */ });
  }
})();
</script>
