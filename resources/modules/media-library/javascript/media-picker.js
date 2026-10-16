/*!
 * MediaPicker (final) - modal selector (single + multiple) using MediaLibrary
 * - No repite lógica de listado/filtrado/paginación: eso lo hace MediaLibrary
 *
 * Requiere:
 *  - jQuery
 *  - Bootstrap 5 (Modal)
 *  - mediaLibrary.js (window.MediaLibrary)
 *  - Un modal con id #mediaPickerModal y un contenedor [data-ml-mount] o #mp-ml-mount
 *
 * API:
 *   MediaPicker.open({
 *     multiple: false,           // default false
 *     max: 20,                   // solo si multiple=true
 *     selected: [1,2,3],         // ids preseleccionados (opcional)
 *     tenantID: 12,              // opcional: tenant explicito para listar/subir
 *     title: '...',              // opcional
 *     insertText: 'Usar',        // opcional
 *     kind: 'images',          // opcional: images|docs|all
 *     saveSource: 'products',  // opcional: destino de guardado en upload
*     maxFileMB: 8,            // opcional: límite MB por archivo al subir (0 = sin límite)
 *   }).then(result => { ... })
 *
 * Result:
 *  - si cancelas: null
 *  - single:   { multiple:false, ids:[id], items:[item], item:item }
 *  - multiple: { multiple:true,  ids:[...], items:[...] }
 */
