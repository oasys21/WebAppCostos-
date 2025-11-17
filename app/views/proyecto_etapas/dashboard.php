<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $etapas */
/** @var int $totalEtapas */
/** @var float $sumaValor */
/** @var float $promAvance */
/** @var array $porEstado */
/** @var bool $isAdmin */
/** @var string $scope */
/** @var array $proyectos */
/** @var string $mensaje */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';
$isAdmin = $isAdmin ?? false;
$scope   = $scope   ?? 'mine';

$proyectosList          = $proyectos ?? [];
$selectedProyectoId     = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;
$selectedProyectoNombre = null;

// Si tenemos proyecto_id, mostrar su nombre
if ($selectedProyectoId > 0) {
    if (!empty($proyectosList)) {
        foreach ($proyectosList as $p) {
            if ((int)($p['id'] ?? 0) === $selectedProyectoId) {
                $selectedProyectoNombre = $p['nombre'] ?? ('ID '.$p['id']);
                break;
            }
        }
    }
    if ($selectedProyectoNombre === null && !empty($etapas)) {
        foreach ($etapas as $e) {
            if ((int)($e['proyecto_id'] ?? 0) === $selectedProyectoId) {
                $selectedProyectoNombre = $e['proyecto_nombre'] ?? ('ID '.$selectedProyectoId);
                break;
            }
        }
    }
}
?>
<style>
body{
    padding-top:4.5rem;
    background-image: url(<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/public/images/fondoverde3.jpg);
    background-color: transparent;
    background-repeat: repeat;
}
</style>

