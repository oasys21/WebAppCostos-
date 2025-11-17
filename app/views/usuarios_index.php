<?php
// /costos/app/views/usuarios_index.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// BASE (sin depender del header para evitar doble include)
$base = rtrim((string)($this->cfg['BASE_URL'] ?? ''), '/');
if ($base === '') {
  $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  $base = rtrim(str_replace('\\','/', dirname($sn)), '/');
  if ($base === '.' || $base === '/') $base = '';
}

// Datos
$rows = $rows ?? [];

// Prefill de filtros desde GET (si el controller aún no filtra, al menos preserva la UI)
$q_rut    = trim((string)($_GET['rut']    ?? ''));
$q_nombre = trim((string)($_GET['nombre'] ?? ''));
$q_email  = trim((string)($_GET['email']  ?? ''));
$q_perfil = trim((string)($_GET['perfil'] ?? ''));
$q_act    = $_GET['act'] ?? []; // arreglo con '1' y/o '0'

// Si no viene ningún act[], por defecto consideramos ambos marcados en la UI
$showActivo   = in_array('1', (array)$q_act, true) || empty($q_act);
$showInactivo = in_array('0', (array)$q_act, true) || empty($q_act);

// Opciones de perfil
$perfiles = ['ADM','CON','BOD','OPE','USR'];
?>
<style>
  body{padding-top:4.5rem; background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent; background-repeat: repeat;}
  .table-sm td, .table-sm th { padding-top: .42rem; padding-bottom: .42rem; }
  .btn-icon { padding: 0 .5rem; height: 32px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
  .svg-ico { width: 16px; height: 16px; display: inline-block; vertical-align: middle; }
  .filter-card .form-label { font-size: .9rem; margin-bottom: .25rem; }
  .filter-actions { display: flex; gap: .5rem; align-items: end; }
</style>

<div class="mx-auto d-block d-flex justify-content-between align-items-center mb-3" style="width:70%;">
  <h2 class="h4 mb-0">Usuarios</h2>
  <div class="d-flex gap-2">
    <a class="btn btn-md btn-success" href="<?= htmlspecialchars($base) ?>/usuarios/create">
      <span class="svg-ico me-1" aria-hidden="true">
        <svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 2a.5.5 0 0 1 .5.5V7h4.5a.5.5 0 0 1 0 1H8.5v4.5a.5.5 0 0 1-1 0V8H3a.5.5 0 0 1 0-1h4.5V2.5A.5.5 0 0 1 8 2"/></svg>
      </span>
      Nuevo
    </a>
    <a class="btn btn-md btn-secondary" href="<?= htmlspecialchars($base) ?>/">Inicio</a>
  </div>
</div>

<div class="mx-auto d-block" style="width:70%;">
  <?php if(!empty($_SESSION['flash_ok'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
  <?php endif; ?>
  <?php if(!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="card mb-3 filter-card">
    <div class="card-body">
      <form class="row g-3" method="get" action="<?= htmlspecialchars($base) ?>/usuarios">
        <div class="col-md-3">
          <label class="form-label">RUT</label>
          <input type="text" name="rut" class="form-control" value="<?= htmlspecialchars($q_rut) ?>" placeholder="11111111-1">
        </div>
        <div class="col-md-3">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($q_nombre) ?>" placeholder="Nombre">
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="text" name="email" class="form-control" value="<?= htmlspecialchars($q_email) ?>" placeholder="correo@dominio.cl">
        </div>
        <div class="col-md-3">
          <label class="form-label">Perfil</label>
          <select name="perfil" class="form-select">
            <option value="">— Perfil —</option>
            <?php foreach ($perfiles as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>" <?= ($q_perfil === $p ? 'selected' : '') ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label d-block">Estado</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="act1" name="act[]" value="1" <?= $showActivo ? 'checked' : '' ?>>
            <label class="form-check-label" for="act1">Activo</label>
          </div>
          <div class="form-check form-check-inline ms-3">
            <input class="form-check-input" type="checkbox" id="act0" name="act[]" value="0" <?= $showInactivo ? 'checked' : '' ?>>
            <label class="form-check-label" for="act0">Inactivo</label>
          </div>
        </div>

        <div class="col-md-3 filter-actions">
          <button class="btn btn-success" type="submit">
            <svg class="svg-ico me-1" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.867-3.834zM12 6.5a5.5 5.5 0 1 1-11 0a5.5 5.5 0 0 1 11 0"/></svg>
            Filtrar
          </button>
          <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base) ?>/usuarios">Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:120px">RUT</th>
              <th>Nombre</th>
              <th style="width:220px">Email</th>
              <th style="width:130px">Fono</th>
              <th style="width:90px">Perfil</th>
              <th style="width:90px">Estado</th>
              <th style="width:160px" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-muted">Sin resultados.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <?php
                  $id     = (int)($r['id'] ?? 0);
                  $rut    = (string)($r['rut'] ?? '');
                  $nombre = (string)($r['nombre'] ?? '');
                  $email  = (string)($r['email'] ?? '');
                  $fono   = (string)($r['fono'] ?? '');
                  $perfil = (string)($r['perfil'] ?? 'USR');
                  $activo = (int)($r['activo'] ?? 1);

                  // Si el controller ya filtró, perfecto. Si no, podemos aplicar un filtrito visual opcional:
                  $passes = true;
                  if ($q_rut    !== '' && stripos($rut, $q_rut) === false)       $passes = false;
                  if ($q_nombre !== '' && stripos($nombre, $q_nombre) === false) $passes = false;
                  if ($q_email  !== '' && stripos($email, $q_email) === false)   $passes = false;
                  if ($q_perfil !== '' && $perfil !== $q_perfil)                 $passes = false;
                  if (!empty($q_act)) {
                    if (!in_array((string)$activo, (array)$q_act, true))         $passes = false;
                  }
                  if (!$passes) continue;
                ?>
                <tr>
                  <td><?= htmlspecialchars($rut) ?></td>
                  <td><?= htmlspecialchars($nombre) ?></td>
                  <td><?= htmlspecialchars($email ?: '—') ?></td>
                  <td><?= htmlspecialchars($fono  ?: '—') ?></td>
                  <td><span class="badge text-bg-light border"><?= htmlspecialchars($perfil) ?></span></td>
                  <td>
                    <?php if ($activo): ?>
                      <span class="badge text-bg-success">Activo</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Inactivo</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a class="btn btn-outline-secondary btn-icon" href="<?= htmlspecialchars($base) ?>/usuarios/edit/<?= $id ?>" title="Editar">
                        <span class="svg-ico" aria-hidden="true">
                          <svg viewBox="0 0 16 16" fill="currentColor"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-9.5 9.5a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.651-.651l2-5a.5.5 0 0 1 .11-.168l9.5-9.5zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zM10.5 3.207 3 10.707V13h2.293l7.5-7.5-2.293-2.293z"/></svg>
                        </span>
                      </a>
                      <a class="btn btn-outline-danger btn-icon"
                         href="<?= htmlspecialchars($base) ?>/usuarios/delete/<?= $id ?>"
                         onclick="return confirm('¿Eliminar usuario <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>?');"
                         title="Eliminar">
                        <span class="svg-ico" aria-hidden="true">
                          <svg viewBox="0 0 16 16" fill="currentColor"><path d="M5.5 5.5A.5.5 0 0 1 6 6v7a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0A.5.5 0 0 1 8.5 6v7a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v7a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1h2.5a1 1 0 0 1 1 1M4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4z"/></svg>
                        </span>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
