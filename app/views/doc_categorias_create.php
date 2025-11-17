<?php
// /costos/app/views/doc_categorias_create.php
$base = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$modulos = $modulos ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 m-0">Nueva Categoría</h1>
  <div>
    <a href="<?= $base ?>/doc-categorias/index" class="btn btn-outline-secondary">Volver</a>
  </div>
</div>

<!-- Mensajes (si viene e= en query) -->
<div id="toastSlot" class="position-relative mb-2" style="height:56px;">
  <div id="toast" class="toast align-items-center text-bg-danger border-0 position-absolute top-0 start-50 translate-middle-x"
       role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="1700" style="min-width: 280px;">
    <div class="d-flex">
      <div id="toastBody" class="toast-body">Error</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<form method="post" action="<?= $base ?>/doc-categorias/store" autocomplete="off" class="card p-3 shadow-sm">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Módulo</label>
      <select name="modulo" class="form-select" required>
        <option value="">-- Seleccione --</option>
        <?php foreach($modulos as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label">Nombre</label>
      <input type="text" name="nombre" class="form-control" required maxlength="80">
    </div>
    <div class="col-md-3">
      <label class="form-label d-block">&nbsp;</label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="activo" id="activo" checked>
        <label class="form-check-label" for="activo"> Activa</label>
      </div>
    </div>
    <div class="col-12">
      <label class="form-label">Descripción</label>
      <textarea name="descripcion" class="form-control" rows="3" maxlength="500"></textarea>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary">Guardar</button>
    <a href="<?= $base ?>/doc-categorias/index" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>

<?php if(isset($_GET['e'])): ?>
<script>
(function(){
  const msg = '<?= htmlspecialchars($_GET['e']) ?>';
  const t = document.getElementById('toast'), b = document.getElementById('toastBody');
  if (t && b) { b.textContent = msg; new bootstrap.Toast(t).show(); }
})();
</script>
<?php endif; ?>
