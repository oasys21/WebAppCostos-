<?php
declare(strict_types=1);
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<!-- Hero -->
<section class="container mb-4">
  <div class="hero p-4 p-md-5 bg-light border">
    <div class="row align-items-center g-3">
      <div class="col-md-2 text-center">
        <img src="<?= $h($base) ?>/public/images/logo.png" alt="Logo" class="img-fluid" style="max-height:90px" onerror="this.style.display='none'">
      </div>
      <div class="col-md-7">
        <h1 class="mb-2">Plataforma de Costos</h1>
        <p class="lead mb-0">Control de Proyectos, presupuestos, adquisiciones, imputaciones, documentación y control etapas de proyecto en un solo lugar.</p>
      </div>
      <div class="col-md-3 text-md-end">
        <a href="<?= $h($base) ?>/auth/login" class="btn btn-primary btn-lg">Ingresar</a>
      </div>
    </div>
  </div>
</section>

<!-- 3 columnas -->
<section class="container">
  <div class="row g-3">
    <!-- Izquierda: imagen + caption flotante -->
    <div class="col-md-3">
      <div class="img-float-box" style="height: 360px;">
        <img src="<?= $h($base) ?>/public/images/gear_02.png" alt="Proyectos" onerror="this.src='<?= $h($base) ?>/public/images/gear_02.png'">
        <div class="img-float-caption">Planificación por etapas y seguimiento en tiempo real.</div>
      </div>
    </div>

    <!-- Centro: textos -->
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <h4 class="card-title">Bienvenido</h4>
          <p class="card-text">
            RHGlobal - OASYS spa.
          </p>
          <ul class="mb-3">
            <li>Proyectos de Ingenieria y Construcción</li>
            <li>Plantas Fotovoltaicas y Autoconsumo Industrial</li>
            <li>Construcciones en General, Domótica y Seguridad Perimetral</li>
			<li>Construcciones en Obra, Oficinas de Campo, Dormitorios, Comedores, Baños</li>
            <li>Gestión de Proyectos, Sistemas CRM - WEBAPP - ERP/SAP</li>
            <li>Imágen Corporativa Virtual y Física</li>
          </ul>
          <a href="<?= $h($base) ?>/site/acerca" class="btn btn-primary">Saber más</a>
          <a href="<?= $h($base) ?>/auth/login" class="btn btn-success ms-2">Login</a>
        </div>
      </div>
    </div>

    <!-- Derecha: imagen + caption flotante -->
    <div class="col-md-3">
      <div class="img-float-box" style="height: 360px;">
        <img src="<?= $h($base) ?>/public/images/gear_09.png" alt="Adquisiciones" onerror="this.src='<?= $h($base) ?>/public/images/gear_09.png'">
        <div class="img-float-caption">Adquisiciones integradas: OC, Compras e Imputaciones.</div>
      </div>
    </div>
  </div>
</section>
