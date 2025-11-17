<?php
declare(strict_types=1);
/** @var array $etapa   Cabecera */
/** @var array $items   Ítems de la etapa */
/** @var string $proyecto_nombre */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';

function fmt($n,$d=2){ return number_format((float)$n, $d, ',', '.'); }
function clp($n){ return 'CLP$ '.number_format((float)$n, 0, ',', '.'); }

$total = 0;
foreach(($items ?? []) as $it){ $total += (float)($it['monto'] ?? ((float)($it['cantidad'] ?? 0) * (float)($it['valor'] ?? 0))); }
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">Plan de Etapas</h3>
  <div class="d-flex gap-2">
    <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir</button>
    <a class="btn btn-secondary btn-sm" href="<?= $h($base) ?>/proyecto-etapas">Volver</a>
    <?php if(!empty($etapa['id'])): ?>
      <a class="btn btn-success btn-sm" href="<?= $h($base) ?>/proyecto-etapas/editar/<?= (int)$etapa['id'] ?>">Editar</a>
    <?php endif; ?>
  </div>
</div>

<!-- Cabecera -->
<div class="">
  <div class="header py-2"><strong>Cabecera</strong></div>
  <div class="body">
    <div class="row g-2">
      <div class="col-md-4">
        <div class="small text-muted">Proyecto</div>
        <div class="fw-semibold"><?= $h($proyecto_nombre ?? ('#'.$etapa['proyecto_id'])) ?></div>
      </div>
      <div class="col-md-4">
        <div class="small text-muted">Ítem de costo</div>
        <div class="fw-semibold"><code><?= $h($etapa['item_costo']) ?></code></div>
      </div>
      <div class="col-md-4">
        <div class="small text-muted">Estado</div>
        <span class="badge bg-secondary"><?= $h($etapa['estado'] ?? '') ?></span>
      </div>

      <div class="col-md-4">
        <div class="small text-muted">Título</div>
        <div><?= $h($etapa['titulo'] ?? '') ?></div>
      </div>
      <div class="col-md-4">
        <div class="small text-muted">Fecha inicio (Prog.)</div>
        <div><?= $h($etapa['fecha_inicio_prog'] ?? '') ?></div>
      </div>
      <div class="col-md-4">
        <div class="small text-muted">Fecha fin (Prog.)</div>
        <div><?= $h($etapa['fecha_fin_prog'] ?? '') ?></div>
      </div>

      <div class="col-md-4">
        <div class="small text-muted">Fecha inicio (Real)</div>
        <div><?= $h($etapa['fecha_inicio_real'] ?? '') ?></div>
      </div>
      <div class="col-md-4">
        <div class="small text-muted">Fecha fin (Real)</div>
        <div><?= $h($etapa['fecha_fin_real'] ?? '') ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Ítems -->
<div class="">
  <div class="header py-2"><strong>Etapas / Pasos</strong></div>
  <div class="body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
          <tr class="text-center">
            <th style="width:60px">Línea</th>
            <th style="min-width:220px">Descripción</th>
            <th style="width:110px">Unidad</th>
            <th style="width:120px">Cantidad</th>
            <th style="width:140px">Valor</th>
            <th style="width:100px">% Peso</th>
            <th style="width:140px">Monto</th>
            <th style="width:140px">Estado</th>
            <th style="width:120px">Avance %</th>
            <th style="width:160px">F. Prog (I-F)</th>
            <th style="width:160px">F. Real (I-F)</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!empty($items)): foreach($items as $it): ?>
          <tr>
            <td class="text-center"><?= (int)($it['linea'] ?? 0) ?></td>
            <td class="small" ><textarea rows="2" class="form-control form-control-sm" ><?= $h($it['descripcion'] ?? '') ?></textarea></td>
            <td><?= $h($it['unidad_med'] ?? '') ?></td>
            <td class="text-end"><?= fmt($it['cantidad'] ?? 0, 2) ?></td>
            <td class="text-end"><?= fmt($it['valor'] ?? 0, 2) ?></td>
            <td class="text-end"><?= (int)($it['porcentaje'] ?? 0) ?></td>
            <td class="text-end"><?= fmt($it['monto'] ?? ((float)($it['cantidad'] ?? 0)*(float)($it['valor'] ?? 0)), 2) ?></td>
            <td><?= $h(ucfirst((string)($it['estado_paso'] ?? ''))) ?></td>
            <td class="text-end"><?= fmt($it['avance_pct'] ?? 0, 2) ?></td>
            <td style="width:250px">
              <?= $h($it['fecha_inicio_prog'] ?? '') ?> — <?= $h($it['fecha_fin_prog'] ?? '') ?>
            </td>
            <td style="width:250px">
              <?= $h($it['fecha_inicio_real'] ?? '') ?> — <?= $h($it['fecha_fin_real'] ?? '') ?>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="11" class="text-center text-muted">Sin etapas definidas</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="table-light">
            <th colspan="6" class="text-end">Total</th>
            <th class="text-end"><?= clp($total) ?></th>
            <th colspan="4"></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
