<?php
$__base = rtrim((string)($base ?? ''), '/');
if (class_exists('Session') && !Session::user()) { header('Location: ' . $__base . '/'); exit; }

if (!function_exists('u')) {
  function u($base, $path) {
    $url = rtrim($base ?? '', '/') . '/' . ltrim($path ?? '', '/');
    return preg_replace('~(?<!:)//+~', '/', $url);
  }
}

$doc = $doc ?? [];
$docId = (int)($doc['id'] ?? 0);
$pageTitle = $pageTitle ?? 'Versiones';
?>
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h1 class="h4 mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-success no-ajax" href="<?= u($__base, 'documentos/preview/' . $docId) ?>" target="_blank" rel="noopener">
        <i class="bi bi-eye"></i> Ver (actual)
      </a>
      <a class="btn btn-primary no-ajax" href="<?= u($__base, 'documentos/download/' . $docId) ?>" target="_self">
        <i class="bi bi-download"></i> Descargar (actual)
      </a>
      <a class="btn btn-outline-secondary" href="<?= u($__base, 'documentos/edit/' . $docId) ?>">
        <i class="bi bi-pencil-square"></i> Editar
      </a>
      <a class="btn btn-outline-dark" href="<?= u($__base, 'documentos') ?>">
        <i class="bi bi-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <!-- Datos del documento -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-12 col-md-4">
          <div class="text-muted small">Título</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($doc['titulo'] ?? '')) ?></div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-muted small">Módulo</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($doc['modulo'] ?? '')) ?></div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-muted small">Entidad</div>
          <div class="fw-semibold"><?= (int)($doc['entidad_id'] ?? 0) ?></div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-muted small">Estado</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($doc['estado'] ?? '')) ?></div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-muted small">Privado</div>
          <div class="fw-semibold"><?= (int)($doc['privado'] ?? 1) ? 'Sí' : 'No' ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Subir nueva versión -->
  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-upload"></i> Subir nueva versión
    </div>
    <div class="card-body">
      <form id="formVersion" class="row g-3 needs-validation" method="post" action="<?= u($__base, 'documentos/version_add/' . $docId) ?>" enctype="multipart/form-data" novalidate>
        <div class="col-12 col-md-6">
          <label for="archivo" class="form-label">Archivo</label>
          <input class="form-control" type="file" id="archivo" name="archivo" required>
          <div class="invalid-feedback">Seleccione un archivo.</div>
        </div>
        <div class="col-12 col-md-6">
          <label for="observacion" class="form-label">Observación (opcional)</label>
          <input type="text" class="form-control" id="observacion" name="observacion" maxlength="200">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-cloud-arrow-up"></i> Registrar versión
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Historial de versiones -->
  <div class="card">
    <div class="card-header">
      <i class="bi bi-clock-history"></i> Historial
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Nombre original</th>
              <th>Tamaño</th>
              <th>MIME</th>
              <th>Checksum</th>
              <th>Observación</th>
              <th>Subido por</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($vers) && is_array($vers)): ?>
              <?php foreach ($vers as $v): ?>
                <tr>
                  <td>v<?= (int)($v['nro_version'] ?? $v['version'] ?? 0) ?></td>
                  <td><?= htmlspecialchars((string)($v['nombre_original'] ?? '')) ?></td>
                  <td><?= number_format((int)($v['tamanio'] ?? 0)) ?> B</td>
                  <td><?= htmlspecialchars((string)($v['mime'] ?? '')) ?></td>
                  <td class="text-truncate" style="max-width:240px"><?= htmlspecialchars((string)($v['checksum_sha256'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($v['observacion'] ?? '')) ?></td>
                  <td><?= (int)($v['subido_por'] ?? $v['creado_por'] ?? 0) ?></td>
                  <td><?= htmlspecialchars((string)($v['creado_en'] ?? $v['fecha'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" class="text-center text-muted py-3">Sin versiones registradas</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  'use strict';
  // Validación Bootstrap para subir versión
  const form = document.getElementById('formVersion');
  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
    form.classList.add('was-validated');
  }, false);
})();
</script>
