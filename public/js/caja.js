/* public/js/caja.js
 * Pobla selects Proyecto / Ítem de costo y maneja formato LATAM del monto.
 * Requiere jQuery 3.x cargado antes.
 */
(function (window, document) {
  'use strict';

  // ======= Verificar jQuery =======
  if (!window.jQuery) {
    console.error('[caja.js] jQuery no está cargado. Debes incluirlo antes de caja.js');
    return;
  }
  var $ = window.jQuery;

  // =========================
  // Descubrir BASE y Endpoints
  // =========================
  function detectarBase() {
    // 1) data-base en el contenedor
    var el = document.querySelector('[data-base]');
    if (el && el.getAttribute('data-base')) {
      return String(el.getAttribute('data-base')).replace(/\/+$/, '');
    }
    // 2) Heurística clásica para DEV
    if (location.pathname.indexOf('/costos/') === 0) return '/costos';
    // 3) Producción raíz
    return '';
  }
  var BASE = detectarBase();

  // Permite sobreescribir desde la vista con:
  // window.CAJA_API_PROY  = '...';
  // window.CAJA_API_ITEMS = '...';
  var API_PROY  = window.CAJA_API_PROY  || (BASE + '/caja/apiproyectos');
  var API_ITEMS = window.CAJA_API_ITEMS || (BASE + '/caja/apiproyectoitems');

  // Construye lista de fallbacks por si la URL relativa falla según dónde esté montado
  function buildUrlFallbacks(url) {
    var out = [];
    if (!url) return out;
    var abs = /^https?:\/\//i.test(url) || url.indexOf('/') === 0;
    if (abs) {
      out.push(url);
    } else {
      // relativo a la página actual
      out.push(url);
      // relativo a BASE
      if (BASE) out.push(BASE.replace(/\/+$/, '') + '/caja/' + url.replace(/^\/+/, ''));
      // fallback explícito /costos
      out.push('/costos/caja/' + url.replace(/^\/+/, ''));
      // raíz /caja (PROD)
      out.push('/caja/' + url.replace(/^\/+/, ''));
    }
    // desduplicar manteniendo orden
    var seen = Object.create(null), fin = [];
    for (var i = 0; i < out.length; i++) {
      var k = out[i];
      if (!seen[k]) { seen[k] = 1; fin.push(k); }
    }
    return fin;
  }

  function getJSONfallback(urlOrList, data) {
    var urls = Array.isArray(urlOrList) ? urlOrList : buildUrlFallbacks(urlOrList);
    var dfd = $.Deferred();
    (function tryNext(i) {
      if (i >= urls.length) { dfd.reject({ error: 'all_failed', urls: urls }); return; }
      $.getJSON(urls[i], data || {})
        .done(function (r) { dfd.resolve(r); })
        .fail(function () { tryNext(i + 1); });
    })(0);
    return dfd.promise();
  }

  // =========================
  // Formato LATAM (monto)
  // =========================
  function onlyDigits(s) { return (s || '').replace(/[^\d]/g, ''); }
  function fmtMilesLatamInt(s) {
    s = onlyDigits(s);
    if (!s) return '';
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function bindMoneyInputs() {
    var $inputs = $('.js-money-latam');
    if (!$inputs.length) return;

    $inputs.addClass('text-end');

    // Formateo inicial
    $inputs.each(function () {
      var v = $(this).val();
      if (v && /\d/.test(v)) $(this).val(fmtMilesLatamInt(v));
    });

    // Formateo en tiempo real conservando el caret
    $inputs.on('input', function () {
      var posFromEnd = this.value.length - (this.selectionStart || 0);
      this.value = fmtMilesLatamInt(this.value);
      var pos = this.value.length - Math.min(posFromEnd, this.value.length);
      if (this.setSelectionRange) this.setSelectionRange(pos, pos);
    });

    $inputs.on('blur', function () {
      this.value = fmtMilesLatamInt(this.value);
    });

    // Al enviar formulario, enviar sólo dígitos (sin comas)
    $('form').on('submit', function () {
      $(this).find('.js-money-latam').each(function () {
        this.value = onlyDigits(this.value);
      });
    });
  }

  // =========================
  // Poblar selects Proyecto / Ítem
  // =========================
  function renderOptions($sel, rows, valueKey, textKey, firstText) {
    $sel.empty();
    $sel.append($('<option>', { value: '', text: firstText || 'Seleccione...' }));
    if (Array.isArray(rows)) {
      rows.forEach(function (r) {
        $sel.append($('<option>', { value: r[valueKey], text: r[textKey] }));
      });
    }
  }

  function findSelects() {
    var $proy = $('#proyecto');
    var $item = $('#item');
    if (!$proy.length) $proy = $('select[name="proyecto_id"]');
    if (!$item.length) $item = $('select[name="proyecto_costo_id"]');
    return { $proy: $proy, $item: $item };
  }

  function cargarProyectos(term) {
    return getJSONfallback([API_PROY], { term: term || '' }).then(function (rows) {
      // Normalizar
      rows = Array.isArray(rows) ? rows : [];
      return rows.map(function (r) {
        return {
          id: parseInt(r.id, 10),
          nombre: String(r.nombre || r.codigo_proy || ('Proyecto ' + r.id))
        };
      });
    });
  }

  function cargarItems(pid, term) {
    pid = parseInt(pid || 0, 10);
    if (!pid) return $.Deferred().resolve([]).promise();
    return getJSONfallback([API_ITEMS], { proyecto_id: pid, term: term || '' }).then(function (rows) {
      rows = Array.isArray(rows) ? rows : [];
      return rows.map(function (r) {
        var id = parseInt(r.proyecto_costo_id || r.id, 10);
        var label = r.label || (String(r.codigo || '') + ' - ' + String(r.glosa || ''));
        return { proyecto_costo_id: id, label: label };
      });
    });
  }

  function initCombos() {
    var c = findSelects();
    var $proy = c.$proy, $item = c.$item;
    if (!$proy.length || !$item.length) return;

    var selectedProy = parseInt($proy.data('selected') || '0', 10) || null;
    var selectedItem = parseInt($item.data('selected') || '0', 10) || null;

    $proy.prop('disabled', true);
    renderOptions($proy, [], 'id', 'nombre', 'Cargando proyectos...');
    cargarProyectos('')
      .done(function (rows) {
        renderOptions($proy, rows, 'id', 'nombre', 'Seleccione proyecto...');
        if (selectedProy) $proy.val(String(selectedProy));
        $proy.prop('disabled', false);

        var pid = parseInt($proy.val() || '0', 10);
        if (pid) {
          $item.prop('disabled', true);
          renderOptions($item, [], 'proyecto_costo_id', 'label', 'Cargando ítems...');
          cargarItems(pid, '')
            .done(function (r2) {
              renderOptions($item, r2, 'proyecto_costo_id', 'label', 'Seleccione ítem...');
              if (selectedItem) $item.val(String(selectedItem));
              $item.prop('disabled', false);
            })
            .fail(function () {
              renderOptions($item, [], 'proyecto_costo_id', 'label', 'Error al cargar ítems');
              $item.prop('disabled', true);
              console.error('[caja.js] Error AJAX ítems');
            });
        } else {
          $item.prop('disabled', true);
          renderOptions($item, [], 'proyecto_costo_id', 'label', 'Seleccione proyecto primero...');
        }
      })
      .fail(function () {
        renderOptions($proy, [], 'id', 'nombre', 'Error al cargar proyectos');
        $proy.prop('disabled', true);
        $item.prop('disabled', true);
        console.error('[caja.js] Error AJAX proyectos');
      });

    // Cambio de proyecto => recargar ítems
    $proy.on('change', function () {
      var pid = parseInt($(this).val() || '0', 10);
      if (!pid) {
        renderOptions($item, [], 'proyecto_costo_id', 'label', 'Seleccione proyecto primero...');
        $item.prop('disabled', true);
        return;
      }
      $item.prop('disabled', true);
      renderOptions($item, [], 'proyecto_costo_id', 'label', 'Cargando ítems...');
      cargarItems(pid, '')
        .done(function (r2) {
          renderOptions($item, r2, 'proyecto_costo_id', 'label', 'Seleccione ítem...');
          $item.prop('disabled', false);
        })
        .fail(function () {
          renderOptions($item, [], 'proyecto_costo_id', 'label', 'Error al cargar ítems');
          $item.prop('disabled', true);
          console.error('[caja.js] Error AJAX ítems');
        });
    });
  }

  // =========================
  // Filtros en index (si existen)
  // =========================
  function bindIndexFilters() {
    var $btn = $('#btn-filtrar'), $frm = $('#frm-filtros');
    if ($btn.length && $frm.length) {
      $btn.on('click', function (ev) {
        ev.preventDefault();
        $frm.trigger('submit');
      });
    }
  }

  // =========================
  // Init
  // =========================
  $(function () {
    bindMoneyInputs();
    initCombos();
    bindIndexFilters();
  });

})(window, document);
