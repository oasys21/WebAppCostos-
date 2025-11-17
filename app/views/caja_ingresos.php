<?php
/** @var string $base */
/** @var array  $usuarios */
/** @var string $today */
$base = rtrim((string)($base ?? ''), '/');
?>
<div class="container my-4" data-base="<?= htmlspecialchars($base) ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Ingreso a caja — otro usuario</h4>
    <div>
      <?php if (!empty($_SESSION['flash_ok'])): ?><span class="badge bg-success me-2"><?= htmlspecialchars($_SESSION['flash_ok']) ?></span><?php unset($_SESSION['flash_ok']); endif; ?>
      <?php if (!empty($_SESSION['flash_error'])): ?><span class="badge bg-danger me-2"><?= htmlspecialchars($_SESSION['flash_error']) ?></span><?php unset($_SESSION['flash_error']); endif; ?>
      <a class="btn btn-outline-secondary" href="<?= $base ?>/caja">Volver</a>
    </div>
  </div>

  <div class="alert alert-info">
    Solo perfiles <strong>ADM/CON</strong> con permiso <code>ADQ_DEL</code> pueden registrar ingresos en caja de cualquier usuario.
  </div>

  <div class="card">
    <div class="card-header">Datos del ingreso</div>
    <div class="card-body">
      <form class="row g-3 needs-validation" novalidate method="post" action="<?= $base ?>/caja/ingresosStore" enctype="multipart/form-data">
        <div class="col-md-6">
          <label class="form-label">Usuario destino</label>
          <select name="usuario_id" class="form-select" required>
            <option value="">Seleccione usuario...</option>
            <?php foreach ($usuarios as $u): ?>
              <option value="<?= (int)$u['id'] ?>">
                <?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['email'] ?? $u['rut'] ?? '') ?>) [<?= htmlspecialchars($u['perfil'] ?? '') ?>]
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Seleccione el usuario destino.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Fecha doc.</label>
          <input type="date" class="form-control" name="fecha_doc" value="<?= htmlspecialchars($today) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Documento</label>
          <select name="documento_tipo" class="form-select">
            <option value="RECIBO">Recibo/Comprobante</option>
            <option value="FACTURA">Factura</option>
            <option value="BOLETA">Boleta</option>
            <option value="OTRO">Otro</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">N° documento</label>
          <input type="text" class="form-control" name="numero_doc" maxlength="40" placeholder="Opcional">
        </div>

        <div class="col-md-3">
          <label class="form-label">Monto</label>
          <input type="text" class="form-control js-money-latam text-end" name="monto" inputmode="numeric" required placeholder="1,234,567">
          <div class="invalid-feedback">Monto inválido.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Medio de ingreso</label>
          <select name="medio_ingreso" class="form-select">
            <option value="TRANSFERENCIA">Transferencia</option>
            <option value="DEPOSITO">Depósito</option>
            <option value="EFECTIVO">Efectivo</option>
            <option value="OTRO">Otro</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Banco</label>
          <input type="text" class="form-control" name="banco" maxlength="80" placeholder="Banco/Origen">
        </div>

        <div class="col-md-6">
          <label class="form-label">Referencia de pago</label>
          <input type="text" class="form-control" name="referencia_pago" maxlength="120" placeholder="N° transferencia, glosa, etc.">
        </div>

        <div class="col-12">
          <label class="form-label">Comprobante (PDF/JPG/PNG, máx 20MB)</label>
          <input type="file" class="form-control" name="doc_file" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="col-12 text-end">
          <button class="btn btn-success">Registrar ingreso</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>window.CAJA_API_PROY='<?= $base ?>/caja/apiproyectos.php';window.CAJA_API_ITEMS='<?= $base ?>/caja/apiproyectositem.php';</script>
<script src="<?= $base ?>/public/js/caja.js"></script>
