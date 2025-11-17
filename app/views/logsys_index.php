<?php
// /costos/app/views/logsys_index.php
$base  = rtrim($this->cfg['BASE_URL'] ?? '/', '/');
$p     = (int)($p ?? 1);
$pages = (int)($pages ?? 1);
$total = (int)($total ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="h4 mb-0">Auditoría (LogSys)</h2>
  <a class="btn btn-sm btn-secondary" href="<?= $base ?>/usuarios/index">Volver</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" class="form-control" placeholder="rut, nombre, ip, acción, entidad...">
      </div>
      <div class="col-md-2">
        <label class="form-label">Acción</label>
        <input type="text" name="acc" value="<?= htmlspecialchars($acc ?? '') ?>" class="form-control" placeholder="LOGIN_OK, UPDATE...">
      </div>
      <div class="col-md-2">
        <label class="form-label">Entidad</label>
        <input type="text" name="ent" value="<?= htmlspecialchars($ent ?? '') ?>" class="form-control" placeholder="auth, usuarios, documentos">
      </div>
      <div class="col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" name="f1" value="<?= htmlspecialchars($f1 ?? '') ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" name="f2" value="<?= htmlspecialchars($f2 ?? '') ?>" class="form-control">
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button class="btn btn-primary w-100">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>IP</th>
            <th>Acción</th>
            <th>Entidad</th>
            <th>Entidad ID</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
        <?php if(empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Sin registros</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><span class="text-nowrap"><?= htmlspecialchars($r['creado_en']) ?></span></td>
            <td>
              <div class="small">
                <div><strong><?= htmlspecialchars($r['nombre'] ?: '-') ?></strong></div>
                <div class="text-muted">ID: <?= (int)$r['user_id'] ?> · RUT: <?= htmlspecialchars($r['rut'] ?: '-') ?></div>
              </div>
            </td>
            <td><?= htmlspecialchars($r['ip']) ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($r['accion']) ?></span></td>
            <td><?= htmlspecialchars($r['entidad']) ?></td>
            <td><?= $r['entidad_id'] !== null ? (int)$r['entidad_id'] : '-' ?></td>
            <td style="max-width:360px;">
              <?php
                $det = trim((string)$r['detalle_json']);
                $short = (mb_strlen($det) > 120) ? (mb_substr($det, 0, 120).'…') : $det;
              ?>
              <span class="d-inline-block text-truncate" style="max-width: 340px;" title="<?= htmlspecialchars($det) ?>">
                <?= htmlspecialchars($short) ?>
              </span>
              <?php if (mb_strlen($det) > 120): ?>
                <button class="btn btn-sm btn-link p-0 ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#d<?= (int)$r['id'] ?>">ver</button>
                <div id="d<?= (int)$r['id'] ?>" class="collapse mt-1"><pre class="small mb-0"><?= htmlspecialchars($det) ?></pre></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-2">
      <div class="text-muted small">
        Total: <?= number_format($total) ?> · Página <?= (int)$p ?> de <?= (int)$pages ?>
      </div>
      <?php if($pages > 1): ?>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php
            // construir query string preservando filtros
            $qsBase = $_GET;
            $mk = function($np) use ($qsBase, $base) {
              $qsBase['p'] = $np;
              return $base . '/logsys/index?' . http_build_query($qsBase);
            };
          ?>
          <li class="page-item <?= $p<=1?'disabled':'' ?>"><a class="page-link" href="<?= $mk(max(1,$p-1)) ?>">‹</a></li>
          <li class="page-item active"><span class="page-link"><?= (int)$p ?></span></li>
          <li class="page-item <?= $p>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $mk(min($pages,$p+1)) ?>">›</a></li>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
