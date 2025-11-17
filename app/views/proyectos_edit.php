<?php
declare(strict_types=1);

/** Base & helpers **/
$base = rtrim((string)($base ?? ''), '/');
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Datos normalizados (acepta alias p / proyecto) **/
$p = (isset($p) && is_array($p)) ? $p : ((isset($proyecto) && is_array($proyecto)) ? $proyecto : []);
$miembros     = (isset($miembros) && is_array($miembros)) ? $miembros : [];
$usuarios     = (isset($usuarios) && is_array($usuarios)) ? $usuarios : [];
$canManageACL = !empty($canManageACL);
$isADM        = !empty($isADM);

/** Campos frecuentes **/
$id            = (int)   ($p['id'] ?? 0);
$nombre        = (string)($p['nombre'] ?? '');
$codigo_proy   = (string)($p['codigo_proy'] ?? '');
$ownerId       = (int)   ($p['owner_user_id'] ?? 0);
$descripcion   = (string)($p['descripcion'] ?? '');
$activo        = (int)   ($p['activo'] ?? 1);
$rut_cliente   = (string)($p['rut_cliente'] ?? '');
$fecha_inicio  = (string)($p['fecha_inicio'] ?? '');
$fecha_termino = (string)($p['fecha_termino'] ?? '');

/** Helper de etiqueta de usuario (robusto) **/
if (!function_exists('_uLabel')) {
  function _uLabel(array $r): string {
    $id = $r['id'] ?? $r['user_id'] ?? '';
    $name = $r['nameuser'] ?? $r['nombre'] ?? $r['username'] ?? $r['user'] ?? $r['email'] ?? $r['idRUT'] ?? '';
    if ($name === '' && $id !== '') $name = 'Usuario '.$id;
    return trim(($id !== '' ? ($id.' - ') : '').$name);
  }
}
?>
 <style>
 <!--
