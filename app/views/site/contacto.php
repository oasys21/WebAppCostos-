<?php /* app/views/site/contacto.php */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = !empty($GLOBALS['cfg']['BASE_URL']) ? rtrim($GLOBALS['cfg']['BASE_URL'], '/') : '';
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<section class="container py-4">
  <div class="row g-3">
    <div class="col-lg-8">
      <h2>Contactos</h2>
      <p class="text-muted">
        <strong>Para contactarnos, envíe un correo, un mensaje whatsap, o use el fomulario.</strong>
      </p>
      <!-- Mantén aquí tu contenido existente: dirección, teléfonos, etc. -->

      <div class="mt-3">
        <a class="btn btn-primary" href="<?=$h($base)?>/site/form_contacto">
          Ir al formulario de contacto
        </a>
      </div>
    </div>
  </div>
</section>
