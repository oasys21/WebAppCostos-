<?php
declare(strict_types=1);
/** @var array $g */
/** @var array $usuarios */
/** @var bool|null $isOwner */
/** @var bool|null $isDest */
/** @var bool|null $isAdm */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';

$editing = !empty($g['id']);
$action  = $editing ? ($base.'/gestion/actualizar/'.(int)$g['id']) : ($base.'/gestion/guardar');

/* Reglas:
   - Owner: fecha_termino, valor_asignados, text_asignados, text_tarea, estado_gestion (y usuario_destino al crear).
   - Destino: text_respuesta, fecha_propuesta, text_requeridos, valor_requeridos, estado_gestion.
   - Admin: todo.
*/
$ownerCan  = ($isAdm ?? false) || ($isOwner ?? (!$editing));
$destCan   = ($isAdm ?? false) || ($isDest  ?? false);

$ng = $g['numero_gestion'] ?? '(auto)';
?>
 <style>
body{padding-top:4.5rem; 	background-image: url(<?= htmlspecialchars($base) ?>/public/images/fondoverde3.jpg); background-color: transparent;	background-repeat: repeat;}	}
</style>
<div class="modal fade show" id="gestModal" tabindex="-1" style="display:block;" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-xxl modal-dialog-centered">
    <div class="modal-content">
      <style>
        #gestModal .modal-dialog{ width:96vw; max-width:96vw; margin:1.25rem auto; }
        @media (min-width:1400px){ #gestModal .modal-dialog{ max-width:1350px; } }
        #gestModal .modal-content{ height:84vh; display:flex; flex-direction:column; }
        #gestModal .modal-body{ overflow:auto; padding-bottom:.75rem; }
        #gestModal .card-body{ padding:.75rem; }
        #gestModal .form-label{ margin-bottom:.25rem;}
      </style>
      <form method="post" action="<?= $h($action) ?>" autocomplete="off" id="frm-gest">
        <div class="modal-header" >
          <h5 class="modal-title"><?= $editing ? 'Editar Gestión' : 'Nueva Gestión' ?></h5>
          <a href="<?= $h($base) ?>/gestion" class="btn-close"></a>
        </div>
        <div class="modal-body" style="background-color: LightSteelBlue ;">
          <div class="row g-2 mb-2">
            <div class="col-sm-3">
              <label class="form-label mb-1">N° Gestión</label>
              <input type="text" class="form-control form-control-sm" value="<?= $h($ng) ?>" disabled>
            </div>
            <div class="col-sm-3">
              <label class="form-label mb-1">Estado</label>
              <select name="estado_gestion" class="form-select form-select-sm" <?= (($ownerCan || $destCan) || ($isAdm??false)) ? '' : 'disabled' ?>>
                <?php foreach(['pendiente','realizada','cerrada','anulada'] as $e): ?>
                  <option value="<?= $h($e) ?>" <?= ((string)($g['estado_gestion'] ?? '')===$e)?'selected':'' ?>><?= $h(ucfirst($e)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-3">
            <!-- ASIGNADOR -->
            <div class="col-lg-6">
              <div class=" h-100">
                <div class="card-header py-2"><strong>Asignador (quien solicita)</strong></div>
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-12">
                      <label class="form-label mb-1">Usuario origen</label>
                      <select name="usuario_origen" class="form-select form-select-sm" <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?>>
                        <?php foreach(($usuarios ?? []) as $u): ?>
                          <option value="<?= (int)$u['id'] ?>" <?= ((string)($g['usuario_origen'] ?? '')===(string)$u['id'])?'selected':'' ?>>
                            <?= $h($u['nombre']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-sm-6">
                      <label class="form-label mb-1">Fecha solicitud</label>
                      <input type="text" name="fecha_solicitud" class="form-control form-control-sm date-dmy"
                             value="<?= $h($g['fecha_solicitud'] ?? '') ?>" placeholder="dd/mm/aaaa"
                             <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?> required>
                    </div>
                    <div class="col-sm-6">
                      <label class="form-label mb-1">Fecha término</label>
                      <input type="text" name="fecha_termino" class="form-control form-control-sm date-dmy"
                             value="<?= $h($g['fecha_termino'] ?? '') ?>" placeholder="dd/mm/aaaa"
                             <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?>>
                    </div>

                    <div class="col-sm-6">
                      <label class="form-label mb-1">Deriva de (ID gestión)</label>
                      <input type="number" name="deriva_gestion" class="form-control form-control-sm"
                             value="<?= $h($g['deriva_gestion'] ?? '') ?>"
                             <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-sm-6"></div>

                    <div class="col-12">
                      <label class="form-label mb-1">Tarea (detalle de la solicitud)</label>
                      <textarea name="text_tarea" rows="8" class="form-control form-control-sm"
                                <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?>><?= $h($g['text_tarea'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-8">
                      <label class="form-label mb-1">Asignados (texto)</label>
                      <textarea name="text_asignados" rows="4" class="form-control form-control-sm"
                                <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?>><?= $h($g['text_asignados'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label mb-1">Valor asignados</label>
                      <input type="text" name="valor_asignados" class="form-control form-control-sm money"
                             inputmode="decimal" pattern="[0-9\.,]*"
                             value="<?= $h($g['valor_asignados'] ?? '0,00') ?>"
                             <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?>>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- ASIGNADO -->
            <div class="col-lg-6">
              <div class=" h-100">
                <div class="card-header py-2"><strong>Asignado (quien responde)</strong></div>
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-12">
                      <label class="form-label mb-1">Usuario destino</label>
                      <select name="usuario_destino" class="form-select form-select-sm"
                              <?= ($ownerCan || ($isAdm??false)) ? '' : 'disabled' ?> required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach(($usuarios ?? []) as $u): ?>
                          <option value="<?= (int)$u['id'] ?>" <?= ((string)($g['usuario_destino'] ?? '')===(string)$u['id'])?'selected':'' ?>>
                            <?= $h($u['nombre']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-sm-6">
                      <label class="form-label mb-1">Fecha propuesta</label>
                      <input type="text" name="fecha_propuesta" class="form-control form-control-sm date-dmy"
                             value="<?= $h($g['fecha_propuesta'] ?? '') ?>" placeholder="dd/mm/aaaa"
                             <?= ($destCan || ($isAdm??false)) ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-sm-6"></div>

                    <div class="col-12">
                      <label class="form-label mb-1">Respuesta (detalle)</label>
                      <textarea name="text_respuesta" rows="8" class="form-control form-control-sm"
                                <?= ($destCan || ($isAdm??false)) ? '' : 'disabled' ?>><?= $h($g['text_respuesta'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-8">
                      <label class="form-label mb-1">Requeridos (texto)</label>
                      <textarea name="text_requeridos" rows="4" class="form-control form-control-sm"
                                <?= ($destCan || ($isAdm??false)) ? '' : 'disabled' ?>><?= $h($g['text_requeridos'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label mb-1">Valor requeridos</label>
                      <input type="text" name="valor_requeridos" class="form-control form-control-sm money"
                             inputmode="decimal" pattern="[0-9\.,]*"
                             value="<?= $h($g['valor_requeridos'] ?? '0,00') ?>"
                             <?= ($destCan || ($isAdm??false)) ? '' : 'disabled' ?>>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div><!-- /row -->
        </div><!-- /modal-body -->
        <div class="modal-footer">
          <a href="<?= $h($base) ?>/gestion" class="btn btn-secondary">Volver</a>
          <?php if ($editing): ?>
            <a class="btn btn-primary" target="_blank" href="<?= $h($base) ?>/gestion/ver/<?= (int)$g['id'] ?>">Imprimir</a>
          <?php endif; ?>
          <?php if (!$editing || $ownerCan || $destCan || ($isAdm??false)): ?>
            <button class="btn btn-success" type="submit">Guardar</button>
          <?php endif; ?>
		  <?php if ($editing): ?>
      <form action="<?= $h($base) ?>/gestion/destroy/<?= (int)$g['id'] ?>" method="post" class="d-inline mx-3 mb-3" onsubmit="return confirm('¿Eliminar esta gestión? Solo el creador o ADMIN pueden hacerlo.');">
        <button type="submit" class="btn btn-danger">Eliminar</button>
      </form>
      <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// dd/mm/aaaa (solo guía visual, no bloquea)
document.querySelectorAll('.date-dmy').forEach(el=>{
  el.addEventListener('input', e=>{
    let v = e.target.value.replace(/[^\d]/g,'').slice(0,8);
    if (v.length>=5) v = v.slice(0,2)+'/'+v.slice(2,4)+'/'+v.slice(4);
    else if (v.length>=3) v = v.slice(0,2)+'/'+v.slice(2);
    e.target.value = v;
  });
});

// ====== Money sin salto de cursor ======
// Estrategia: NO re-formatear en 'input' (solo saneo). Formatear LATAM en 'blur'.
function parseNumberLoose(s){
  if(!s) return 0;
  s = String(s).replace(/[^0-9,.\-]/g,'');
  // Si hay coma y punto, asumimos coma decimal si la coma aparece después del punto final
  // En la práctica, para LATAM tomamos la última coma o punto como separador decimal
  const lastComma = s.lastIndexOf(',');
  const lastDot   = s.lastIndexOf('.');
  let decPos = Math.max(lastComma, lastDot);
  if (decPos >= 0){
    let intPart = s.slice(0, decPos).replace(/[^\d\-]/g,'');
    let decPart = s.slice(decPos+1).replace(/[^0-9]/g,'').slice(0,2);
    return parseFloat((intPart || '0') + '.' + (decPart || '0')) || 0;
  }
  // solo enteros
  return parseFloat(s.replace(/[^\d\-]/g,'')) || 0;
}
function formatLatam(n){
  n = Number(n)||0;
  const entero = Math.trunc(Math.abs(n));
  const sign = n<0 ? '-' : '';
  let entStr = String(entero).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  let decStr = String(Math.round((Math.abs(n) - entero)*100)).padStart(2,'0');
  return sign + entStr + ',' + decStr;
}

document.querySelectorAll('input.money').forEach(el=>{
  // saneo sin mover caret
  el.addEventListener('input', ev=>{
    const start = ev.target.selectionStart;
    const end   = ev.target.selectionEnd;
    const before = ev.target.value;
    const after  = before.replace(/[^0-9,.\-]/g,''); // quitamos caracteres no válidos
    if (before !== after){
      ev.target.value = after;
      // restaurar caret lo mejor posible
      const shift = before.length - after.length;
      ev.target.setSelectionRange(Math.max(0,(start-shift)), Math.max(0,(end-shift)));
    }
  });
  // formateo al salir
  el.addEventListener('blur', ev=>{
    const n = parseNumberLoose(ev.target.value);
    ev.target.value = formatLatam(n);
  });
  // opcional: seleccionar todo al focus para sobreescribir rápido
  el.addEventListener('focus', ev=>{
    setTimeout(()=>{ ev.target.select(); }, 0);
  });
});
</script>
