<?php
declare(strict_types=1);
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';
?>
<!-- CSS: color del card (ajusta a tu gusto) -->
<!-- Hero -->
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<section class="container mb-4">
  <div class="hero p-4 p-md-5 bg-light border">
    <div class="row align-items-center g-3">
      <div class="col-md-2 text-center">
        <img src="<?= $h($base) ?>/public/images/logo.png" alt="Logo" class="img-fluid" style="max-height:90px" onerror="this.style.display='none'">
      </div>
      <div class="col-md-7">
        <h1 class="mb-2">Acerca de RHGlobal-OASYS spa</h1>
        <p class="lead mb-0">Nuestros servicios virtuales y físicos nos permiten ofrecer desde el control presupuestario y de avances, hasta instalaciones in situ.</p>
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
      <div class="card ">
        <div class="card-body">
          <h4 class="card-title">Bienvenido</h4>
          <h6 class="text-uppercase small mb-2">Servicios Virtuales</h6>
          <ul class="mb-3">
			<li class="mb-1">Implementación informática de control de proyectos, presupuestos y avances.</li>
            <li class="mb-1">Control de documentación física y lógica en sitio web privado.</li>
            <li class="mb-1">Manejo de documentación, trámites y certificaciones.</li>
            <li class="mb-1">Subida de datos al Cliente o Mandante (de sitio privado a sitio privado; sin Google Docs, OneDrive, GitHub, correo o WhatsApp).</li>
          </ul>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h6 class="text-uppercase small mb-2">Servicios Ingeniería</h6>
          <ul class="mb-3">
			<li class="mb-1">Instalación y mantención de plantas domésticas y de autoconsumo industrial.</li>
            <li class="mb-1">Servicios menores de mantenimiento, aseo, despeje de sitios, cercos perimetrales y caminos de acceso.</li>
            <li class="mb-1">Limpieza de paneles solares en seco y húmedo.</li>
            <li class="mb-1">Instalaciones de obra: galpones, containers, dormitorios, comedores, baños químicos y de pozo normado.</li>
            <li class="mb-1">Construcción de centrales de distribución fotovoltaica, bases, fundaciones y conexiones a inversores y distribuidores.</li>
          </ul>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h6 class="text-uppercase small mb-2">Servicios Corporativos</h6>
          <ul class="mb-3">
            <li class="mb-1">Gestión de herramientas, vehículos, asignaciones y control de mantención.</li>
            <li class="mb-1">Imagen corporativa, constructividad modelada y terminaciones corporativas.</li>
		  </ul>
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

