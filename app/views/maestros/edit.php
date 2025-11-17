<?php
// /costos/app/views/maestros/edit.php
declare(strict_types=1);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="m-0">Editar ítem</h3>
  <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base) ?>/maestros/index">Volver</a>
</div>

<form method="post" action="<?= htmlspecialchars($base) ?>/maestros/update/<?= (int)$row['id'] ?>">
  <?php include __DIR__ . '/_form.php'; ?>
  <div class="mt-3">
    <button class="btn btn-primary">Actualizar</button>
  </div>
</form>
