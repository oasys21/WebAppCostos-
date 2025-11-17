<?php
declare(strict_types=1);
/** @var array $g */
/** @var string $nomO */
/** @var string $nomD */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';

function fmtMoney($n){ return number_format((float)$n, 2, ',', '.'); }

$ng = $g['numero_gestion'] ?? '';
?>
<div class="modal fade show" id="gestShow" tabindex="-1" style="display:block; background:transparent;" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-xxl modal-dialog-centered">
    <div class="modal-content">
      <style>
        #gestShow .modal-dialog{ width:96vw; max-width:96vw; margin:1.25rem auto; }
        @media (min-width:1400px){ #gestShow .modal-dialog{ max-width:1350px; } }
        #gestShow .modal-content{ height:84vh; display:flex; flex-direction:column; }
        #gestShow .modal-body{ overflow:auto; padding-bottom:.75rem; }
        #gestShow .card-body{ padding:.75rem; }
        #gestShow .form-label{ margin-bottom:.25rem; }
        #gestShow .form-control[readonly], #gestShow .form-select[disabled], #gestShow textarea[readonly]{
          background:#f8f9fa;
        }
      </style>

      <div class="modal-header">
        <h5 class="modal-title">Gestión #<?= $h($ng) ?></h5>
        <a href="<?= $h($base) ?>/gestion" class="btn-close"></a>
      </div>

      <div class="modal-body" style="background-color: LightSteelBlue ;">
        <div class="row g-2 mb-2">
          <div class="col-sm-3">
            <label class="form-label mb-1">N° Gestión</label>
            <input type="text" class="form-control form-control-sm" value="<?= $h($ng) ?>" readonly>
          </div>
          <div class="col-sm-3">
            <label class="form-label mb-1">Estado</label>
            <input type="text" class="form-control form-control-sm" value="<?= $h(ucfirst((string)($g['estado_gestion'] ?? ''))) ?>" readonly>
          </div>
        </div>

        <div class="row g-3">
          <!-- ASIGNADOR -->
          <div class="col-lg-6">
            <div class=" h-100">
              <div class="card-header py-1"></div>
              <div class="card-body">
                <div class="row g-2">
                  <div class="col-12">
                    <label class="form-label mb-1">Usuario origen</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h($nomO) ?>" readonly>
                  </div>

                  <div class="col-sm-6">
                    <label class="form-label mb-1">Fecha solicitud</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h($g['fecha_solicitud'] ?? '') ?>" readonly>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label mb-1">Fecha término</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h($g['fecha_termino'] ?? '') ?>" readonly>
                  </div>

                  <div class="col-sm-6">
                    <label class="form-label mb-1">Deriva de (ID gestión)</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h($g['deriva_gestion'] ?? '') ?>" readonly>
                  </div>
                  <div class="col-sm-6"></div>

                  <div class="col-12">
                    <label class="form-label mb-1">Tarea (detalle de la solicitud)</label>
                    <textarea rows="8" class="form-control form-control-sm" readonly><?= $h($g['text_tarea'] ?? '') ?></textarea>
                  </div>

                  <div class="col-md-8">
                    <label class="form-label mb-1">Asignados (texto)</label>
                    <textarea rows="4" class="form-control form-control-sm" readonly><?= $h($g['text_asignados'] ?? '') ?></textarea>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label mb-1">Valor asignados</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h(fmtMoney($g['valor_asignados'] ?? 0)) ?>" readonly>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ASIGNADO -->
          <div class="col-lg-6">
            <div class=" h-100">
              <div class="card-body">
                <div class="row g-2">
                  <div class="col-12">
                    <label class="form-label mb-1">Usuario destino</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h($nomD) ?>" readonly>
                  </div>

                  <div class="col-sm-6">
                    <label class="form-label mb-1">Fecha propuesta</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h($g['fecha_propuesta'] ?? '') ?>" readonly>
                  </div>
                  <div class="col-sm-6"></div>

                  <div class="col-12">
                    <label class="form-label mb-1">Respuesta (detalle)</label>
                    <textarea rows="8" class="form-control form-control-sm" readonly><?= $h($g['text_respuesta'] ?? '') ?></textarea>
                  </div>

                  <div class="col-md-8">
                    <label class="form-label mb-1">Requeridos (texto)</label>
                    <textarea rows="4" class="form-control form-control-sm" readonly><?= $h($g['text_requeridos'] ?? '') ?></textarea>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label mb-1">Valor requeridos</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $h(fmtMoney($g['valor_requeridos'] ?? 0)) ?>" readonly>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div><!-- /row -->
      </div><!-- /modal-body -->

      <div class="modal-footer">
        <a href="<?= $h($base) ?>/gestion" class="btn btn-outline-secondary">Volver</a>
        <a href="<?= $h($base) ?>/gestion/editar/<?= (int)$g['id'] ?>" class="btn btn-success">Editar</a>
      </div>
    </div>
  </div>
</div>
