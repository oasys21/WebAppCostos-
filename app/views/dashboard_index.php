<?php
/** @var string      $base */
/** @var array|null  $proy */
/** @var array|null  $kpis */
/** @var array       $items */
/** @var array       $proyectos */
/** @var int         $selPid */
/** @var string|null $warn */

$base = rtrim((string)($base ?? ''), '/');
function nf0($n){ return number_format((float)$n, 0, ',', ','); } // LATAM sin decimales
$avance = $kpis ? max(0, min(100, (float)$kpis['avance'])) : 0;

// Datos para el gráfico (top 8 por ejecutado)
$chartItems = array_slice($items ?? [], 0, 8);
$chartLabels = [];
$chartValues = [];
foreach ($chartItems as $it) {
  $lbl = trim(($it['codigo'] ?? '') . ' ' . ($it['glosa'] ?? ''));
  $lbl = $lbl !== '' ? $lbl : ('Ítem #' . (int)($it['id'] ?? 0));
  $chartLabels[] = mb_substr($lbl, 0, 24) . (mb_strlen($lbl) > 24 ? '…' : '');
  $chartValues[] = (float)($it['subtotal_real'] ?? 0);
}
?>
<style>
  /* Altura de gráfico y ajustes visuales */
  #chartCard { min-height: 360px; }
  #chartCanvas { width: 100%; height: 260px; display: block; }
  .btn-stack .btn { text-align: left; }
</style>
 <style>
 <!--
