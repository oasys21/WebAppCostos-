<?php
/** @var array $rows */
/** @var int $proyecto_id */
/** @var array|null $proyecto */
/** @var string|null $from */
/** @var string|null $to */
/** @var array $proy_list */
/** @var string $csrf */
$base = rtrim($this->cfg['BASE_URL'] ?? '', '/');
$fmt = fn($c)=> strlen($c)===10 ? substr($c,0,3).'-'.substr($c,3,3).'-'.substr($c,6,4) : $c;
?>
<div class="container-fluid py-3">

  <div class="row g-2 align-items-end mb-3">
    <div class="col-lg-7">
      <label class="form-label">Proyecto</label>
      <div class="input-group">
        <input id="qProyecto" class="form-control" placeholder="Buscar por código o descripción...">
        <button id="btnBuscar" class="btn btn-outline-secondary" type="button">Buscar</button>
        <select id="selProyecto" class="form-select" style="max-width:420px">
          <option value="">Seleccione proyecto...</option>
          <?php foreach (($proy_list ?? []) as $p): ?>
            <option value="<?= (int)$p['id'] ?>"<?= ($proyecto_id>0 && (int)$p['id']===$proyecto_id)?' selected':'' ?>>
              <?= htmlspecialchars($p['txt']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button id="btnIr" class="btn btn-primary" type="button">Ir</button>
      </div>
      <div class="form-text">Se mostrarán hasta 200 proyectos.</div>
    </div>
    <div class="col-lg-5 text-lg-end">
      <?php if ($proyecto): ?>
        <div class="alert alert-secondary py-2 m-0">
          <strong>Proyecto:</strong> <code><?= htmlspecialchars($proyecto['codigo']) ?></code>
          — <?= htmlspecialchars($proyecto['descripcion']) ?>
        </div>
      <?php else: ?>
        <div class="alert alert-info py-2 m-0">Seleccione un proyecto para ver avances.</div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($proyecto_id > 0): ?>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <form class="d-flex align-items-center ms-auto" method="get" action="<?= htmlspecialchars(($base?:'').'/avances/index') ?>">
      <input type="hidden" name="proyecto_id" value="<?= (int)$proyecto_id ?>">
      <div class="input-group">
        <span class="input-group-text">Desde</span>
        <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from ?? '') ?>">
        <span class="input-group-text">Hasta</span>
        <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to ?? '') ?>">
        <button class="btn btn-outline-secondary">Filtrar</button>
      </div>
    </form>
    <a class="btn btn-success" href="<?= htmlspecialchars(($base?:'').'/avances/create?proyecto_id='.$proyecto_id) ?>">
      <i class="bi bi-plus-lg"></i> Registrar avance
    </a>
  </div>

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success p-2">Operación realizada correctamente.</div>
  <?php endif; ?>
  <?php if (isset($_GET['e'])): ?>
    <div class="alert alert-danger p-2"><?= htmlspecialchars($_GET['e']) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:11rem;">Código</th>
          <th>Descripción</th>
          <th style="width:9.5rem;">Fecha</th>
          <th class="text-end">Cant. Ejecutada</th>
          <th class="text-end">Monto ejecutado</th>
          <th style="width:8rem;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= htmlspecialchars($fmt((string)$r['codigo'])) ?></code></td>
          <td><?= htmlspecialchars((string)($r['descripcion'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)$r['fecha_avance']) ?></td>
          <td class="text-end"><?= number_format((float)$r['cantidad_ejecutada'], 4, ',', '.') ?></td>
          <td class="text-end"><?= number_format((float)$r['monto_ejecutado'], 2, ',', '.') ?></td>
          <td class="text-end">
            <div class="btn-group">
              <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(($base?:'').'/avances/edit/'.$r['id']) ?>">Editar</a>
              <form method="post" action="<?= htmlspecialchars(($base?:'').'/avances/delete/'.$r['id']) ?>" onsubmit="return confirm('¿Eliminar avance?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button class="btn btn-sm btn-outline-danger">Borrar</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  const ROOT = <?= json_encode($base ?: '') ?>;

  function parseMaybeJSON(text) {
    try { return JSON.parse(text); } catch(e) {
      const s = String(text).trim();
      const i1 = s.indexOf('['), j1 = s.lastIndexOf(']');
      const i2 = s.indexOf('{'), j2 = s.lastIndexOf('}');
      let sub = '';
      if (i1 >= 0 && j1 > i1) sub = s.slice(i1, j1+1);
      else if (i2 >= 0 && j2 > i2) sub = s.slice(i2, j2+1);
      if (sub) return JSON.parse(sub);
      throw e;
    }
  }
  async function fetchFirstOK(urls){
    for (const u of urls) {
      try {
        const r = await fetch(u, {headers: {'X-Requested-With':'XMLHttpRequest'}});
        if (!r.ok) continue;
        const t = await r.text();
        const data = parseMaybeJSON(t);
        if (Array.isArray(data)) return data;
      } catch(e) {}
    }
    throw new Error('No se pudo obtener JSON válido.');
  }
  function urlsAjax(q){
    const qs = q ? ('?q='+encodeURIComponent(q)) : '';
    return [
      ROOT + '/presupuestos/ajaxproyectos' + qs,
      ROOT + '/index.php/presupuestos/ajaxproyectos' + qs,
      ROOT + '/index.php?controller=presupuestos&action=ajaxproyectos' + (qs?('&'+qs.substring(1)):''),
      ROOT + '/?controller=presupuestos&action=ajaxproyectos' + (qs?('&'+qs.substring(1)):''),
    ].filter((u,i,a)=> u && a.indexOf(u)===i);
  }

  const q = document.getElementById('qProyecto');
  const btnBuscar = document.getElementById('btnBuscar');
  const sel = document.getElementById('selProyecto');
  const btnIr = document.getElementById('btnIr');

  btnBuscar.addEventListener('click', async ()=>{
    try{
      const data = await fetchFirstOK(urlsAjax(q.value.trim()));
      const head = document.createElement('option');
      head.value = ''; head.textContent = 'Seleccione proyecto...';
      sel.innerHTML = ''; sel.appendChild(head);
      data.forEach(x=> sel.add(new Option(x.txt || ('Proyecto #'+x.id), x.id)));
    }catch(e){
      console.warn('Búsqueda de proyectos falló; se mantiene la lista precargada.');
    }
  });
  q.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); btnBuscar.click(); } });

  btnIr.addEventListener('click', ()=>{
    const v = sel.value; if(!v) return;
    location.href = (ROOT||'') + '/avances/index?proyecto_id=' + encodeURIComponent(v);
  });
})();
</script>
