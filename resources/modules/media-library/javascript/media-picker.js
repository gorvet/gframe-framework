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
  var defaultEndpoints = $.extend({}, lib.endpoints || {});

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
    kind: 'all'
  };

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
      if ($insert.length) $insert.prop("disabled", !state.allowEmpty);
      return;
    }

    if (!state.multiple) {
      var first = state.selected.values().next().value || {};
      if ($label.length) $label.text("Seleccionado: #" + (first.id || "?"));
      if ($insert.length) $insert.prop("disabled", false);
      return;
    }

    var n = kindNouns(state.kind);
    var selWord = (n.gender === "f") ? "Seleccionadas" : "Seleccionados";
    if ($label.length) $label.text(selWord + ": " + count);
    if ($insert.length) $insert.prop("disabled", false);
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
    });
  }

  function toggleSelect(item) {
    if (!item || !toInt(item.id)) return;

    // single
    if (!state.multiple) {
      state.selected.clear();
      state.selected.set(item.id, item);
      highlightSelected();
      setFooter();
      return;
    }

    // multiple toggle
    if (state.selected.has(item.id)) {
      state.selected.delete(item.id);
      highlightSelected();
      setFooter();
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
    highlightSelected();
    setFooter();
  }

  // Eventos del MediaLibrary: click item / render
  $mount.off("ml:itemClick.mediaPicker").on("ml:itemClick.mediaPicker", function (e, item) {
    // picker siempre captura selección
    toggleSelect(item);
  });

  $mount.off("ml:render.mediaPicker").on("ml:render.mediaPicker", function () {
    highlightSelected();
    setFooter();
  });

  // Insertar
  $modal.off("click.mediaPickerInsert", "#mp-insert").on("click.mediaPickerInsert", "#mp-insert", function (e) {
    e.preventDefault();
    if (!state.selected.size && !state.allowEmpty) return;

    state.didInsert = true;

    var items = Array.from(state.selected.values());
    var ids = items.map(function (x) { return toInt(x.id); }).filter(Boolean);

    var result = { multiple: state.multiple, ids: ids, items: items };
    if (!state.multiple) result.item = items[0];

    bsModal.hide();

    // Evento global opcional
    $(document).trigger("media:selected", [result]);

    if (typeof state.resolve === "function") state.resolve(result);
    state.resolve = null;
  });

  // Cierre/cancel: resolve(null) solo si no insertó
  $modal.off("hidden.bs.modal.mediaPicker").on("hidden.bs.modal.mediaPicker", function () {
    if (!state.didInsert && typeof state.resolve === "function") {
      state.resolve(null);
    }
    state.resolve = null;
    state.didInsert = false;

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

      state.multiple = !!opts.multiple;
      state.max = (opts.max !== undefined) ? (toInt(opts.max) || 20) : 20;
      state.allowEmpty = (opts.allowEmpty !== undefined)
        ? !!opts.allowEmpty
        : (state.multiple ? true : false);

      // Preselección (ids)
      state.selected.clear();
      var pre = uniqInt(opts.selected || opts.selectedIds || []);
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
        highlightSelected();
        setFooter();
      });
    }
  };

})(window, jQuery);
