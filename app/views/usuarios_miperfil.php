<?php
// /app/views/usuarios_miperfil.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) { header('Location: /index.php'); exit; }
//require_once __DIR__ . '/layout/header.php';

$base = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$row  = $row ?? [];
$csrf = $_SESSION['csrf'] ?? '';

$fecnacVal = '';
if (!empty($row['fecnac']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$row['fecnac'])) {
  $fecnacVal = $row['fecnac'];
}
?>
<div class="container py-3">
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">Mi perfil</h4>
    <a class="btn btn-outline-secondary ms-auto" href="<?= htmlspecialchars($base) ?>/">Inicio</a>
  </div>

  <?php if(!empty($_SESSION['flash_ok'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
  <?php endif; ?>
  <?php if(!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>

  <form method="post"
        action="<?= htmlspecialchars($base) ?>/usuarios/miperfilUpdate"
        enctype="multipart/form-data"
        class="row g-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="col-md-3">
      <label class="form-label">RUT</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars((string)($row['rut'] ?? '')) ?>" readonly>
    </div>
    <div class="col-md-5">
      <label class="form-label">Nombre</label>
      <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars((string)($row['nombre'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)($row['email'] ?? '')) ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">Fono</label>
      <input type="text" name="fono" class="form-control" maxlength="15" value="<?= htmlspecialchars((string)($row['fono'] ?? '')) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Fecha Nac.</label>
      <input type="date" name="fecnac" class="form-control" value="<?= htmlspecialchars($fecnacVal) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Nueva contraseña (opcional)</label>
      <div class="input-group">
        <input type="password" name="password" id="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
        <button type="button" class="btn btn-outline-secondary" id="togglePass" tabindex="-1">👁</button>
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Confirmar contraseña</label>
      <div class="input-group">
        <input type="password" name="password2" id="password2" class="form-control">
        <button type="button" class="btn btn-outline-secondary" id="togglePass2" tabindex="-1">👁</button>
      </div>
    </div>

    <div class="col-md-4">
      <label class="form-label d-block">Foto</label>
      <?php if (!empty($row['foto'])): ?>
        <img src="<?= $base ?>/public/images/usuarios/<?=
             htmlspecialchars($row['foto']) ?>" class="rounded border mb-2" style="height:240px" alt="foto">
      <?php else: ?>
        <div class="text-muted mb-2">Sin foto actual</div>
      <?php endif; ?>
      <input type="file" name="foto" accept=".jpg,.jpeg,.png" class="form-control">
      <div class="form-text">JPG/PNG máx 2MB.</div>
    </div>

    <div class="col-12 text-end">
      <button class="btn btn-primary" id="btnSubmit">Actualizar</button>
    </div>
  </form>
</div>

<script>
(function(){
  function toggle(id, btnId){
    const i = document.getElementById(id);
    const b = document.getElementById(btnId);
    if (!i || !b) return;
    b.addEventListener('click', ()=>{ i.type = (i.type === 'password' ? 'text' : 'password'); i.focus(); });
  }
  toggle('password','togglePass');
  toggle('password2','togglePass2');

  // chequear confirmación antes de enviar (si se ingresó nueva clave)
  const btn = document.getElementById('btnSubmit');
  btn?.addEventListener('click', function(ev){
    const p1 = document.getElementById('password')?.value || '';
    const p2 = document.getElementById('password2')?.value || '';
    if (p1 !== '' && p1 !== p2) {
      ev.preventDefault();
      alert('La confirmación de contraseña no coincide.');
    }
  });
})();
</script>

<?php //require_once __DIR__ . '/layout/footer.php'; ?>