body{padding-top:4.5rem; 	
background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); 
background-color: transparent;	background-repeat: repeat;}	
-->
</style>
<div class="mx-auto d-block " style="align:center; width:70%;">
<div class="container my-4" data-base="<?= htmlspecialchars($base, ENT_QUOTES) ?>">

  <!-- Toast de aviso de cierre -->
  <div id="cierreToast" class="toast align-items-center text-bg-warning border-0 position-fixed top-0 end-0 m-3"
       role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4500"
       style="z-index:1080; min-width: 320px; <?= $warn ? '' : 'display:none;' ?>">
    <div class="d-flex">
      <div class="toast-body"><?= htmlspecialchars($warn ?? '') ?></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Dashboard Proyecto</h4>
    <!-- Se removieron botones de aquí: ahora van abajo del gráfico -->
  </div>

  <!-- Selector de proyecto -->
  <div class=" shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get" action="<?= $base ?>/dashboard" id="frmProyectoSel" autocomplete="off">
        <div class="col-md-9">
          <label class="form-label">Seleccione Proyecto</label>
          <select name="proyecto_id" id="proyectoSel" class="form-select" required>
            <?php if(empty($proyectos)): ?>
              <option value="">(sin proyectos)</option>
            <?php else: ?>
              <?php foreach ($proyectos as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= ((int)$p['id']===$selPid?'selected':'') ?>>
                  <?= htmlspecialchars(($p['nombre'] ?? 'Proyecto '.(int)$p['id'])) ?>
                  <?php if(!empty($p['codigo_proy'])): ?>
                    — <?= htmlspecialchars($p['codigo_proy']) ?>
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-3 text-end">
          <button class="btn btn-primary w-100">Ver</button>
        </div>
      </form>
    </div>
  </div>

  <?php if(!$proy): ?>
    <div class="alert alert-info">
      No tienes proyectos habilitados. Pide a un administrador que te asigne a uno o crea un proyecto si corresponde.
    </div>
  <?php else: ?>

    <div class="row g-3">
      <!-- 3/4: contenido principal -->
      <div class="col-lg-9">

        <!-- Header proyecto -->
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
              <div>
                <div class="text-muted small">Proyecto</div>
                <h5 class="mb-0"><?= htmlspecialchars($proy['nombre'] ?? ('Proyecto #'.(int)$proy['id'])) ?></h5>
                <?php if(!empty($proy['codigo_proy'])): ?>
                  <div class="text-muted small">Código: <?= htmlspecialchars($proy['codigo_proy']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
              <div class="small text-muted">Presupuesto total</div>
              <div class="h5 text-end">$ <?= nf0($kpis['total_pres'] ?? 0) ?></div>
            </div></div>
          </div>
          <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
              <div class="small text-muted">Ejecutado</div>
              <div class="h5 text-end">$ <?= nf0($kpis['total_real'] ?? 0) ?></div>
            </div></div>
          </div>
          <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
              <div class="small text-muted">Desvío</div>
              <?php $dz = (float)($kpis['desvio'] ?? 0); ?>
              <div class="h5 text-end <?= $dz>=0?'text-success':'text-danger' ?>">$ <?= nf0($dz) ?></div>
            </div></div>
          </div>
          <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
              <div class="small text-muted">Avance</div>
              <div class="h5 text-end"><?= number_format(max(0,min(100,(float)($kpis['avance'] ?? 0))), 1, ',', '') ?>%</div>
            </div></div>
          </div>
        </div>

        <!-- Barra simple de avance -->
        <div class="progress mb-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$avance ?>">
          <div class="progress-bar" style="width: <?= (float)$avance ?>%"></div>
        </div>

        <!-- Top ítems por consumo -->
        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>Ítems con mayor ejecución</div>
            <!-- Botón “Ver presupuesto” movido abajo del gráfico -->
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:1%">#</th>
                  <th>Código</th>
                  <th>Glosa</th>
                  <th class="text-end">Presupuestado</th>
                  <th class="text-end">Ejecutado</th>
                  <th class="text-end">Dif.</th>
                </tr>
              </thead>
              <tbody>
              <?php $i=1; foreach($items as $it): ?>
                <?php
                  $pres = (float)($it['subtotal_pres'] ?? 0);
                  $real = (float)($it['subtotal_real'] ?? 0);
                  $dif  = $pres - $real;
                ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($it['codigo'] ?? '') ?></span></td>
                  <td><?= htmlspecialchars($it['glosa'] ?? '') ?></td>
                  <td class="text-end">$ <?= nf0($pres) ?></td>
                  <td class="text-end">$ <?= nf0($real) ?></td>
                  <td class="text-end <?= $dif>=0?'text-success':'text-danger' ?>">$ <?= nf0($dif) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($items)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Sin datos</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- 1/4: panel derecho con gráfico y botones -->
      <div class="col-lg-3">
        <div id="chartCard" class="card shadow-sm mb-3">
          <div class="card-header">Ejecución por ítem (Top 8)</div>
          <div class="card-body">
            <canvas id="chartCanvas"></canvas>
            <?php if(empty($chartValues)): ?>
              <div class="text-muted small mt-2">Sin datos para graficar.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Todos los botones abajo del gráfico -->
        <div class="d-grid gap-2 btn-stack">
          <a class="btn btn-primary" href="<?= $base ?>/proyectos">Proyectos</a>
          <a class="btn btn-secondary" href="<?= $base ?>/proyectos/show/<?= (int)$proy['id'] ?>">Ver Proyecto</a>
          <a class="btn btn-success" href="<?= $base ?>/presupuestos?proyecto_id=<?= (int)$proy['id'] ?>">Ver Presupuesto</a>
		  <a class="btn btn-warning" href="<?= $base ?>/proyecto-etapas">Ver Etapas</a>		  
        </div>
      </div>
    </div>

  <?php endif; ?>

</div>
</div>
<!-- jQuery (CDN + fallback local) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>
<script> if(!window.jQuery){document.write('<script src="<?= $base ?>/public/vendor/jquery-3.7.1.min.js"><\/script>');} </script>
<!-- Bootstrap Bundle (por si el layout no lo incluye aquí) -->
<script src="<?= $base ?>/public/vendor/bootstrap.bundle.min.js"></script>

<script>
  (function(){
    // Toast de cierre
    var el = document.getElementById('cierreToast');
    if (el && el.style.display !== 'none') { new bootstrap.Toast(el).show(); }

    // Submit automático del selector (opcional; ya hay botón Ver)
    var sel = document.getElementById('proyectoSel');
    if (sel) {
      sel.addEventListener('change', function(){
        document.getElementById('frmProyectoSel')?.submit();
      });
    }

    // ====== Mini-gráfico sin librerías (canvas) ======
    var labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    var values = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    var canvas = document.getElementById('chartCanvas');
    if (!canvas || !values || !values.length) return;

    function nf0(n){ n = Number(n)||0; return n.toLocaleString('es-CL', {maximumFractionDigits:0}); }

    function drawChart(){
      var dpr = window.devicePixelRatio || 1;
      var cssW = canvas.clientWidth || 320;
      var cssH = canvas.clientHeight || 260;
      canvas.width  = Math.floor(cssW * dpr);
      canvas.height = Math.floor(cssH * dpr);

      var ctx = canvas.getContext('2d');
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.clearRect(0,0,cssW,cssH);
      var padding = {top: 10, right: 10, bottom: 60, left: 50};
      var w = cssW - padding.left - padding.right;
      var h = cssH - padding.top - padding.bottom;

      var maxV = 0;
      for (var i=0;i<values.length;i++) if (values[i] > maxV) maxV = values[i];
      if (maxV <= 0) { ctx.fillStyle='#666'; ctx.fillText('Sin datos', cssW/2-25, cssH/2); return; }

      // Ejes y marcas
      ctx.strokeStyle = '#bbb';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(padding.left, padding.top);
      ctx.lineTo(padding.left, padding.top + h);
      ctx.lineTo(padding.left + w, padding.top + h);
      ctx.stroke();

      // Ticks Y (4)
      ctx.fillStyle = '#666';
      ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial';
      for (var t=0;t<=4;t++){
        var val = maxV * t / 4;
        var y = padding.top + h - (h * val / maxV);
        ctx.fillText(nf0(val), 4, y+4);
        ctx.strokeStyle = '#eee';
        ctx.beginPath(); ctx.moveTo(padding.left, y); ctx.lineTo(padding.left + w, y); ctx.stroke();
      }

      // Barras
      var n = values.length;
      var gap = 10;
      var barW = Math.max(12, (w - gap*(n+1)) / n);
      for (var i=0;i<n;i++){
        var x = padding.left + gap + i*(barW+gap);
        var bh = h * (values[i] / maxV);
        var y = padding.top + h - bh;
        ctx.fillStyle = '#0d6efd';
        ctx.fillRect(x, y, barW, bh);

        // Etiquetas X
        ctx.save();
        ctx.translate(x + barW/2, padding.top + h + 14);
        ctx.rotate(-Math.PI/6);
        ctx.fillStyle = '#333';
        ctx.textAlign = 'left';
        ctx.fillText((labels[i] || ('Ítem ' + (i+1))), 0, 0);
        ctx.restore();

        // Valor sobre barra
        if (bh > 18){
          ctx.fillStyle = '#fff';
          ctx.textAlign = 'center';
          ctx.fillText('$'+nf0(values[i]), x + barW/2, y + 14);
        } else {
          ctx.fillStyle = '#333';
          ctx.textAlign = 'center';
          ctx.fillText('$'+nf0(values[i]), x + barW/2, y - 4);
        }
      }
    }

    drawChart();
    window.addEventListener('resize', function(){ drawChart(); });
  })();
</script>
