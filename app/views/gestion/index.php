<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array  $filters */
/** @var string $estadoP */
/** @var string $estadoS */
/** @var array  $ped_pend */
/** @var array  $sol_pend */
/** @var array  $usuarios */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';

function dmy($s){
  if(!$s) return '';
  if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~',$s,$m)) return $m[3].'/'.$m[2].'/'.$m[1];
  return $s;
}
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0"><?= $h($pageTitle) ?></h3>
  <div>
    <a href="<?= $h($base) ?>/gestion/nuevo" class="btn btn-primary btn-sm">Agregar Tarea</a>
  </div>
</div>

<form class="row g-2 mb-3" method="get" action="<?= $h($base) ?>/gestion/index" autocomplete="off">
  <div class="col-sm-4">
    <label class="form-label mb-1">Buscar</label>
    <input type="text" name="q" class="form-control form-control-sm" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Texto en tarea o respuesta">
  </div>
  <div class="col-sm-2">
    <label class="form-label mb-1">Desde</label>
    <input type="text" name="desde" class="form-control form-control-sm date-dmy" value="<?= $h($_GET['desde'] ?? '') ?>" placeholder="dd/mm/aaaa">
  </div>
  <div class="col-sm-2">
    <label class="form-label mb-1">Hasta</label>
    <input type="text" name="hasta" class="form-control form-control-sm date-dmy" value="<?= $h($_GET['hasta'] ?? '') ?>" placeholder="dd/mm/aaaa">
  </div>
  <div class="col-sm-2">
    <label class="form-label mb-1">Estado Pedidos</label>
    <select name="estadoP" class="form-select form-select-sm">
      <option value="">-- Todos --</option>
      <?php foreach(['pendiente','realizada','cerrada','anulada'] as $e): ?>
        <option value="<?= $h($e) ?>" <?= (($_GET['estadoP'] ?? '')===$e)?'selected':'' ?>><?= $h(ucfirst($e)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-sm-2">
    <label class="form-label mb-1">Estado Solicitudes</label>
    <select name="estadoS" class="form-select form-select-sm">
      <option value="">-- Todos --</option>
      <?php foreach(['pendiente','realizada','cerrada','anulada'] as $e): ?>
        <option value="<?= $h($e) ?>" <?= (($_GET['estadoS'] ?? '')===$e)?'selected':'' ?>><?= $h(ucfirst($e)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 d-flex gap-2">
    <button class="btn btn-sm btn-outline-primary">Buscar</button>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $h($base) ?>/dashboard">Volver</a>
  </div>
</form>

<ul class="nav nav-tabs" id="gTabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-ped" type="button">Pedidos</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sol" type="button">Solicitudes</button></li>
</ul>
<div class="tab-content border border-top-0 p-3 bg-white">
  <!-- Pedidos (me han pedido) -->
  <div class="tab-pane fade show active" id="tab-ped">
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:90px">N°</th>
            <th style="width:120px">F. Solicitud</th>
            <th>Solicitante</th>
            <th>Resumen</th>
            <th style="width:120px">Estado</th>
            <th style="width:120px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!empty($ped_pend)): foreach($ped_pend as $g): ?>
          <tr>
            <td><?= (int)$g['numero_gestion'] ?></td>
            <td><?= $h(dmy($g['fecha_solicitud'] ?? '')) ?></td>
            <td><?= $h($g['origen_nombre'] ?? '') ?></td>
            <td class="small"><?= $h(mb_strimwidth((string)($g['text_tarea'] ?? ''), 0, 120, '…','UTF-8')) ?></td>
            <td><span class="badge bg-secondary"><?= $h($g['estado_gestion'] ?? '') ?></span></td>
            <td class="text-nowrap">
              <a href="<?= $h($base) ?>/gestion/ver/<?= (int)$g['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
              <a href="<?= $h($base) ?>/gestion/editar/<?= (int)$g['id'] ?>" class="btn btn-sm btn-success">Editar</a>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center text-muted">Sin resultados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Solicitudes (yo pedí) -->
  <div class="tab-pane fade" id="tab-sol">
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:90px">N°</th>
            <th style="width:120px">F. Solicitud</th>
            <th>Asignado a</th>
            <th>Resumen</th>
            <th style="width:120px">Estado</th>
            <th style="width:120px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!empty($sol_pend)): foreach($sol_pend as $g): ?>
          <tr>
            <td><?= (int)$g['numero_gestion'] ?></td>
            <td><?= $h(dmy($g['fecha_solicitud'] ?? '')) ?></td>
            <td><?= $h($g['destino_nombre'] ?? '') ?></td>
            <td class="small"><?= $h(mb_strimwidth((string)($g['text_tarea'] ?? ''), 0, 120, '…','UTF-8')) ?></td>
            <td><span class="badge bg-secondary"><?= $h($g['estado_gestion'] ?? '') ?></span></td>
            <td class="text-nowrap">
              <a href="<?= $h($base) ?>/gestion/ver/<?= (int)$g['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
              <a href="<?= $h($base) ?>/gestion/editar/<?= (int)$g['id'] ?>" class="btn btn-sm btn-success">Editar</a>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center text-muted">Sin resultados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
<script>
// Input mascara simple para dd/mm/aaaa (no bloquea, solo guía)
document.querySelectorAll('.date-dmy').forEach(el=>{
  el.addEventListener('input', e=>{
    let v = e.target.value.replace(/[^\d]/g,'').slice(0,8);
    if (v.length>=5) v = v.slice(0,2)+'/'+v.slice(2,4)+'/'+v.slice(4);
    else if (v.length>=3) v = v.slice(0,2)+'/'+v.slice(2);
    e.target.value = v;
  });
});
</script>
