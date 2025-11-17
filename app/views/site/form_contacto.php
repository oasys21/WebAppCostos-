<?php /* app/views/site/form_contacto.php */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = !empty($GLOBALS['cfg']['BASE_URL']) ? rtrim($GLOBALS['cfg']['BASE_URL'], '/') : '';
$ok   = isset($_GET['ok']);
$err  = isset($_GET['error']);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrf = $_SESSION['csrf_contact'] ?? '';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h2 class="mb-3">Contacto</h2>
      <p class="text-muted">
        <strong>Para contactarnos, envíe un correo, un mensaje whatsap, o use el fomulario de contacto</strong>
      </p>

      <?php if ($ok): ?>
        <div class="alert alert-success">Mensaje enviado correctamente. ¡Gracias por contactarnos!</div>
      <?php elseif ($err): ?>
        <div class="alert alert-danger">No se pudo enviar el correo en este momento. Intente nuevamente.</div>
      <?php else: ?>
        <div class="alert d-none" id="msgBox"></div>
      <?php endif; ?>

      <form id="contactoForm" class="needs-validation" action="<?=$h($base)?>/site/contacto_enviar" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?=$h($csrf)?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required minlength="2" maxlength="120">
            <div class="invalid-feedback">Ingrese su nombre.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required maxlength="160">
            <div class="invalid-feedback">Ingrese un email válido.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Fono</label>
            <input type="text" name="fono" class="form-control" required minlength="6" maxlength="40">
            <div class="invalid-feedback">Ingrese un teléfono válido.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ciudad</label>
            <input type="text" name="ciudad" class="form-control" required maxlength="100">
            <div class="invalid-feedback">Ingrese su ciudad.</div>
          </div>
          <div class="col-12">
            <label class="form-label">Mensaje</label>
            <textarea name="mensaje" class="form-control" rows="5" required minlength="5" maxlength="4000"></textarea>
            <div class="invalid-feedback">Escriba su mensaje.</div>
          </div>
        </div>

        <div class="d-flex gap-2 align-items-center mt-3 flex-wrap">
          <button type="submit" class="btn btn-primary">Enviar</button>
          <a class="btn btn-secondary" href="mailto:admin@rhglobal.cl">Enviar correo directo</a>
          <a class="btn btn-success" target="_blank" rel="noopener" href="https://wa.me/56988283756">WhatsApp</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Validación HTML5 + envío progresivo con fetch (si está disponible)
(function(){
  const f = document.getElementById('contactoForm');
  if (!f) return;

  f.addEventListener('submit', function(ev){
    if (!f.checkValidity()) {
      ev.preventDefault(); ev.stopPropagation();
      f.classList.add('was-validated');
      return;
    }

    if (window.fetch) {
      ev.preventDefault(); ev.stopPropagation();
      const fd = new FormData(f);
      fetch(f.action, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
        body: fd
      })
      .then(r => r.json())
      .then(data => {
        const box = document.getElementById('msgBox');
        if (!box) return;
        box.classList.remove('d-none','alert-success','alert-danger');
        if (data.ok) {
          box.classList.add('alert-success');
          box.textContent = data.msg || 'Mensaje enviado. ¡Gracias!';
          f.reset(); f.classList.remove('was-validated');
        } else {
          box.classList.add('alert-danger');
          box.textContent = (data && data.msg) ? data.msg : 'No se pudo enviar.';
        }
      })
      .catch(() => {
        const box = document.getElementById('msgBox');
        if (!box) return;
        box.classList.remove('d-none'); box.classList.add('alert-danger');
        box.textContent = 'Error de red. Intente nuevamente.';
      });
    }
  }, false);
})();
</script>
