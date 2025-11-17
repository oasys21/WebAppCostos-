<?php
// /costos/app/views/proyectos_index.php
$base      = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$q         = $q   ?? '';
$rut       = $rut ?? '';
$rows      = $rows ?? [];
$total     = (int)($total ?? 0);
$page      = (int)($page ?? 1);
$perPage   = (int)($perPage ?? 20);
$pages     = (int)($pages ?? 1);
$showInact = !empty($showInact);
?>
 <style>
 <!--
body{padding-top:4.5rem; 	
background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); 
background-color: transparent;	background-repeat: repeat;}	
-->
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="d-flex justify-content-between align-items-center mb-3" style="width:70%;">
  <h1 class="h4 m-0">Proyectos</h1>
  <div>
    <a class="btn btn-primary btn-sm" href="<?= $base ?>/proyectos/create">
      <i class="bi bi-plus-circle"></i> Nuevo
    </a>
  </div>
</div>

<form class="row g-2 align-items-end mb-3" method="get" action="<?= $base ?>/proyectos/index" style="width:70%;">
  <div class="col-sm-4">
    <label class="form-label">Buscar</label>
    <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre o descripción">
  </div>
  <div class="col-sm-3">
    <label class="form-label">Cliente (RUT)</label>
    <input type="text" class="form-control" name="rut" value="<?= htmlspecialchars($rut) ?>" placeholder="11111111-1">
  </div>
  <div class="col-sm-3">
    <label class="form-label d-block">Mostrar</label>
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" role="switch" id="swInact" name="inact" value="1" <?= $showInact?'checked':'' ?>>
      <label class="form-check-label" for="swInact">Incluir inactivos</label>
    </div>
  </div>
  <div class="col-sm-2">
    <button class="btn btn-warning w-100 btn-sm"><i class="bi bi-search"></i> Filtrar</button>
  </div>
</form>

<div class="table-responsive " >
  <table class="table table-hover align-middle">
    <thead>
      <tr>
        <th style="width: 80px;">ID</th>
        <th>Nombre</th>
        <th style="width: 160px;">Cliente (RUT)</th>
        <th style="width: 110px;">Estado</th>
        <th style="width: 180px;">Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($rows): foreach ($rows as $r): ?>
      <tr>
        <td class="text-muted">#<?= (int)$r['id'] ?></td>
        <td>
          <div class="fw-semibold">
            <a href="<?= $base ?>/proyectos/edit/<?= (int)$r['id'] ?>">
              <?= htmlspecialchars((string)$r['nombre']) ?>
            </a>
          </div>
          <?php if (!empty($r['descripcion'])): ?>
            <div class="small text-muted"><?= htmlspecialchars((string)$r['descripcion']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string)($r['rut_cliente'] ?? '')) ?></td>
        <td>
          <?php if ((int)$r['activo']===1): ?>
            <span class="badge text-bg-success">Activo</span>
          <?php else: ?>
            <span class="badge text-bg-secondary">Inactivo</span>
          <?php endif; ?>
        </td>
        <td>
          <a class="btn btn-outline-primary btn-sm" href="<?= $base ?>/presupuestos?proyecto_id=<?= (int)$r['id'] ?>">
            <i class="bi bi-pencil"></i> Abrir
          </a>
          <form action="<?= $base ?>/proyectos/toggle/<?= (int)$r['id'] ?>" method="post" class="d-inline">
            <button class="btn btn-warning btn-sm" onclick="return confirm('¿Cambiar estado?')">
              <i class="bi bi-power"></i> <?= ((int)$r['activo']===1?'Desactivar':'Activar') ?>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No hay proyectos.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class= "mx-auto d-block d-flex justify-content-between align-items-center">
  <div class="small text-muted">
    Mostrando <?= count($rows) ?> de <?= $total ?> (página <?= $page ?>/<?= $pages ?>)
  </div>
  <nav>
    <ul class="pagination pagination-sm mb-0">
      <?php for($i=1;$i<=$pages;$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="<?= $base ?>/proyectos/index?page=<?= $i ?>&q=<?= urlencode($q) ?>&rut=<?= urlencode($rut) ?><?= $showInact ? '&inact=1':'' ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>
</div>