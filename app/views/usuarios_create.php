<?php
// /costos/app/views/usuarios_create.php
declare(strict_types=1);

$base = rtrim((string)($this->cfg['BASE_URL'] ?? '/'), '/');

/**
 * Mapa de subperfil (30 bits) en bloques de 3 por módulo: CRE/EDT/DEL
 * Debe coincidir con usuarios_edit.php
 */
$groups = [
  ['code'=>'USR','name'=>'Usuarios',        'start'=>1],
  ['code'=>'PRO','name'=>'Proveedores',     'start'=>4],
  ['code'=>'ADQ','name'=>'Adquisiciones',   'start'=>7],
  ['code'=>'CLI','name'=>'Clientes',        'start'=>10],
  ['code'=>'PROY','name'=>'Proyectos',      'start'=>13],
  ['code'=>'CAT','name'=>'Catálogo',        'start'=>16],
  ['code'=>'AVN','name'=>'Avances',         'start'=>19],
  ['code'=>'ESP','name'=>'Estados Pago',    'start'=>22],
  ['code'=>'DOX','name'=>'Documentos',      'start'=>25],
  ['code'=>'PRE','name'=>'Presupuestos',    'start'=>28],
];
// Divide en dos bloques: 5 módulos (15 permisos) por lado
$leftGroups  = array_slice($groups, 0, 5); // 1..15
$rightGroups = array_slice($groups, 5);    // 16..30