<div class="mx-auto d-block" style="align:center; width:70%;">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0"><?= $h($pageTitle ?? 'Dashboard de Etapas') ?></h3>
      <?php if ($selectedProyectoNombre !== null): ?>
        <div class="text-muted small mt-1">
          Proyecto: <span class="fw-semibold"><?= $h($selectedProyectoNombre) ?></span>
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= $h($base) ?>/proyecto-etapas" class="btn btn-outline-secondary btn-sm">
        Volver al listado
      </a>
      <a href="<?= $h($base) ?>/proyecto-etapas/nuevo" class="btn btn-primary btn-sm">
        Nueva Planificación
      </a>
    </div>
  </div>

  <?php if (!empty($mensaje)): ?>
    <div class="alert alert-warning py-2">
      <?= $h($mensaje) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($isAdmin)): ?>
    <div class="mb-2">
      <span class="badge bg-info text-dark">Admin</span>
      <span class="ms-2 small">
        Alcance:
        <?= $scope === 'all' ? 'Todas las etapas' : 'Solo mis proyectos (owner / invitado)' ?>
      </span>
    </div>
  <?php endif; ?>

  <!-- Filtros del dashboard -->
  <form class="row g-2 mb-3" method="get" action="<?= $h($base) ?>/proyecto-etapas/dashboard" autocomplete="off">
    <div class="col-sm-3">
      <label class="form-label mb-1">Proyecto</label>
      <select name="proyecto_id" class="form-select form-select-sm">
        <option value="">-- Seleccione --</option>
        <?php foreach(($proyectosList ?? []) as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ($selectedProyectoId === (int)$p['id'])?'selected':'' ?>>
            <?= $h($p['nombre'] ?? ('ID '.$p['id'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-sm-3">
      <label class="form-label mb-1">Código costo (FFFGGGIIII)</label>
      <input type="text"
             name="item_costo"
             class="form-control form-control-sm"
             value="<?= $h($_GET['item_costo'] ?? '') ?>"
             maxlength="10">
    </div>

    <div class="col-sm-3">
      <label class="form-label mb-1">Título</label>
      <input type="text"
             name="titulo"
             class="form-control form-control-sm"
             value="<?= $h($_GET['titulo'] ?? '') ?>"
             maxlength="120">
    </div>

    <div class="col-sm-3">
      <label class="form-label mb-1">Estado</label>
      <select name="estado" class="form-select form-select-sm">
        <option value="">-- Todos --</option>
        <?php foreach(['borrador','planificado','en_proceso','completado','anulado'] as $e): ?>
          <option value="<?= $h($e) ?>" <?= (($_GET['estado'] ?? '')===$e)?'selected':'' ?>>
            <?= $h(ucfirst($e)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-sm-3">
      <label class="form-label mb-1">Usuario (RUT)</label>
      <input type="text"
             name="usuario_rut"
             class="form-control form-control-sm"
             value="<?= $h($_GET['usuario_rut'] ?? '') ?>"
             placeholder="11.111.111-1">
    </div>

    <?php if (!empty($isAdmin)): ?>
      <div class="col-sm-4 d-flex align-items-end">
        <div class="form-check mt-3">
          <input class="form-check-input"
                 type="checkbox"
                 name="scope"
                 id="scope_all_dash"
                 value="all"
                 <?= (($_GET['scope'] ?? $scope) === 'all') ? 'checked' : '' ?>>
          <label class="form-check-label" for="scope_all_dash">
            Ver todas las etapas
          </label>
        </div>
      </div>
    <?php endif; ?>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-sm btn-primary" type="submit">Aplicar filtros</button>
      <a class="btn btn-sm btn-secondary" href="<?= $h($base) ?>/proyecto-etapas/dashboard?proyecto_id=<?= $selectedProyectoId > 0 ? (int)$selectedProyectoId : '' ?>">
        Limpiar
      </a>
    </div>
  </form>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total etapas</div>
          <div class="fs-4 fw-semibold"><?= (int)$totalEtapas ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Valor total (suma)</div>
          <div class="fs-5">
            CLP$ <?= number_format((float)$sumaValor, 0, ',', '.') ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Avance promedio</div>
          <div class="fs-4 fw-semibold">
            <?= number_format((float)$promAvance, 2, ',', '.') ?> %
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header">
      Etapas por estado
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Estado</th>
              <th style="width:120px;" class="text-end">Cantidad</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($porEstado)): ?>
              <?php foreach ($porEstado as $estado => $cnt): ?>
                <tr>
                  <td><?= $h($estado) ?></td>
                  <td class="text-end"><?= (int)$cnt ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="2" class="text-center text-muted">Sin datos</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header">
      Detalle de etapas
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px">ID</th>
              <th>Proyecto</th>
              <th style="width:120px">Código</th>
              <th>Título</th>
              <th style="width:120px">Estado</th>
              <th style="width:120px" class="text-end">Cant. Total</th>
              <th style="width:140px" class="text-end">Valor Total</th>
              <th style="width:110px" class="text-end">Avance %</th>
              <th style="width:160px">F. Prog (Ini-Fin)</th>
              <th style="width:170px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($etapas)): foreach ($etapas as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td style="width:150px"><?= $h($r['proyecto_nombre'] ?? ('#'.$r['proyecto_id'])) ?></td>
                <td><code><?= $h($r['item_costo']) ?></code></td>
                <td style="width:300px"><?= $h($r['titulo'] ?? '') ?></td>
                <td><span class="badge bg-secondary"><?= $h($r['estado'] ?? '') ?></span></td>
                <td class="text-end">
                  <?= number_format((float)($r['cantidad_total'] ?? 0), 2, ',', '.') ?>
                </td>
                <td class="text-end">
                  CLP$ <?= number_format((float)($r['valor_total'] ?? 0), 0, ',', '.') ?>
                </td>
                <td class="text-end">
                  <?= number_format((float)($r['avance_pct'] ?? 0), 2, ',', '.') ?>
                </td>
                <td style="width:200px">
                  <?= $h($r['fecha_inicio_prog'] ?? '') ?> — <?= $h($r['fecha_fin_prog'] ?? '') ?>
                </td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-primary"
                     href="<?= $h($base) ?>/proyecto-etapas/ver/<?= (int)$r['id'] ?>">
                    Ver
                  </a>
                  <a class="btn btn-sm btn-success"
                     href="<?= $h($base) ?>/proyecto-etapas/editar/<?= (int)$r['id'] ?>">
                    Editar
                  </a>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="10" class="text-center text-muted">Sin resultados</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
