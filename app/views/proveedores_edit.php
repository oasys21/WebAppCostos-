<?php
$__base = rtrim((string)($base ?? ''), '/');
if (class_exists('Session') && !Session::user()) { header('Location: ' . $__base . '/'); exit; }
if (!function_exists('u')) { function u($base,$path){ $url=rtrim($base??'','/').'/'.ltrim($path??'','/'); return preg_replace('~(?<!:)//+~','/',$url);} }
$row = $row ?? [];
$csrf = (class_exists('Session') && method_exists('Session','csrf')) ? Session::csrf() : '';
?>
<div class="container py-3">
  <h1 class="h5 mb-3">Editar Proveedor</h1>

  <form method="post" action="<?= u($__base,'index.php?r=proveedores/update/'.(int)$row['id']) ?>" class="row g-3" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
    <div class="col-md-6">
      <label class="form-label" for="nombre">Nombre</label>
      <input class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars((string)$row['nombre']) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rut">RUT</label>
      <input class="form-control" id="rut" name="rut" value="<?= htmlspecialchars((string)($row['rut'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rubro">Rubro</label>
      <input class="form-control" id="rubro" name="rubro" value="<?= htmlspecialchars((string)($row['rubro'] ?? '')) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label" for="razon">Razón Social</label>
      <input class="form-control" id="razon" name="razon" value="<?= htmlspecialchars((string)($row['razon'] ?? '')) ?>">
    </div>
    <div class="col-md-8">
      <label class="form-label" for="direccion">Dirección</label>
      <input class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars((string)($row['direccion'] ?? '')) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label" for="comuna">Comuna</label>
      <input class="form-control" id="comuna" name="comuna" value="<?= htmlspecialchars((string)($row['comuna'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="ciudad">Ciudad</label>
      <input class="form-control" id="ciudad" name="ciudad" value="<?= htmlspecialchars((string)($row['ciudad'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label d-block">Activo</label>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= ((int)($row['activo'] ?? 1)===1?'checked':'') ?>>
        <label class="form-check-label" for="activo">Sí</label>
      </div>
    </div>

    <div class="col-12"><hr></div>

    <div class="col-md-4">
      <label class="form-label" for="con_nom">Contacto (Nombre)</label>
      <input class="form-control" id="con_nom" name="con_nom" value="<?= htmlspecialchars((string)($row['con_nom'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="con_email">Contacto (Email)</label>
      <input class="form-control" id="con_email" name="con_email" type="email" value="<?= htmlspecialchars((string)($row['con_email'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="con_fono">Contacto (Fono)</label>
      <input class="form-control" id="con_fono" name="con_fono" value="<?= htmlspecialchars((string)($row['con_fono'] ?? '')) ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label" for="rl_rut">Rep. Legal RUT</label>
      <input class="form-control" id="rl_rut" name="rl_rut" value="<?= htmlspecialchars((string)($row['rl_rut'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rl_nom">Rep. Legal Nombre</label>
      <input class="form-control" id="rl_nom" name="rl_nom" value="<?= htmlspecialchars((string)($row['rl_nom'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rl_email">Rep. Legal Email</label>
      <input class="form-control" id="rl_email" name="rl_email" type="email" value="<?= htmlspecialchars((string)($row['rl_email'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rl_fono">Rep. Legal Fono</label>
      <input class="form-control" id="rl_fono" name="rl_fono" value="<?= htmlspecialchars((string)($row['rl_fono'] ?? '')) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label" for="ep_nom">Enc. Pago Nombre</label>
      <input class="form-control" id="ep_nom" name="ep_nom" value="<?= htmlspecialchars((string)($row['ep_nom'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="ep_email">Enc. Pago Email</label>
      <input class="form-control" id="ep_email" name="ep_email" type="email" value="<?= htmlspecialchars((string)($row['ep_email'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="ep_fono">Enc. Pago Fono</label>
      <input class="form-control" id="ep_fono" name="ep_fono" value="<?= htmlspecialchars((string)($row['ep_fono'] ?? '')) ?>">
    </div>

    <div class="col-12">
      <button class="btn btn-primary" type="submit">Guardar cambios</button>
      <a class="btn btn-outline-secondary" href="<?= u($__base,'index.php?r=proveedores/index') ?>">Volver</a>
    </div>
  </form>
</div>

<script>
document.getElementById('rut').addEventListener('blur', function() {
  if (typeof validaRUT === 'function') {
    const ok = validaRUT(this.value);
    this.classList.toggle('is-invalid', !ok && this.value.trim()!=='');
  }
});
</script>