// $csrf viene del controller -> $this->csrfEnsure()
$csrf = isset($csrf) ? (string)$csrf : '';
?>
<style>
  body{padding-top:4.5rem; background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent; background-repeat: repeat;}
  .btn-icon { padding: 0; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
</style>

<div class="mx-auto d-block d-flex justify-content-between align-items-center mb-3" style="width:70%;">
  <h2 class="h4 mb-0"><?= htmlspecialchars($pageTitle ?? 'Crear Usuario', ENT_QUOTES, 'UTF-8') ?></h2>
  <div class="mt-4">
    <a class="btn btn-md btn-primary" href="<?= $base ?>/usuarios/index">Volver</a>
  </div>
</div>

<div class="mx-auto d-block" style="width:70%;">
  <div class="card-body">
    <form method="post" action="<?= $base ?>/usuarios/store" autocomplete="off" id="formCreateUser" novalidate>

      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label" for="rut">RUT</label>
          <input type="text" name="rut" id="rut" class="form-control" required maxlength="12" placeholder="11111111-1">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="email">Email</label>
          <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="nombre">Nombre completo</label>
          <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="120">
        </div>
      </div>

      <div class="row g-4 mt-0">
        <div class="col-md-2">
          <label class="form-label" for="perfil">Perfil</label>
          <select name="perfil" id="perfil" class="form-select" required>
            <option value="ADM">ADM</option>
            <option value="CON">CON</option>
            <option value="BOD">BOD</option>
            <option value="OPE">OPE</option>
            <option value="USR" selected>USR</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" checked>
            <label class="form-check-label" for="activo">Activo</label>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label" for="fono">Fono</label>
          <input type="text" name="fono" id="fono" class="form-control" maxlength="15" placeholder="Ej: +56912345678">
        </div>

        <div class="col-md-4">
          <label class="form-label" for="fecnac">Fecha Nac.</label>
          <input type="date" name="fecnac" id="fecnac" class="form-control">
        </div>
      </div>

      <div class="row g-4 mt-0">
        <div class="col-md-6">
          <label class="form-label" for="password">Contraseña</label>
          <div class="input-group">
            <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password">
            <button type="button" class="btn btn-secondary" id="togglePass1" tabindex="-1" aria-label="Mostrar/ocultar contraseña">👁</button>
          </div>
          <div class="form-text">Mín. 8, Mayúscula, Minúscula, Número y Símbolo (#%&*!).</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="password2">Confirmar contraseña</label>
          <div class="input-group">
            <input type="password" name="password2" id="password2" class="form-control" required autocomplete="new-password">
            <button type="button" class="btn btn-secondary" id="togglePass2" tabindex="-1" aria-label="Mostrar/ocultar confirmación">👁</button>
          </div>
        </div>
      </div>

      <hr class="my-4">
      <h5 class="mb-3">Subperfil (30 permisos: CRE/EDT/DEL por módulo)</h5>

      <div class="row g-3">
        <!-- Bloque izquierdo (1..15) -->
        <div class="col-lg-6">
          <div class="border rounded-3 p-3">
            <div class="fw-semibold mb-2">Permisos 1–15 __________________ Creación______Edición______Borrado</div>
            <?php foreach ($leftGroups as $g): $p=(int)$g['start']; ?>
              <div class="row g-2 align-items-center mb-1">
                <div class="col-5 small fw-semibold">
                  <?= htmlspecialchars($g['name']) ?> <span class="fw-semibold">[<?= htmlspecialchars($g['code']) ?>]</span>
                </div>
                <div class="col-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="sp<?= $p ?>" name="subp[]" value="<?= $p ?>">
                    <label class="form-check-label" for="sp<?= $p ?>">(<?= $p ?>)</label>
                  </div>
                </div>
                <div class="col-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="sp<?= $p+1 ?>" name="subp[]" value="<?= $p+1 ?>">
                    <label class="form-check-label" for="sp<?= $p+1 ?>">(<?= $p+1 ?>)</label>
                  </div>
                </div>
                <div class="col-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="sp<?= $p+2 ?>" name="subp[]" value="<?= $p+2 ?>">
                    <label class="form-check-label" for="sp<?= $p+2 ?>">(<?= $p+2 ?>)</label>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Bloque derecho (16..30) -->
        <div class="col-lg-6">
          <div class="border rounded-3 p-3">
            <div class="fw-semibold mb-2">Permisos 16–30 ________________ Creación______Edición______Borrado</div>
            <?php foreach ($rightGroups as $g): $p=(int)$g['start']; ?>
              <div class="row g-2 align-items-center mb-1">
                <div class="col-5 small fw-semibold">
                  <?= htmlspecialchars($g['name']) ?> <span class="fw-semibold">[<?= htmlspecialchars($g['code']) ?>]</span>
                </div>
                <div class="col-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="sp<?= $p ?>" name="subp[]" value="<?= $p ?>">
                    <label class="form-check-label" for="sp<?= $p ?>">(<?= $p ?>)</label>
                  </div>
                </div>
                <div class="col-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="sp<?= $p+1 ?>" name="subp[]" value="<?= $p+1 ?>">
                    <label class="form-check-label" for="sp<?= $p+1 ?>">(<?= $p+1 ?>)</label>
                  </div>
                </div>
                <div class="col-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="sp<?= $p+2 ?>" name="subp[]" value="<?= $p+2 ?>">
                    <label class="form-check-label" for="sp<?= $p+2 ?>">(<?= $p+2 ?>)</label>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-success" type="submit">Crear</button>
        <a href="<?= $base ?>/usuarios/index" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  /* ====== Toast helper ====== */
  if (typeof window.showToast !== 'function') {
    window.showToast = function(msg, type) {
      try {
        const id = 'toastGlobal';
        let el = document.getElementById(id);
        if (!el) {
          const slot = document.createElement('div');
          slot.style.position='fixed'; slot.style.top='10px'; slot.style.right='10px'; slot.style.zIndex=1080;
          slot.innerHTML =
            '<div id="'+id+'" class="toast text-bg-'+(type||'danger')+' border-0" role="alert" data-bs-delay="1700">' +
            ' <div class="d-flex"><div class="toast-body"></div>' +
            ' <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
          document.body.appendChild(slot);
          el = document.getElementById(id);
        }
        el.className = 'toast text-bg-' + (type||'danger') + ' border-0';
        el.querySelector('.toast-body').textContent = msg || 'Mensaje';
        if (window.bootstrap && bootstrap.Toast) { new bootstrap.Toast(el).show(); } else { alert(msg || 'Mensaje'); }
      } catch(e) { alert(msg || 'Mensaje'); }
    };
  }

  /* ====== Mensajes de URL (ok / e) ====== */
  (function(){
    const params = new URLSearchParams(window.location.search);
    const e  = params.get('e') || '';
    const ok = params.get('ok') || '';
    const mapE = {
      rut:'RUT inválido',
      email:'Email inválido',
      nombre:'Nombre requerido',
      pass:'Contraseña no cumple o no coincide',
      rutdup:'RUT ya existe',
      emaildup:'Email ya existe'
    };
    if (e) showToast(mapE[e] || 'Error', 'danger');
    if (ok === '1') showToast('Usuario creado', 'success');
  })();

  /* ====== Utilidades RUT ====== */
  function cleanRut(r){ return (r||'').toString().replace(/[^0-9kK]/g,'').toUpperCase(); }
  function validarRut(rut) {
    rut = (rut||'').toString().toUpperCase().replace(/\./g,'').replace(/-/g,'');
    if (!rut || rut.length < 2) return false;
    const dv = rut.slice(-1), cuerpo = rut.slice(0,-1);
    if (!/^\d+$/.test(cuerpo)) return false;
    let suma=0, mul=2;
    for (let i=cuerpo.length-1; i>=0; i--) {
      suma += mul * parseInt(cuerpo[i],10);
      mul = (mul===7) ? 2 : (mul+1);
    }
    const res = 11 - (suma % 11);
    const dvEsperado = (res===11) ? '0' : (res===10 ? 'K' : String(res));
    return dv === dvEsperado;
  }

  /* ====== Mostrar/Ocultar contraseña ====== */
  function bindToggle(btnId, inputId){
    const btn = document.getElementById(btnId);
    const inp = document.getElementById(inputId);
    if (!btn || !inp) return;
    btn.addEventListener('click', function(){
      inp.type = (inp.type === 'password') ? 'text' : 'password';
      this.textContent = (inp.type === 'password') ? '👁' : '🙈';
    });
  }
  bindToggle('togglePass1','password');
  bindToggle('togglePass2','password2');

  /* ====== Reglas de password y submit ====== */
  function passFuerte(p){
    if (!p || p.length < 8) return false;
    const hasMin = /[a-z]/.test(p);
    const hasMay = /[A-Z]/.test(p);
    const hasNum = /\d/.test(p);
    const hasSym = /[#%&*!]/.test(p);
    return hasMin && hasMay && hasNum && hasSym;
  }

  const $rut    = document.getElementById('rut');
  const $email  = document.getElementById('email');
  const $pass1  = document.getElementById('password');
  const $pass2  = document.getElementById('password2');

  $rut.addEventListener('change', function(){
    const ok = validarRut(this.value);
    this.classList.toggle('is-invalid', !ok);
    if (!ok) showToast('RUT inválido', 'danger');
  });
  $email.addEventListener('change', function(){
    const emailOk = /^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(this.value.trim());
    this.classList.toggle('is-invalid', !emailOk);
    if (!emailOk) showToast('Email inválido', 'danger');
  });

  document.getElementById('formCreateUser').addEventListener('submit', function(ev){
    const rut    = $rut.value.trim();
    const email  = $email.value.trim();
    const pass1  = $pass1.value || '';
    const pass2  = $pass2.value || '';

    if (!validarRut(rut)) { ev.preventDefault(); showToast('RUT inválido','danger'); $rut.focus(); return false; }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(email)) { ev.preventDefault(); showToast('Email inválido','danger'); $email.focus(); return false; }
    if (!passFuerte(pass1)) { ev.preventDefault(); showToast('Contraseña no cumple requisitos','danger'); $pass1.focus(); return false; }
    if (pass1 !== pass2) { ev.preventDefault(); showToast('Las contraseñas no coinciden','danger'); $pass2.focus(); return false; }
    return true;
  });
</script>
