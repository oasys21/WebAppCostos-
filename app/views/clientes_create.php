<?php
$__base = rtrim((string)($base ?? ''), '/');
if (class_exists('Session') && !Session::user()) { header('Location: ' . $__base . '/'); exit; }
if (!function_exists('u')) { function u($base,$path){ $url=rtrim($base??'','/').'/'.ltrim($path??'','/'); return preg_replace('~(?<!:)//+~','/',$url);} }
$csrf = (class_exists('Session') && method_exists('Session','csrf')) ? Session::csrf() : '';
?>
<div class="container py-3">
  <h1 class="h4 mb-3">Nuevo Cliente</h1>

  <form method="post" action="<?= u($__base,'index.php?r=clientes/store') ?>" class="row g-3" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
    <div class="col-md-6">
      <label class="form-label" for="nombre">Nombre</label>
      <input class="form-control" id="nombre" name="nombre" required>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rut">RUT</label>
      <input class="form-control" id="rut" name="rut" placeholder="12.345.678-9">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rubro">Rubro</label>
      <input class="form-control" id="rubro" name="rubro">
    </div>

    <div class="col-md-4">
      <label class="form-label" for="razon">Razón Social</label>
      <input class="form-control" id="razon" name="razon">
    </div>
    <div class="col-md-8">
      <label class="form-label" for="direccion">Dirección</label>
      <input class="form-control" id="direccion" name="direccion">
    </div>

    <div class="col-md-4">
      <label class="form-label" for="comuna">Comuna</label>
      <input class="form-control" id="comuna" name="comuna">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="ciudad">Ciudad</label>
      <input class="form-control" id="ciudad" name="ciudad">
    </div>
    <div class="col-md-4">
      <label class="form-label d-block">Activo</label>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
        <label class="form-check-label" for="activo">Sí</label>
      </div>
    </div>

    <div class="col-12"><hr></div>

    <div class="col-md-4">
      <label class="form-label" for="con_nom">Contacto (Nombre)</label>
      <input class="form-control" id="con_nom" name="con_nom">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="con_email">Contacto (Email)</label>
      <input class="form-control" id="con_email" name="con_email" type="email">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="con_fono">Contacto (Fono)</label>
      <input class="form-control" id="con_fono" name="con_fono">
    </div>

    <div class="col-md-3">
      <label class="form-label" for="rl_rut">Rep. Legal RUT</label>
      <input class="form-control" id="rl_rut" name="rl_rut">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rl_nom">Rep. Legal Nombre</label>
      <input class="form-control" id="rl_nom" name="rl_nom">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rl_email">Rep. Legal Email</label>
      <input class="form-control" id="rl_email" name="rl_email" type="email">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="rl_fono">Rep. Legal Fono</label>
      <input class="form-control" id="rl_fono" name="rl_fono">
    </div>

    <div class="col-md-4">
      <label class="form-label" for="ep_nom">Enc. Pago Nombre</label>
      <input class="form-control" id="ep_nom" name="ep_nom">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="ep_email">Enc. Pago Email</label>
      <input class="form-control" id="ep_email" name="ep_email" type="email">
    </div>
    <div class="col-md-4">
      <label class="form-label" for="ep_fono">Enc. Pago Fono</label>
      <input class="form-control" id="ep_fono" name="ep_fono">
    </div>

    <div class="col-12">
      <button class="btn btn-primary" type="submit">Guardar</button>
      <a class="btn btn-outline-secondary" href="<?= u($__base,'index.php?r=clientes/index') ?>">Cancelar</a>
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