body{padding-top:4.5rem; 	
background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); 
background-color: transparent;	background-repeat: repeat;}	
-->
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 m-0">Proyecto #<?= $id ?> · <?= h($nombre) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/index.php?r=proyectos/index">Volver</a>
  </div>

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Cambios guardados.</div>
  <?php elseif (isset($_GET['err'])): ?>
    <div class="alert alert-danger"><?= h((string)$_GET['err']) ?></div>
  <?php endif; ?>

  <!-- ===== Form Edición ===== -->
  <div class="mb-4">
    <div class="card-body">
      <form method="post" action="<?= $base ?>/index.php?r=proyectos/update/<?= $id ?>" class="row g-3" id="formEdit" autocomplete="off">
        <div class="col-md-6">
          <label class="form-label">Nombre <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" required maxlength="160" value="<?= h($nombre) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label" for="codigo_proy">Código del Proyecto</label>
          <input type="text" class="form-control" id="codigo_proy" name="codigo_proy" required maxlength="50" value="<?= h($codigo_proy) ?>" placeholder="PR-UNO045">
          <div class="form-text">Usa letras, números y guiones. Ej: PR-UNO045</div>
        </div>

        <!-- Select simple de cliente -->
        <div class="col-md-6">
          <label class="form-label">Cliente</label>
          <select class="form-select" name="rut_cliente" id="rut_cliente" required>
            <option value="">— Seleccione cliente —</option>
            <?php if (!empty($clientes) && is_array($clientes)): ?>
              <?php foreach ($clientes as $c):
                    $rut = (string)($c['rut'] ?? '');
                    $nom = (string)($c['nombre'] ?? '');
                    $sel = ($rut === $rut_cliente) ? 'selected' : ''; ?>
                <option value="<?= h($rut) ?>" <?= $sel ?>><?= h($nom.' — '.$rut) ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <?php if ($rut_cliente !== ''): ?>
                <option value="<?= h($rut_cliente) ?>" selected><?= h($rut_cliente) ?> (actual)</option>
              <?php endif; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Fecha inicio</label>
          <input type="date" name="fecha_inicio" class="form-control" value="<?= h($fecha_inicio) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha término</label>
          <input type="date" name="fecha_termino" class="form-control" value="<?= h($fecha_termino) ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Descripción</label>
          <textarea class="form-control" name="descripcion" rows="3"><?= h($descripcion) ?></textarea>
        </div>

        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <select name="activo" class="form-select">
            <option value="1" <?= $activo===1?'selected':'' ?>>Activo</option>
            <option value="0" <?= $activo===0?'selected':'' ?>>Inactivo</option>
          </select>
        </div>

        <div class="col-12 mt-3">
          <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
          <a class="btn btn-outline-secondary" href="<?= $base ?>/index.php?r=proyectos/index">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <?php if ($canManageACL): ?>
  <!-- ===== Control de Acceso ===== -->
  <div class="">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Control de acceso del proyecto</strong>
      <span class="badge text-bg-secondary">Sólo dueño o ADM</span>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <!-- Dueño -->
        <div class="col-md-6">
          <form method="post" action="<?= $base ?>/index.php?r=proyectos/setowner/<?= $id ?>" class="row g-2">
            <div class="col-12">
              <label class="form-label">Dueño del proyecto</label>
              <select name="owner_user_id" class="form-select" required>
                <option value="">— seleccionar —</option>
                <?php foreach ($usuarios as $u): $uid=(int)($u['id'] ?? 0); ?>
                  <option value="<?= $uid ?>" <?= $uid===$ownerId?'selected':'' ?>><?= h(_uLabel($u)) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">El dueño siempre puede ver/editar y administrar miembros.</div>
            </div>
            <div class="col-12">
              <button class="btn btn-outline-primary btn-sm"><i class="bi bi-person-check"></i> Definir dueño</button>
            </div>
          </form>
        </div>

        <!-- Agregar miembro -->
        <div class="col-md-6">
          <form method="post" action="<?= $base ?>/index.php?r=proyectos/addmember/<?= $id ?>" class="row g-2">
            <div class="col-12">
              <label class="form-label">Agregar miembro autorizado</label>
              <div class="input-group">
                <select name="user_id" class="form-select" id="selUser">
                  <option value="">— seleccionar usuario —</option>
                  <?php foreach ($usuarios as $u): $uid=(int)($u['id'] ?? 0); ?>
                    <option value="<?= $uid ?>"><?= h(_uLabel($u)) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="rol" class="form-select" style="max-width: 150px">
                  <option value="autorizado" selected>autorizado</option>
                  <option value="visor">visor</option>
                </select>
                <button class="btn btn-outline-success"><i class="bi bi-person-plus"></i></button>
              </div>
              <div class="form-text">Los autorizados pueden ver/editar; los visores sólo ver.</div>
            </div>
          </form>
        </div>
      </div>

      <hr>

      <!-- Miembros -->
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th style="width:48px">#</th>
              <th>Usuario</th>
              <th>Rol</th>
              <th style="width:100px">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($miembros): foreach ($miembros as $m): $uid=(int)($m['user_id'] ?? $m['id'] ?? 0); ?>
            <tr>
              <td><?= $uid ?></td>
              <td><?= h(_uLabel($m)) ?></td>
              <td>
                <?php if ($uid === $ownerId): ?>
                  <span class="badge text-bg-primary">OWNER</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary"><?= h(strtoupper((string)($m['rol'] ?? 'EDITOR'))) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($uid !== $ownerId): ?>
                <form method="post" action="<?= $base ?>/index.php?r=proyectos/removemember/<?= $id ?>" onsubmit="return confirm('¿Quitar miembro?')" class="d-inline">
                  <input type="hidden" name="user_id" value="<?= $uid ?>">
                  <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x"></i></button>
                </form>
                <?php else: ?>
                  <span class="text-muted small">dueño</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4" class="text-center text-muted py-3">Sin miembros.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
(function(){
  // Normaliza código de proyecto en cliente
  const ipt = document.getElementById('codigo_proy');
  if (ipt){
    ipt.addEventListener('input', function(){
      let v = this.value.toUpperCase();
      v = v.replace(/\s+/g,'-').replace(/[^A-Z0-9\-_.]/g,'');
      this.value = v;
    });
  }

  // Validación básica
  const form = document.getElementById('formEdit');
  form?.addEventListener('submit', function(ev){
    const nom = form.nombre.value.trim();
    const cod = form.codigo_proy.value.trim();
    const rut = form.rut_cliente.value.trim();
    const fi  = form.fecha_inicio.value;
    const ft  = form.fecha_termino.value;
    if (!nom || !cod || !rut){ ev.preventDefault(); alert('Nombre, Código y Cliente son obligatorios.'); return; }
    if (fi && ft && fi > ft){ ev.preventDefault(); alert('La fecha de término debe ser >= a la de inicio.'); return; }
  });
})();
</script>
