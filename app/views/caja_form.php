<?php
/** @var string $base */
/** @var array  $caja */
/** @var array|null $row */
/** @var string $now */
$base = rtrim((string)($base ?? ''), '/');
$proyecto_id_sel  = (int)($row['proyecto_id'] ?? 0);
$proycosto_id_sel = (int)($row['proyecto_costo_id'] ?? 0);
?>
 <style>
 <!--
body{padding-top:4.5rem; 	
background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); 
background-color: transparent;	background-repeat: repeat;}	
-->
</style>
<div class="mx-auto d-block " style="align:center;">
<div class="container my-4" data-base="<?= htmlspecialchars($base, ENT_QUOTES) ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Nuevo movimiento — Caja <?= (int)$caja['anio']; ?>/<?= str_pad((string)$caja['mes'],2,'0',STR_PAD_LEFT) ?></h4>
    <a class="btn btn-secondary" href="<?= $base ?>/caja">Volver</a>
  </div>
  <div class="">
    <div class="card-body">
      <form class="row g-3 needs-validation" novalidate method="post"
            action="<?= $base ?>/caja/store" enctype="multipart/form-data">
        <input type="hidden" name="caja_id" value="<?= (int)$caja['id'] ?>">
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <select name="tipo" class="form-select" required>
            <option value="EGRESO" selected>Egreso</option>
            <option value="AJUSTE">Ajuste</option>
            <option value="INGRESO">Ingreso</option>
            <option value="TRASPASO_IN">Traspaso (+)</option>
            <option value="TRASPASO_OUT">Traspaso (−)</option>
          </select>
          <div class="invalid-feedback">Seleccione un tipo.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Fecha doc.</label>
          <input type="date" name="fecha_doc" class="form-control"
                 value="<?= htmlspecialchars(substr($now,0,10)) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Documento</label>
          <select name="documento_tipo" class="form-select">
            <option value="BOLETA">Boleta</option>
            <option value="FACTURA">Factura</option>
            <option value="RECIBO">Recibo</option>
            <option value="OTRO">Otro</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">N° documento</label>
          <input type="text" name="numero_doc" class="form-control" maxlength="40" placeholder="Opcional">
        </div>

        <div class="col-md-4">
          <label class="form-label">Proyecto</label>
          <select id="proyecto" name="proyecto_id" class="form-select"
                  data-selected="<?= (int)$proyecto_id_sel ?>">
            <option value="">Cargando proyectos...</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Ítem de costo (imputación)</label>
          <select id="item" name="proyecto_costo_id" class="form-select"
                  data-selected="<?= (int)$proycosto_id_sel ?>" required>
            <option value="">Seleccione proyecto primero...</option>
          </select>
          <div class="invalid-feedback">Seleccione el ítem de costo.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Monto</label>
          <input type="text" name="monto"
                 class="form-control js-money-latam text-end"
                 inputmode="numeric" placeholder="1,234,567" value="">
          <div class="invalid-feedback">Ingrese el monto.</div>
        </div>

        <div class="col-12">
          <label class="form-label">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="2" placeholder="Glosa"></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Adjunto (PDF/JPG/PNG, máx 20MB)</label>
          <input type="file" name="doc_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="col-12 text-end">
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- jQuery (por si el layout no se está usando en este módulo) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>
<script>
  window.jQuery || document.write('<script src="<?= $base ?>/public/vendor/jquery-3.7.1.min.js"><\/script>');
</script>
<!-- Endpoints para AJAX -->
<script>
  window.CAJA_API_PROY  = '<?= $base ?>/caja/apiproyectos';
  window.CAJA_API_ITEMS = '<?= $base ?>/caja/apiproyectoitems';
</script>
<script src="<?= $base ?>/public/js/caja.js?v=<?= (int)($_SERVER['REQUEST_TIME'] ?? time()) ?>"></script>
