<?php
// /costos/app/views/auth_login.php
$base = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$recaptcha = !empty($this->cfg['RECAPTCHA_ENABLED']);
$sitekey   = (string)($this->cfg['RECAPTCHA_SITE_KEY'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Si en esta vista no incluyes el header global, al menos carga el CSS de Bootstrap -->
  <link rel="stylesheet" href="<?= $base ?>/public/vendor/bootstrap/css/bootstrap.min.css">
  <style>
    body{
      padding-top:4.5rem;
      background-image:url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg);
      background-color:transparent;
      background-repeat:repeat;
    }
  </style>

  <script>
// --- Helpers (definidos ANTES de ser usados) ---
function showToastInline(id, bodyId, msg, type, delayMs) {
  var t = document.getElementById(id), b = document.getElementById(bodyId);
  if (!t || !b) return;
  t.className = 'toast align-items-center text-bg-' + (type || 'danger') +
                ' border-0 position-absolute top-0 start-50 translate-middle-x';
  b.textContent = msg || 'Mensaje';
  if (delayMs) t.setAttribute('data-bs-delay', String(delayMs));

  try {
    if (window.bootstrap && typeof bootstrap.Toast === 'function') {
      new bootstrap.Toast(t).show();
    } else {
      // Fallback si NO está bootstrap.js
      t.style.display = 'block';
      t.classList.add('show');
      setTimeout(function(){ t.classList.remove('show'); t.style.display = 'none'; }, delayMs || 2000);
    }
  } catch(e) {
    t.style.display = 'block';
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); t.style.display = 'none'; }, delayMs || 2000);
  }
}

function cleanRut(r){ return (r||'').replace(/[^0-9kK-]/g,'').toUpperCase(); }
function validaRUT(rutCompleto){
  if(!rutCompleto) return false;
  let rut = rutCompleto.replace(/\./g,'').replace(/-/g,'').toUpperCase();
  if(rut.length < 2) return false;
  let cuerpo = rut.slice(0,-1), dv = rut.slice(-1);
  let suma=0, multiplo=2;
  for(let i=cuerpo.length-1;i>=0;i--){
    suma += multiplo * parseInt(cuerpo.charAt(i),10);
    multiplo = (multiplo === 7) ? 2 : (multiplo + 1);
  }
  let dvEsperado = 11 - (suma % 11);
  dvEsperado = (dvEsperado === 11) ? '0' : (dvEsperado === 10 ? 'K' : String(dvEsperado));
  return dv === dvEsperado;
}
  </script>
</head>
<body>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4 position-relative">

        <!-- Slot fijo para toast inline (no mueve el formulario) -->
        <div id="loginToastSlot" class="position-relative mb-2" style="height:56px;">
          <div id="loginToast" class="toast align-items-center text-bg-danger border-0 position-absolute top-0 start-50 translate-middle-x"
               role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="1700" style="min-width: 280px;">
            <div class="d-flex">
              <div id="loginToastBody" class="toast-body">Mensaje</div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
          </div>
        </div>

        <h1 class="h4 mb-3">Iniciar sesión</h1>

        <form method="post" action="<?= $base ?>/auth/dologin" autocomplete="off" id="loginForm">
          <div class="mb-3">
            <label class="form-label">RUT (sin puntos, con o sin guion)</label>
            <input type="text" class="form-control" name="rut" id="rut" required maxlength="12" placeholder="11111111-1" autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <div class="input-group">
              <input type="password" class="form-control" name="password" id="password" required>
              <button type="button" class="btn btn-secondary" id="togglePass" aria-pressed="false" title="Mostrar/Ocultar">???</button>
            </div>
          </div>

          <?php if($recaptcha): ?>
          <div class="mb-3">
            <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($sitekey) ?>"></div>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
          </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary w-100" id="btnLogin">Entrar</button>
        </form>

        <?php if (isset($_GET['e'])): ?>
        <!-- Al cargar el DOM mostramos el toast -->
        <script>
          window.addEventListener('DOMContentLoaded', function () {
            var e = '<?= htmlspecialchars($_GET['e'], ENT_QUOTES, 'UTF-8') ?>';
            var map = {
              rut:     'RUT inválido',
              nouser:  'No existe usuario',
              pass:    'Contraseña incorrecta',
              blocked: 'Demasiados intentos. Intente más tarde.',
              captcha: 'Complete el reCAPTCHA'
            };
            var msg  = map[e] || 'Error';
            var tone = (e === 'pass' || e === 'blocked' || e === 'rut') ? 'danger' : 'warning';
            showToastInline('loginToast','loginToastBody', msg, tone, 2200);
          });
        </script>

        <!-- (Opcional) mensaje visible también sin JS -->
        <?php if ($_GET['e'] === 'pass'): ?>
          <div class="alert alert-danger mt-2">Contraseña incorrecta.</div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if(isset($_GET['bye'])): ?>
          <script>
            window.addEventListener('DOMContentLoaded', function () {
              showToastInline('loginToast','loginToastBody','Sesión finalizada','success',1500);
            });
          </script>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<script>
// Toggle password
(function(){
  var btn = document.getElementById('togglePass');
  var pwd = document.getElementById('password');
  if (btn && pwd) {
    btn.addEventListener('click', function(){
      var isPwd = (pwd.getAttribute('type') === 'password');
      pwd.setAttribute('type', isPwd ? 'text' : 'password');
      btn.setAttribute('aria-pressed', isPwd ? 'true' : 'false');
      pwd.focus();
    });
  }
})();

// Validación + verificación de existencia del RUT
(function(){
  var rut = document.getElementById('rut');
  if (!rut) return;
  rut.addEventListener('change', function(){
    var val = cleanRut(rut.value);
    if (val && !/-/.test(val) && val.length >= 2) {
      val = val.slice(0, -1) + '-' + val.slice(-1);
    }
    rut.value = val;

    if(val.length < 2){ return; }

    if(!validaRUT(val)){
      showToastInline('loginToast','loginToastBody','RUT inválido','danger',1700);
      rut.value = '';
      rut.focus();
      return;
    }
	var url = '<?= $base ?>/auth/checkrut?rut=' + encodeURIComponent(val);
    
    fetch(url, {headers:{'Accept':'application/json'}})
      .then(function(r){
        if (!r.ok) throw new Error('HTTP '+r.status);
        var ct = (r.headers.get('content-type')||'').toLowerCase();
        if (!ct.includes('application/json')) throw new Error('Respuesta no JSON');
        return r.json();
      })
      .then(function(res){
        if(!res || !res.exists){
          showToastInline('loginToast','loginToastBody','No existe usuario','warning',1700);
          rut.value = '';
          rut.focus();
        }
      })
      .catch(function(err){
        const msg = (String(err.message||'').includes('404')
          ? 'Ruta /auth/checkrut no encontrada (404)'
          : 'Error de red');
        showToastInline('loginToast','loginToastBody', msg, 'danger', 1700);
      });
  });
})();
</script>

<!-- Si esta vista no usa el header global, carga bootstrap.bundle aquí (opcional).
     Si no está presente, el fallback del toast igual funciona. -->
<script src="<?= $base ?>/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
