<?php
// /costos/app/views/layout/header.php
declare(strict_types=1);

// BASE_URL robusto
if (isset($this) && isset($this->cfg['BASE_URL']) && is_string($this->cfg['BASE_URL']) && $this->cfg['BASE_URL'] !== '') {
  $base = rtrim($this->cfg['BASE_URL'], '/');
} else {
  $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  $base = rtrim(str_replace('\\','/', dirname($sn)), '/');
  if ($base === '' || $base === '.') { $base = ''; }
}

// Usuario y subperfil
$u = class_exists('Session') ? (Session::user() ?? null) : null;
$sp_has = function (?array $usr, int $pos): bool {
  if (!$usr) return false;
  $sp = preg_replace('/[^01]/','0',(string)($usr['subperfil'] ?? ''));
  $sp = str_pad($sp, 30, '0');
  $idx = $pos - 1;
  return isset($sp[$idx]) && $sp[$idx] === '1';
};

$isADM = (!!$u && (($u['perfil'] ?? '') === 'ADM' || ($u['perfil'] ?? '') === 'ADMIN'));

// Visibilidades para logueados
$canDOX_view = $isADM || $sp_has($u,25) || $sp_has($u,26) || $sp_has($u,27);
$canUSR_view = $isADM || $sp_has($u,1)  || $sp_has($u,2)  || $sp_has($u,3);
$canCAT_view = $isADM || $sp_has($u,16) || $sp_has($u,17) || $sp_has($u,18);
$canCLI_view = $isADM || $sp_has($u,10) || $sp_has($u,11) || $sp_has($u,12);
$canPRO_view = $isADM || $sp_has($u,4)  || $sp_has($u,5)  || $sp_has($u,6);
$canADQ_view = $isADM || $sp_has($u,7)  || $sp_has($u,8)  || $sp_has($u,9);

// avatar / título
$foto = ($u && !empty($u['foto']))
  ? ($base.'/public/images/usuarios/'.rawurlencode((string)$u['foto']))
  : ($base.'/public/images/usuarios/avatar-default.png');
$title = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Costos';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RHGlobal-Costos(c)2025</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/public/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/public/css/app.css">
  <style>
    body{
      padding-top:4.5rem;
      background-image:url(../public/images/fondoverde3.jpg);
      background-color:transparent;
      background-repeat:repeat;
    }
    .navbar-brand{font-weight:600}
    .avatar-xs{width:34px;height:34px;border-radius:50%;object-fit:cover}
    .hero {
      background: linear-gradient(180deg, rgba(0,0,0,.35), rgba(0,0,0,.15));
      border-radius: 1rem;
    }
    .img-float-box { position: relative; overflow: hidden; border-radius: .75rem; }
    .img-float-box img { width:100%; height:100%; object-fit:cover; }
    .img-float-caption { position:absolute; left:0; right:0; bottom:0; padding:.5rem .75rem;
      background: rgba(0,0,0,.5); color:#fff; font-size:.9rem; }
  </style>
  <script>window.BASE_URL="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>";</script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= htmlspecialchars($base) ?>/">
      <img src="<?= htmlspecialchars($base) ?>/public/images/logo.png" alt="Logo" onerror="this.style.display='none'" width="28" height="28">
      <span>Costos</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if($u): ?>
		  <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/dashboard">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/proyectos/index">Proyectos</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/presupuestos/index">Presupuestos</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/proyecto-etapas">Etapas</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/gestion">Gestión</a></li>
          <?php if ($canADQ_view): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Adquisiciones</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/ocompras">Orden de Compra</a></li>
              <li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/compras">Adquisiciones</a></li>
			  <li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/caja">Rendiciones</a></li>
              <li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/imputaciones">Imputaciones</a></li>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($canDOX_view): ?>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/documentos/index">Documentos</a></li>
          <?php endif; ?>
          <?php if ($isADM): ?>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/logsys">Auditoría</a></li>
          <?php endif; ?>

          <!-- Maestros -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Maestros</a>
            <ul class="dropdown-menu">
              <?php if ($canUSR_view): ?><li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/usuarios/index">Usuarios</a></li><?php endif; ?>
              <?php if ($canDOX_view): ?><li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/doc-categorias/index">Categorías Doc.</a></li><?php endif; ?>
              <?php if ($canCAT_view): ?><li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/catalogo/index">Catálogo Costos</a></li><?php endif; ?>
              <?php if ($canCLI_view): ?><li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/clientes/index">Clientes</a></li><?php endif; ?>
              <?php if ($canPRO_view): ?><li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/proveedores/index">Proveedores</a></li><?php endif; ?>
            </ul>
          </li>
        <?php else: ?>
          <!-- Público no logueado -->
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/">Inicio</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/site/acerca">Acerca de</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base) ?>/site/contacto">Contactos</a></li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <?php if($u): ?>
          <li class="nav-item dropdown">
            <a class="nav-link d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
              <img class="avatar-xs" src="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>" alt="avatar">
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li class="dropdown-item-text small text-muted">
                <?= htmlspecialchars((string)($u['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($u['perfil'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= htmlspecialchars($base) ?>/usuarios/miperfil">Mi perfil</a></li>
              <li><a class="dropdown-item text-danger" href="<?= htmlspecialchars($base) ?>/auth/logout">Cerrar sesión</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="btn btn-light btn-sm" href="<?= htmlspecialchars($base) ?>/auth/login">Login</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container-fluid">
<?php
if (!isset($_SESSION)) @session_start();
if (!empty($_SESSION['flash'])):
  foreach ($_SESSION['flash'] as $f):
    $t = $f['t'] ?? 'info';
    $m = $f['m'] ?? '';
    $cls = ['success'=>'success','error'=>'danger','warning'=>'warning','info'=>'info'][$t] ?? 'secondary';
?>
  <div class="alert alert-<?= htmlspecialchars($cls) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($m) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php
  endforeach;
  unset($_SESSION['flash']);
endif;
?>