(function (window, $) {
  "use strict";

  if (!window.jQuery) return;

  if (typeof window.MediaLibrary !== "function") {
    console.error("[MediaPicker] falta MediaLibrary.js");
    return;
  }

  var $modal = $("#mediaPickerModal");
  if (!$modal.length) {
    console.error("[MediaPicker] falta #mediaPickerModal");
    return;
  }

  // Mount: recomendado [data-ml-mount] dentro del modal.
  // Fallback: #mp-ml-mount
  var $mount = $modal.find("[data-ml-mount]").first();
  if (!$mount.length) $mount = $modal.find("#mp-ml-mount").first();

  if (!$mount.length) {
    console.error("[MediaPicker] falta contenedor [data-ml-mount] (o #mp-ml-mount) dentro del modal");
    return;
  }

  function resolveTokensForm(candidate) {
    var helper = window.MediaLibrary && typeof window.MediaLibrary.resolveTokensForm === "function"
      ? window.MediaLibrary.resolveTokensForm
      : null;

    if (helper) return helper(candidate);

    var $candidate = $();
    if (candidate && candidate.jquery) {
      $candidate = candidate.first();
    } else if (candidate) {
      $candidate = $(candidate).first();
    }
    if ($candidate.length) return $candidate;

    var $byId = $("#tokens").first();
    if ($byId.length) return $byId;

    var $byFlag = $("form[data-ml-tokens]").first();
    if ($byFlag.length) return $byFlag;

    return $();
  }

  var tokensForm = resolveTokensForm();

var bsModal = bootstrap.Modal.getOrCreateInstance($modal[0]);
 

  // Instancia única de MediaLibrary para el modal
  var lib = $mount.data("mediaLibrary");
  if (!lib) {
    lib = new window.MediaLibrary({
      mount: $mount,
      uiRoot: $modal,
      tokens: tokensForm,
      mode: "picker",
      syncUrlEnabled: false,
      listenPopState: false
    });

    // Upload dentro del modal (tab "Subir")
    lib.attachUpload();

    $mount.data("mediaLibrary", lib);
  } else {
    lib.setMode("picker");
  }
  var endpointBase = typeof site_url === 'string' ? site_url : '';
  var defaultEndpoints = $.extend({
    details: endpointBase + 'ajax/admin/media/details',
    save: endpointBase + 'ajax/admin/media/save',
    field: endpointBase + 'ajax/admin/media/field'
  }, lib.endpoints || {});

  // Estado picker
  var state = {
    resolve: null,
    didInsert: false,
    multiple: false,
    max: 20,
    selected: new Map(), // id => item (puede ser placeholder {id})
    title: null,
    insertText: null,
    allowEmpty: false,
    kind: 'all',
    session: 0,
    active: null,
    dirty: false,
    saving: false,
    selectionPending: false,
    uploadHydrating: false,
    closing: false,
    replaceSelection: false
  };
  var detailSequence = 0;
  var selectionSequence = 0;
  var $details = $modal.find('[data-mp-details-form]');
  var $status = $modal.find('[data-mp-details-status]');
  var messages = {};
  try { messages = JSON.parse($modal.find('[data-mp-messages]').text() || '{}'); } catch (error) {}
  function message(key) { return String(messages[key] || ''); }

  function request(action, data) {
    var payload = {};
    resolveTokensForm(tokensForm).serializeArray().forEach(function(token) { payload[token.name] = token.value; });
    return $.ajax({url: lib.endpoints[action], type: 'POST', dataType: 'json', data: $.extend(payload, data)});
  }

  function normalize(item) {
    return $.extend({}, item, {
      id: toInt(item.media_id || item.id),
      url: item.media_url || item.url || '',
      is_image: (item.type || item.kind) === 'images'
    });
  }

  function mayLeaveDetails() {
    if (state.saving) { $status.text(message('wait_save')).trigger('focus'); return false; }
    if (state.dirty) { $status.text(message('dirty')).trigger('focus'); return false; }
    return true;
  }

  function fillDetails(item) {
    state.active = item;
    state.dirty = false;
    $details.find(':input').prop('disabled', false);
    $modal.find('[data-mp-details-empty]').addClass('d-none');
    $details.removeClass('d-none');
    $details.find('[data-mp-name]').val(item.original_name || item.name || '');
    $details.find('[data-mp-alt]').val(item.alt_text || '').prop('disabled', item.type !== 'images');
    $modal.find('#mp-file-url').val(item.media_url || '');
    $details.find('[data-mp-preview]').toggleClass('d-none', item.type !== 'images').attr('src', item.type === 'images' ? item.media_url : '').attr('alt', item.alt_text || '');
    var meta = item.metadata || {};
    if (!Object.keys(meta).length) { try { meta = JSON.parse(item.meta_json || '{}'); } catch (error) {} }
    var original = meta.original || meta;
    var dimensions = original.width && original.height ? ' · ' + original.width + ' × ' + original.height : '';
    $details.find('[data-mp-file-info]').text((item.name || '') + ' · ' + (item.mime_type || '') + ' · ' + (item.size_bytes || 0) + ' bytes' + dimensions);
    $status.text('');
  }

  function showDetails(id) {
    var session = state.session;
    var sequence = ++detailSequence;
    state.active = null;
    $details.find(':input').prop('disabled', true);
    $details.addClass('d-none');
    $status.text(message('loading_details'));
    request('details', {media_id: id}).done(function(response) {
      if (session !== state.session || sequence !== detailSequence) return;
      if (response.status !== 'success') { $status.text(response.message || message('details_failed')); return; }
      fillDetails(normalize(response.data));
    }).fail(function() {
      if (session === state.session && sequence === detailSequence) $status.text(message('details_failed'));
    });
  }

  function renderSelection(focusID, direction) {
    var ids = Array.from(state.selected.keys());
    var session = state.session;
    var sequence = ++selectionSequence;
    state.selectionPending = false;
    $modal.find('[data-mp-selection]').toggleClass('d-none', !state.multiple || !ids.length);
    if (!state.multiple || !ids.length) {
      $modal.find('[data-mp-selection-list]').empty();
      setFooter();
      return;
    }
    state.selectionPending = true;
    $modal.find('#mp-insert').prop('disabled', true);
    request('field', {media_ids: JSON.stringify(ids), variant: 'selection'}).done(function(response) {
      if (session !== state.session || sequence !== selectionSequence) return;
      if (response.status !== 'success') { $status.text(response.message || message('selection_failed')); return; }
      var $list = $modal.find('[data-mp-selection-list]').html(response.html || '');
      if ((response.data || []).length !== ids.length) { $status.text(message('selection_unavailable')); return; }
      (response.data || []).forEach(function(item) { item = normalize(item); if (state.selected.has(item.id)) state.selected.set(item.id, item); });
      $list.find('li').first().find('[data-mp-move="-1"]').prop('disabled', true);
      $list.find('li').last().find('[data-mp-move="1"]').prop('disabled', true);
      state.selectionPending = false;
      setFooter();
      if (focusID) {
        var $item = $list.find('[data-mp-selection-id="' + focusID + '"]');
        var $focus = direction ? $item.find('[data-mp-move="' + direction + '"]:enabled') : $item.find('[data-mp-detail]');
        ($focus.length ? $focus : $item.find('[data-mp-detail]')).trigger('focus');
      }
    }).fail(function() {
      if (session === state.session && sequence === selectionSequence) $status.text(message('selection_retry'));
    });
  }

  function syncSelection(focusID, direction) {
    highlightSelected();
    setFooter();
    renderSelection(focusID, direction);
  }

  $details.on('input', '[data-mp-name], [data-mp-alt]', function() { state.dirty = true; $status.text(message('unsaved')); });
  $details.on('keydown', '[data-mp-name]', function(event) {
    if (event.key === 'Enter') { event.preventDefault(); $details.find('[data-mp-save]').trigger('click'); }
  });
  $modal.on('click.mediaPickerDetails', '[data-mp-discard]', function() { if (!state.saving && state.active) fillDetails(state.active); });
  $details.on('click', '[data-mp-save]', function(event) {
    event.preventDefault();
    if (!state.active || state.saving || !$details.find('[data-mp-name]')[0].reportValidity()) return;
    state.saving = true;
    $details.find(':input').prop('disabled', true);
    $status.text(message('saving'));
    var item = state.active;
    var data = {media_id: item.id, original_name: $details.find('[data-mp-name]').val(), alt_text: $details.find('[data-mp-alt]').val()};
    request('save', data).done(function(response) {
      if (response.status !== 'success') { $status.text(response.message || message('save_denied')); return; }
      item = response.data ? normalize(response.data) : $.extend({}, item, {original_name: data.original_name, alt_text: data.alt_text});
      fillDetails(item);
      if (state.selected.has(item.id)) state.selected.set(item.id, item);
      $status.text(message('saved'));
      renderSelection();
      lib.load(lib.state.page || 1, {push: false, makeActive: true});
    }).fail(function() { $status.text(message('save_failed')); })
      .always(function() {
        state.saving = false;
        $details.find(':input').prop('disabled', false);
        $details.find('[data-mp-alt]').prop('disabled', !state.active || state.active.type !== 'images');
      });
  });

  $modal.on('click.mediaPickerComposition', '[data-mp-detail]', function() {
    if (mayLeaveDetails()) showDetails(toInt($(this).attr('data-mp-detail')));
  });
  $modal.on('click.mediaPickerComposition', '[data-mp-remove]', function() {
    if (!mayLeaveDetails()) return;
    state.selected.delete(toInt($(this).attr('data-mp-remove')));
    state.replaceSelection = true;
    syncSelection();
  });
  $modal.on('click.mediaPickerComposition', '[data-mp-move]', function() {
    if (!mayLeaveDetails()) return;
    var id = toInt($(this).closest('[data-mp-selection-id]').attr('data-mp-selection-id'));
    var direction = toInt($(this).attr('data-mp-move'));
    var items = Array.from(state.selected.values());
    var index = items.findIndex(function(item) { return item.id === id; });
    var target = index + direction;
    if (index < 0 || target < 0 || target >= items.length) return;
    var temporary = items[index]; items[index] = items[target]; items[target] = temporary;
    state.selected = new Map(items.map(function(item) { return [item.id, item]; }));
    state.replaceSelection = true;
    syncSelection(id, direction);
  });

  $mount.on('ml:uploadComplete.mediaPicker', function(event, uploaded, context) {
    if (!state.resolve || context !== state.session || !uploaded.length) return;
    var session = state.session;
    state.uploadHydrating = true;
    setFooter();
    Promise.all(uploaded.map(function(entry) {
      var data = entry.response.data || {};
      return new Promise(function(resolve) {
        request('details', {media_id: data.media_id || data.id}).done(function(response) { resolve(response.status === 'success' ? normalize(response.data) : null); }).fail(function() { resolve(null); });
      });
    })).then(function(items) {
      if (session !== state.session || !state.resolve) return;
      state.uploadHydrating = false;
      items = items.filter(function(item) { return item && (state.kind === 'all' || state.kind === item.type); });
      if (!state.multiple && items.length) state.selected = new Map([[items[items.length - 1].id, items[items.length - 1]]]);
      else items.forEach(function(item) { if (state.selected.has(item.id) || state.selected.size < state.max) state.selected.set(item.id, item); });
      bootstrap.Tab.getOrCreateInstance($modal.find('[data-bs-target="#mp-library"]')[0]).show();
      syncSelection();
      if (items.length && mayLeaveDetails()) showDetails(items[items.length - 1].id);
      $modal.find('[data-mp-notice]').text(state.multiple && items.some(function(item) { return !state.selected.has(item.id); })
        ? message('limit')
        : (items.length !== uploaded.length ? message('incompatible') : ''));
    });
  });

  function toInt(n) {
    var x = parseInt(n, 10);
    return isNaN(x) ? 0 : x;
  }

  function uniqInt(arr) {
    var out = [];
    var seen = {};
    (arr || []).forEach(function (v) {
      var n = toInt(v);
      if (!n) return;
      if (seen[n]) return;
      seen[n] = 1;
      out.push(n);
    });
    return out;
  }


  function normKind(kind) {
    kind = (kind || "").toString().trim().toLowerCase();
    if (!kind) return "all";
    if (kind === "image") return "images";
    if (kind === "file" || kind === "files" || kind === "document" || kind === "documents") return "docs";
    if (kind === "audio" || kind === "sound") return "audios";
    if (kind === "video") return "videos";
    if (kind === "mixed") return "all";
    return kind;
  }

  function kindNouns(kind) {
    kind = normKind(kind);
    if (kind === "images") return { sing: "imagen", pl: "imágenes", gender: "f" };
    if (kind === "audios") return { sing: "audio", pl: "audios", gender: "m" };
    if (kind === "videos") return { sing: "video", pl: "videos", gender: "m" };
    return { sing: "archivo", pl: "archivos", gender: "m" };
    //return { sing: "elemento", pl: "elementos", gender: "m" };
  }

  function resolveTitle(opts) {
    var n = kindNouns(opts && opts.kind);
    return "Seleccionar " + ((opts && opts.multiple) ? n.pl : n.sing);
  }

  function resolveInsertText(opts) {
    var n = kindNouns(opts && opts.kind);
    return "Usar " + ((opts && opts.multiple) ? n.pl : n.sing);
  }


  function setFooter() {
    var $label = $("#mp-selected-label");
    var $insert = $("#mp-insert");

    if (!$label.length) $label = $modal.find("#mp-selected-label");
    if (!$insert.length) $insert = $modal.find("#mp-insert");

    var count = state.selected.size;

    if (!count) {
      if ($label.length) $label.text("Ninguna selección");
      // Permite "guardar vacío" (útil para limpiar galerías)
      if ($insert.length) $insert.prop("disabled", !state.allowEmpty || state.selectionPending || state.uploadHydrating);
      return;
    }

    if (!state.multiple) {
      var first = state.selected.values().next().value || {};
      if ($label.length) $label.text("Seleccionado: #" + (first.id || "?"));
      if ($insert.length) $insert.prop("disabled", state.selectionPending || state.uploadHydrating);
      return;
    }

    var n = kindNouns(state.kind);
    var selWord = (n.gender === "f") ? "Seleccionadas" : "Seleccionados";
    if ($label.length) $label.text(selWord + ": " + count);
    if ($insert.length) $insert.prop("disabled", state.selectionPending || state.uploadHydrating);
  }

  function highlightSelected() {
    // Marca .is-selected en las cards visibles, y rehidrata placeholders con data real del DOM
    $mount.find(".m-list-card").each(function () {
      var $card = $(this);
      var id = toInt($card.data("media-id")) || 0;
      if (!id) return;

      if (state.selected.has(id)) {
        var cur = state.selected.get(id) || { id: id };
        // si es placeholder, completamos con normalizeItem del library
        if (!cur.media_url && typeof lib.normalizeItem === "function") {
          state.selected.set(id, lib.normalizeItem($card));
        }
      }

      $card.toggleClass("is-selected", state.selected.has(id));
      $card.attr('aria-pressed', state.selected.has(id) ? 'true' : 'false');
      $card.find('[data-mp-selected]').toggleClass('d-none', !state.selected.has(id));
    });
  }

  function toggleSelect(item) {
    if (!item || !toInt(item.id)) return;

    // single
    if (!state.multiple) {
      state.selected.clear();
      state.selected.set(item.id, item);
      syncSelection();
      return;
    }

    // multiple toggle
    if (state.selected.has(item.id)) {
      state.selected.delete(item.id);
      state.replaceSelection = true;
      syncSelection();
      return;
    }

    if (state.max && state.selected.size >= state.max) {
      if (typeof alertToast === "function") {
        var n = kindNouns(state.kind);
        alertToast({ icon: "error", title: "Máximo " + state.max + " " + n.pl + "." });
      }
      return;
    }

    state.selected.set(item.id, item);
    syncSelection();
  }

  // Eventos del MediaLibrary: click item / render
  $mount.off("ml:itemClick.mediaPicker").on("ml:itemClick.mediaPicker", function (e, item) {
    if (!mayLeaveDetails()) return;
    // picker siempre captura selección
    toggleSelect(item);
    showDetails(item.id);
  });

  $mount.off("ml:render.mediaPicker").on("ml:render.mediaPicker", function () {
    highlightSelected();
    setFooter();
  });

  // Insertar
  $modal.off("click.mediaPickerInsert", "#mp-insert").on("click.mediaPickerInsert", "#mp-insert", function (e) {
    e.preventDefault();
    if (!mayLeaveDetails() || state.selectionPending || state.uploadHydrating) return;
    if (!state.selected.size && !state.allowEmpty) return;

    state.didInsert = true;

    var items = Array.from(state.selected.values());
    var ids = items.map(function (x) { return toInt(x.id); }).filter(Boolean);

    var result = { multiple: state.multiple, ids: ids, items: items, replaceSelection: state.replaceSelection };
    if (!state.multiple) result.item = items[0];

    bsModal.hide();

    // Evento global opcional
    $(document).trigger("media:selected", [result]);

    if (typeof state.resolve === "function") state.resolve(result);
    state.resolve = null;
  });

  // Cierre/cancel: resolve(null) solo si no insertó
  $modal.on('hide.bs.modal.mediaPickerDetails', function(event) {
    if (!mayLeaveDetails()) { event.preventDefault(); return; }
    state.closing = true;
    $details.find(':input').prop('disabled', true);
    state.session++;
    lib.uploadContext = state.session;
    detailSequence++;
    selectionSequence++;
    lib._loadSequence = (lib._loadSequence || 0) + 1;
  });
  $modal.off("hidden.bs.modal.mediaPicker").on("hidden.bs.modal.mediaPicker", function () {
    if (!state.didInsert && typeof state.resolve === "function") {
      state.resolve(null);
    }
    state.resolve = null;
    state.didInsert = false;
    state.closing = false;

    // limpia selección visual
    state.selected.clear();
    highlightSelected();
    setFooter();
  });
  function setModalTexts(opts) {
    opts = opts || {};

    // Título (si existe elemento)
    var $title = $modal.find("[data-mp-title]").first();
    if (!$title.length) $title = $modal.find(".modal-title").first();
    if ($title.length) $title.text(opts.title || resolveTitle(opts));

    // Texto botón insertar
    var $insert = $modal.find("#mp-insert");
    if ($insert.length) $insert.text(opts.insertText || resolveInsertText(opts));
  }


  // API pública
  window.MediaPicker = {
    open: function (opts) {
      opts = opts || {};
      if (state.closing) return new Promise(function(resolve) {
        $modal.one('hidden.bs.modal.mediaPickerReopen', function() { resolve(window.MediaPicker.open(opts)); });
      });
      if (state.resolve) return Promise.resolve(null);
      state.session++;
      lib.uploadContext = state.session;
      state.replaceSelection = false;
      state.selectionPending = false;
      state.uploadHydrating = false;
      state.active = null;
      state.dirty = false;
      $details.addClass('d-none');
      $modal.find('[data-mp-details-empty]').removeClass('d-none');
      $status.text('');
      $modal.find('[data-mp-notice]').text('');
      $modal.find('[data-ml-upload-results]').empty();
      bootstrap.Tab.getOrCreateInstance($modal.find('[data-bs-target="#mp-library"]')[0]).show();

      state.multiple = !!opts.multiple;
      state.max = Math.max(1, Math.min(50, (opts.max !== undefined) ? (toInt(opts.max) || 20) : 20));
      state.allowEmpty = (opts.allowEmpty !== undefined)
        ? !!opts.allowEmpty
        : (state.multiple ? true : false);

      // Preselección (ids)
      state.selected.clear();
      var pre = uniqInt(opts.selected || opts.selectedIds || []).slice(0, state.multiple ? state.max : 1);
      pre.forEach(function (id) {
        state.selected.set(id, { id: id }); // placeholder hasta que aparezca en el DOM
      });

      state.didInsert = false;
      state.kind = normKind(opts.kind);

      lib.endpoints = $.extend({}, defaultEndpoints, opts.endpoints || {});

      setModalTexts({ multiple: state.multiple, kind: state.kind, title: opts.title, insertText: opts.insertText });
// Rehidrata tokens (por si el JS se cargó antes de que existiera #tokens en el DOM)
try { lib.$tokens = resolveTokensForm(tokensForm); } catch(e) {}

// Configuración por contexto (desde MediaField):
// - kind: qué se muestra/acepta en el picker (images/docs/all)
// - saveSource: dónde guardar cuando subes desde el modal (NO afecta el filtro "Origen")
      if (opts.kind !== undefined && opts.kind !== null && (opts.kind + '') !== '') {
        try { lib.setKind(opts.kind, { reload: false }); } catch(e) {}
      }
      try { lib.setSaveSource((opts.saveSource !== undefined && opts.saveSource !== null) ? (opts.saveSource + '') : ''); } catch(e) {}
      try {
        var tenantID = toInt(opts.tenantID);
        lib.tenantID = tenantID > 0 ? tenantID : 0;
        if (tenantID > 0) $mount.attr('data-ml-tenant-id', String(tenantID));
        else $mount.removeAttr('data-ml-tenant-id');
      } catch(e) {}

// - maxFileMB: límite por archivo en upload (solo cliente; backend debe validar igual)
try {
  if (typeof lib.setUploadMaxMB === 'function') lib.setUploadMaxMB(opts.maxFileMB);
  else lib.uploadMaxMB = parseFloat(opts.maxFileMB) || 0;
} catch(e) {}


      // Estado footer antes de cargar
      highlightSelected();
      setFooter();

      // Cargar página 1 del listado
      // (sin push URL, pero setActive para paginación)
      try { lib.resetFilters && lib.resetFilters(); } catch (e) {}
      lib.load(1, { push: false, makeActive: true });

      bsModal.show();

      return new Promise(function (resolve) {
        state.resolve = resolve;
        // cuando renderice, aplicará highlightSelected() via ml:render
        syncSelection();
      });
    }
  };

})(window, jQuery);
