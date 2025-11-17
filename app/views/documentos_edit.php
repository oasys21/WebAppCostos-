<?php
declare(strict_types=1);

$__base = rtrim((string)($base ?? ''), '/');

// Control de sesión (salida a index)
if (class_exists('Session')) {
    if (!Session::user()) { header('Location: ' . $__base . '/'); exit; }
} else {
    if (empty($_SESSION['user'])) { header('Location: ' . $__base . '/'); exit; }
}

$doc        = $doc        ?? [];
$categorias = $categorias ?? [];
$proyectos  = $proyectos  ?? [];   // [{codigo_proy, nombre}, ...]
$modulos    = $modulos    ?? [];
$pageTitle  = $pageTitle  ?? 'Editar documento';

$docId   = (int)($doc['id'] ?? 0);
$nom     = (string)($doc['nombre_original'] ?? '');
$mime    = (string)($doc['mime'] ?? '');
$size    = (int)   ($doc['tamanio'] ?? 0);
$modSel  = (string)($doc['modulo'] ?? '');
$proySel = (string)($doc['proyecto'] ?? '');
if ($proySel === '') $proySel = 'Sin-Proyecto';
$itemSel = (string)($doc['itemcosto'] ?? '');
if ($itemSel === '') $itemSel = 'Sin-Item-Costo';
$catSel  = (int)   ($doc['categoria_id'] ?? 0);
$priv    = !empty($doc['privado']);
$emitido = (string)($doc['emitido_en'] ?? '');
$vence   = (string)($doc['vence_en'] ?? '');
$estado  = (string)($doc['estado'] ?? 'vigente');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0"><?= h($pageTitle) ?></h1>
    <?php if ($docId > 0): ?>
    <!-- Botón Eliminar (POST a destroy con confirmación) -->
    <form id="doc-delete-form" method="post" action="<?= $__base ?>/index.php?r=documentos/destroy/<?= $docId ?>">
      <button type="submit" class="btn btn-outline-danger">Eliminar</button>
    </form>
    <?php endif; ?>
  </div>
  <!-- Información del archivo almacenado -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-12 col-md-6">
          <div><strong>Nombre original:</strong> <?= h($nom) ?></div>
          <div><strong>MIME:</strong> <?= h($mime) ?></div>
          <div><strong>Tamaño:</strong> <?= number_format($size, 0, ',', '.') ?> bytes</div>
        </div>
        <div class="col-12 col-md-6 text-md-end">
          <?php if ($docId > 0): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= $__base ?>/index.php?r=documentos/preview/<?= $docId ?>" target="_blank">Ver</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= $__base ?>/index.php?r=documentos/download/<?= $docId ?>">Descargar</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Formulario de edición -->
  <form method="post" action="<?= $__base ?>/index.php?r=documentos/update/<?= $docId ?>" class="row g-3">

    <div class="col-12 col-md-4">
      <label class="form-label" for="modulo">Módulo</label>
      <select class="form-select" id="modulo" name="modulo" required>
        <option value="">Seleccione…</option>
        <?php foreach ($modulos as $m): $m=(string)$m; ?>
          <option value="<?= h($m) ?>" <?= ($m === $modSel ? 'selected' : '') ?>><?= h($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label" for="proyecto_view">Proyecto</label>
      <select class="form-select" id="proyecto_view" disabled>
        <option value="<?= h($proySel) ?>" selected>
          <?php
            $nm = '';
            foreach ($proyectos as $p) {
              if ((string)($p['codigo_proy'] ?? '') === $proySel) { $nm = (string)($p['nombre'] ?? ''); break; }
            }
            echo h($nm !== '' ? $nm.' ('.$proySel.')' : $proySel);
          ?>
        </option>
      </select>
      <!-- Enviar el valor al backend (disabled no envía) -->
      <input type="hidden" name="proyecto" value="<?= h($proySel) ?>">
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label" for="itemcosto_view">Ítem de costo</label>
      <select class="form-select" id="itemcosto_view" disabled>
        <option value="<?= h($itemSel) ?>" selected><?= h($itemSel) ?></option>
      </select>
      <input type="hidden" name="itemcosto" value="<?= h($itemSel) ?>">
    </div>

    <div class="col-12">
      <label class="form-label" for="titulo">Título</label>
      <input type="text" class="form-control" id="titulo" name="titulo" value="<?= h((string)($doc['titulo'] ?? '')) ?>" required>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="categoria_id">Categoría</label>
      <select class="form-select" id="categoria_id" name="categoria_id">
        <option value="0" <?= ($catSel===0?'selected':'') ?>>Sin categoría</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $catSel ? 'selected' : '') ?>>
            <?= h($c['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label d-block">&nbsp;</label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1" id="privado" name="privado" <?= $priv ? 'checked' : '' ?>>
        <label class="form-check-label" for="privado">Privado</label>
      </div>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="emitido_en">Emitido en</label>
      <input type="date" class="form-control" id="emitido_en" name="emitido_en" value="<?= h($emitido) ?>">
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label" for="vence_en">Vence en</label>
      <input type="date" class="form-control" id="vence_en" name="vence_en" value="<?= h($vence) ?>">
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label" for="estado">Estado</label>
      <select class="form-select" id="estado" name="estado">
        <option value="vigente" <?= $estado==='vigente' ? 'selected' : '' ?>>Vigente</option>
        <option value="vencido"  <?= $estado==='vencido'  ? 'selected' : '' ?>>Vencido</option>
      </select>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">Guardar cambios</button>
      <a href="<?= $__base ?>/index.php?r=documentos/index" class="btn btn-outline-secondary">Volver</a>
    </div>
  </form>
</div>

<script>
(function(){
  function domReady(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  domReady(function(){
    // Confirmación eliminación
    var delForm = document.getElementById('doc-delete-form');
    if (delForm) {
      delForm.addEventListener('submit', function(ev){
        if (!confirm('¿Eliminar este documento y TODAS sus versiones y archivos?')) {
          ev.preventDefault();
          return false;
        }
        var btn = delForm.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Eliminando…'; }
      });
    }
  });
})();
</script>
