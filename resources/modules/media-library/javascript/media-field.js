/*!
 * MediaField v3 (minimal markup, smart defaults)
 * - Single by default (no data-ml-multiple attribute): stores INT id in hidden
 * - Multiple if data-ml-multiple attribute EXISTS (no value needed): stores JSON array of ints in hidden
 *
 * Depends on: jQuery, MediaPicker.open()
 */
(function (window, $) {
  "use strict";

  if (!window.jQuery) {
    console.warn("[MediaField] jQuery not found.");
    return;
  }

  function resolveTokensForm(candidate) {
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

  function buildPayloadWithTokens(payload) {
    var data = $.extend({}, payload || {});
    var $tokens = resolveTokensForm();

    if ($tokens.length) {
      ($tokens.serializeArray() || []).forEach(function (field) {
        if (!field || !field.name) return;
        data[field.name] = field.value;
      });
    }

    return data;
  }

  function toInt(n) {
    var x = parseInt(n, 10);
    return isNaN(x) ? 0 : x;
  }

  function uniqInt(arr) {
    var out = [];
    var seen = {};
    (arr || []).forEach(function (v) {
      var n = toInt(v);
      if (n <= 0) return;
      if (seen[n]) return;
      seen[n] = 1;
      out.push(n);
    });
    return out;
  }

  function readDomIds($field) {
    var ids = [];
    try {
      $field.find(".media-field-preview .media-thumb[data-ml-id]").each(function () {
        ids.push(toInt($(this).attr("data-ml-id")));
      });
    } catch (e) {}
    return uniqInt(ids);
  }

  function attrExists(el, attrName) {
    if (!el) return false;
    return el.hasAttribute("data-ml-" + attrName);
  }

  // data-ml-multiple:
  // - absent => false
  // - present empty => true
  // - present "1/true/yes" => true
  // - present "0/false/no" => false
  function inferMultiple(el) {
    if (!attrExists(el, "multiple")) return false;
    var raw = el.getAttribute("data-ml-multiple");
    if (raw === null || raw === "") return true;
    var v = String(raw).trim().toLowerCase();
    return !(v === "0" || v === "false" || v === "no");
  }

  // data-ml-remove-one:
  // - absent => default true (only for multiple)
  // - present empty or "1/true" => true
  // - present "0/false" => false
  function inferRemoveOne(el, multiple) {
    if (!multiple) return false;
    if (!attrExists(el, "remove-one")) return true;
    var raw = el.getAttribute("data-ml-remove-one");
    if (raw === null || raw === "") return true;
    var v = String(raw).trim().toLowerCase();
    return !(v === "0" || v === "false" || v === "no");
  }

  function getDataOrAttr($el, key, fallback) {
    var el = $el.get(0);
    var attr = el ? el.getAttribute("data-ml-" + key) : null;
    if (attr !== null && attr !== undefined && String(attr).trim() !== "") return attr;
    return fallback;
  }

  // ---------- Storage ----------
  // Defaults:
  // - single => "int"
  // - multiple => "json"
  function parseStoredValue(raw, store, multiple) {
    raw = (raw || "").toString().trim();
    if (!raw) return multiple ? [] : 0;

    if (store === "json") {
      try {
        var parsed = JSON.parse(raw);
        return multiple ? uniqInt(Array.isArray(parsed) ? parsed : []) : toInt(parsed);
      } catch (e) {
        return multiple ? [] : 0;
      }
    }

    if (store === "csv") {
      return multiple ? uniqInt(raw.split(",")) : toInt(raw);
    }

    // store === "int" (or unknown)
    return multiple ? uniqInt(raw.split(",")) : toInt(raw);
  }

  function writeStoredValue($input, store, multiple, value) {
    if (multiple) {
      var arr = uniqInt(value || []);
      if (store === "json") $input.val(JSON.stringify(arr));
      else if (store === "csv") $input.val(arr.join(","));
      else $input.val(arr.join(","));
      return;
    }

    var id = toInt(value);
    if (store === "json") $input.val(JSON.stringify(id || 0));
    else $input.val(id ? String(id) : "");
  }

  // ---------- Rendering ----------
  function normalizeItem(it) {
  if (!it) return null;
  var type = (it.type || "").toString();
  var mime = (it.mime_type || it.mime || "").toString();

  return {
    id: toInt(it.id || it.media_id),
    url: it.url || it.media_url || "",
    media_url: it.media_url || it.url || "",
    thumb_small: it.thumb_small || it.thumb || it.preview || "",
    name: it.name || it.filename || "",
    mime_type: mime,
    alt_text: it.alt_text || "",

    // ✅ NUEVO
    type: type,
    ext: (it.ext || "").toString(),
    is_image: (it.is_image != null) ? !!it.is_image : (type === "images" || (mime && mime.indexOf("image/") === 0))
  };
}
  function normalizeItems(arr) {
    return (arr || []).map(normalizeItem).filter(function (x) { return x && x.id; });
  }

  
  function nextRenderSeq($field) {
    var el = $field.get(0);
    var s = ($.data(el, "mlRenderSeq") || 0) + 1;
    $.data(el, "mlRenderSeq", s);
    return s;
  }

  function isLatestSeq($field, seq) {
    return $.data($field.get(0), "mlRenderSeq") === seq;
  }

  function pickVariant($field, multiple) {
    var v = ($field.attr("data-ml-fragment") || "").toString().trim();
    if (v) return v;
    return multiple ? "gthumb" : "sthumb";
  }


  function toFragmentCtx(it) {
    if (!it) return {};
    return {
      media_id: toInt(it.media_id || it.id),
      media_url: it.media_url || it.url || "",
      url: it.url || it.media_url || "",
      thumb_small: it.thumb_small || it.thumb || it.preview || "",
      name: it.name || it.filename || "",
      alt_text: it.alt_text || "",
      ext: (it.ext || "").toString(),
      type: (it.type || "").toString(),
      mimetype: (it.mimetype || it.mime_type || it.mime || "").toString()
    };
  }

 

function ajaxRenderHtml($field, payload, seq) {
  return $.ajax({
   url: $field.attr("data-ml-field-endpoint") || site_url+"ajax/admin/media/field",
    method: "POST",
    data: buildPayloadWithTokens(payload)
    // 👇 NO fuerces dataType; deja que jQuery infera (o pon "text" si prefieres)
  }).then(function (resp) {
    var html = resp;

    // 1) Si el framework devuelve objeto: {data: "..."} o {html:"..."}
    if (resp && typeof resp === "object") {
      html = resp.html || (typeof resp.data === "string" ? resp.data : "");
    }

    // 2) Si devuelve string pero en realidad es JSON serializado
    if (typeof html === "string") {
      var s = html.trim();
      if (s && (s[0] === "{" || s[0] === "[")) {
        try {
          var j = JSON.parse(s);
          if (j && typeof j === "object") html = j.html || (typeof j.data === "string" ? j.data : "");
        } catch (e) {}
      }
    }

    return { html: (html || ""), seq: seq };
  }, function (xhr) {
    return $.Deferred().reject({ xhr: xhr, seq: seq });
  });
}
  function renderSingle($field, item) {
    var $preview = $field.find(".media-field-preview").first();
    var $clear = $field.find("[data-ml-media-clear]").first();

    if (!item || !toInt(item.id)) {
      $preview.html("");
      $preview.removeClass("is-loading");
      if ($clear.length) $clear.addClass("d-none");
      return;
    }

    if ($clear.length) $clear.removeClass("d-none");

    var kind = ($field.attr("data-ml-kind") || "").toString().trim();
    var saveSource = ($field.attr("data-ml-save-source") || "").toString().trim();
    var variant = pickVariant($field, false);

    var seq = nextRenderSeq($field);
    $preview.addClass("is-loading");

    ajaxRenderHtml($field, {
      variant: variant,
      mode: "single",
      media_ids: JSON.stringify([toInt(item.id)])
    }, seq).done(function (res) {
      if (!isLatestSeq($field, res.seq)) return;
      $preview.html(res.html || "");
      $preview.removeClass("is-loading");
      $field.trigger("mediafield:rendered", ["single", item]);
    }).fail(function (err) {
      if (!isLatestSeq($field, err.seq)) return;
      $preview.removeClass("is-loading");
      console.warn("[MediaField] renderSingle ajax failed", err.xhr || err);
    });
  }

  function renderMultiple($field, items, allowRemoveOne) {
    var $preview = $field.find(".media-field-preview").first();
    var $clear = $field.find("[data-ml-media-clear]").first();

    items = items || [];
    if (!items.length) {
      $preview.html("");
      $preview.removeClass("is-loading");
      if ($clear.length) $clear.addClass("d-none");
      return;
    }

    if ($clear.length) $clear.removeClass("d-none");

    var kind = ($field.attr("data-ml-kind") || "").toString().trim();
    var saveSource = ($field.attr("data-ml-save-source") || "").toString().trim();
    var variant = pickVariant($field, true);

    var seq = nextRenderSeq($field);
    $preview.addClass("is-loading");

    ajaxRenderHtml($field, {
      variant: variant,
      mode: "multiple",
      kind: kind,
      saveSource: saveSource,
      allow_remove_one: allowRemoveOne ? 1 : 0,
      media_ids: JSON.stringify((items || []).map(function(item) { return toInt(item.id); }))
    }, seq).done(function (res) {
      if (!isLatestSeq($field, res.seq)) return;
      $preview.html(res.html || "");
      $preview.removeClass("is-loading");
    }).fail(function (err) {
      if (!isLatestSeq($field, err.seq)) return;
      $preview.removeClass("is-loading");
      console.warn("[MediaField] renderMultiple ajax failed", err.xhr || err);
    });
  }


// ---------- Binding ----------
  function bindOne(el) {
    var $field = $(el);
    if ($field.data("mediaField")) return $field.data("mediaField");
    var $input = $field.find('input[type="hidden"]').first();

    if (!$input.length) {
      console.warn("[MediaField] Missing hidden input inside", el);
      return null;
    }

    var multiple = inferMultiple(el);

    // Defaults by mode
    var storeDefault = multiple ? "json" : "int";
    var store = String(getDataOrAttr($field, "store", storeDefault)).toLowerCase(); // optional override
    var max = toInt(getDataOrAttr($field, "max", multiple ? 50 : 1)) || (multiple ? 50 : 1);
    var maxFileMB = parseFloat(getDataOrAttr($field, "max-mb", 0)) || 0; // MB por archivo (0 = sin límite)

    // Only relevant for multiple; default append
    var behavior = String(getDataOrAttr($field, "behavior", "append")).toLowerCase(); // append | replace
    var allowRemoveOne = inferRemoveOne(el, multiple);

    // title/insertText: SOLO si el HTML los define (evita duplicar lógica con MediaPicker)
var title = "";
if (attrExists(el, "title")) title = String(getDataOrAttr($field, "title", "")).trim();

var insertText = "";
if (attrExists(el, "insert-text")) insertText = String(getDataOrAttr($field, "insert-text", "")).trim();

    var state = {
      multiple: multiple,
      store: store,
      max: max,
      maxFileMB: maxFileMB,
      behavior: behavior,
      removeOne: allowRemoveOne,
      title: title,
      insertText: insertText,
      ids: multiple ? parseStoredValue($input.val(), store, true) : [],
      items: [],
      item: null
    };

    // Empty initial render (no hydration from IDs here)
     // ---------- Initial render (respeta backend) ----------
    var $preview = $field.find(".media-field-preview").first();
    var $btnClear = $field.find("[data-ml-media-clear]").first();

    if (multiple) {
      // 1) IDs desde el hidden (si viene)
      var idsHidden = uniqInt(parseStoredValue($input.val(), store, true));

      // 2) Si el hidden viene vacío pero backend ya pintó thumbs, leerlos del DOM
      var idsDom = [];
      $preview.find(".media-thumb[data-ml-id]").each(function () {
        idsDom.push(toInt($(this).attr("data-ml-id")));
      });
      idsDom = uniqInt(idsDom);

      var initIds = idsHidden.length ? idsHidden : idsDom;

      if (initIds.length) {
        // No tocar el HTML ya pintado por PHP; solo sincronizar hidden + estado
        writeStoredValue($input, store, true, initIds);
        state.ids = initIds;

        if ($btnClear.length) $btnClear.removeClass("d-none");
      } else {
        // No hay nada ni en hidden ni en DOM => render vacío real
        renderMultiple($field, [], allowRemoveOne);
      }
    } else {
      var idHidden = toInt(parseStoredValue($input.val(), store, false));
      var hasImg = $preview.find("img").length > 0;

      if ((idHidden && hasImg) || (!idHidden && hasImg)) {
        // Si el backend ya trajo imagen, NO la borres; solo habilita "Quitar"
        if ($btnClear.length) $btnClear.removeClass("d-none");
      } else {
        renderSingle($field, null);
      }
    }


    function openPicker() {
      if (!window.MediaPicker || typeof window.MediaPicker.open !== "function") {
        if (typeof alertToast === "function") {
          alertToast({ icon: "warning", title: "Falta cargar mediaPicker.js (MediaPicker.open)." });
        } else {
          try { console.warn("Falta cargar mediaPicker.js (MediaPicker.open)."); } catch (e) {}
        }
        return;
      }

      var selected = [];
      if (multiple) selected = uniqInt(parseStoredValue($input.val(), store, true));
      else {
        var v = parseStoredValue($input.val(), store, false);
        selected = v ? [toInt(v)] : [];
      }


      // Kind (images|docs|all): filtra el picker + valida upload (opcional)
      var kind = ($field.attr('data-ml-kind') || '').toString().trim();
      // saveSource: destino de guardado (NO afecta el filtro del listado)
      var saveSource = ($field.attr('data-ml-save-source') || '').toString().trim();
      var tenantID = toInt($field.attr('data-ml-tenant-id') || 0);

      window.MediaPicker.open({
        multiple: multiple,
        max: max,
        tenantID: tenantID || undefined,
        kind: (kind || undefined),
        saveSource: (saveSource || undefined),
        selected: selected,
        title: title,
        insertText: insertText,
        maxFileMB: maxFileMB
      }).then(function (res) {
        if (!res) return; // cancel

        // Single: requiere 1 item con id
        if (!multiple) {
          var itemsSingle = normalizeItems(res.items || []);
          if (!itemsSingle.length) return;

          var it = itemsSingle[0];
          state.item = it;

          writeStoredValue($input, store, false, it.id);
          renderSingle($field, it);

          $field.trigger("mediafield:change", [it.id, it]);
          return;
        }

        // Multiple: el resultado del picker es AUTORITARIO (permite añadir y quitar)
        // ids puede venir aunque res.items venga vacío o parcial.
        var nextIds = uniqInt(res.ids || []);
        // Si no vienen ids, caemos a items normalizados
        var pickedItems = normalizeItems(res.items || []);
        if (!nextIds.length && pickedItems.length) {
          nextIds = uniqInt(pickedItems.map(function (x) { return x.id; }));
        }
        // append|replace behavior
        var currentIds = uniqInt(parseStoredValue($input.val(), store, true));
        if (behavior === "append") {
          // En append: si no seleccionaste nada nuevo, no toques el estado actual
          if (!nextIds.length) return;
          nextIds = uniqInt(currentIds.concat(nextIds));
        }

        // Permitir guardar vacío (solo en replace)
        if (!nextIds.length) {
          state.ids = [];
          state.items = [];
          writeStoredValue($input, store, true, []);
          renderMultiple($field, [], allowRemoveOne);
          $field.trigger("mediafield:change", [[], []]);
          return;
        }
// Respeta max
        if (nextIds.length > max) nextIds = nextIds.slice(0, max);

        // Merge de items viejos + nuevos para renderizar thumbs aunque el picker no traiga data completa
        var map = {};
        (state.items || []).forEach(function (x) { if (x && x.id) map[x.id] = x; });
        pickedItems.forEach(function (x) { if (x && x.id) map[x.id] = x; });

        var orderedItems = nextIds.map(function (id) { return map[id]; }).filter(Boolean);

        state.ids = nextIds;
        state.items = orderedItems;

        writeStoredValue($input, store, true, nextIds);
        renderMultiple($field, orderedItems, allowRemoveOne);

        $field.trigger("mediafield:change", [nextIds, orderedItems]);
      });
    }

    $field.on("click", "[data-ml-media-pick]", function (e) {
      e.preventDefault();
      openPicker();
    });

    $field.on("click", "[data-ml-media-clear]", function (e) {
      e.preventDefault();
      if (multiple) {
        state.ids = [];
        state.items = [];
        writeStoredValue($input, store, true, []);
        renderMultiple($field, [], allowRemoveOne);
        $field.trigger("mediafield:change", [[], []]);
      } else {
        state.item = null;
        writeStoredValue($input, store, false, 0);
        renderSingle($field, null);
        $field.trigger("mediafield:change", [null, null]);
      }
    });

    $field.on("click", "[data-ml-media-remove]", function (e) {
      e.preventDefault();
      if (!multiple) return;
      if (!allowRemoveOne) return;

      var $thumb = $(this).closest(".media-thumb");
      var id = toInt($thumb.attr("data-ml-id"));
      if (!id) return;

      // 1) IDs actuales: hidden -> fallback DOM (cuando el backend pintó thumbs pero state.items está vacío)
      var currentIds = uniqInt(parseStoredValue($input.val(), store, true));
      if (!currentIds.length) currentIds = readDomIds($field);

      // 2) Remover del listado
      var ids = currentIds.filter(function (x) { return x !== id; });
      state.ids = ids;

      // 3) Si tenemos items hidratados (por el picker), re-render por ajax como siempre
      if (Array.isArray(state.items) && state.items.length) {
        state.items = state.items.filter(function (x) { return x && x.id !== id; });

        writeStoredValue($input, store, true, ids);
        renderMultiple($field, state.items, allowRemoveOne);
        $field.trigger("mediafield:change", [ids, state.items]);
        return;
      }

      // 4) Fallback DOM-only: no borres todo el preview por falta de state.items
      writeStoredValue($input, store, true, ids);
      $thumb.remove();

      var $preview = $field.find(".media-field-preview").first();
      var $clear = $field.find("[data-ml-media-clear]").first();
      if ($clear.length) {
        if ($preview.find(".media-thumb[data-ml-id]").length) $clear.removeClass("d-none");
        else $clear.addClass("d-none");
      }

      $field.trigger("mediafield:change", [ids, []]);
    });

    var initialIds = multiple ? state.ids : [toInt($input.val())];
    initialIds = uniqInt(initialIds);
    if (initialIds.length && !$preview.children().length) {
      var hydrateSeq = nextRenderSeq($field);
      $.ajax({
        url: $field.attr("data-ml-field-endpoint") || site_url + "ajax/admin/media/field",
        type: "POST", dataType: "json",
        data: buildPayloadWithTokens({media_ids: JSON.stringify(initialIds), variant: pickVariant($field, multiple), allow_remove_one: allowRemoveOne ? 1 : 0})
      }).done(function(response) {
        if (!isLatestSeq($field, hydrateSeq) || response.status !== "success") return;
        $preview.html(response.html || "");
        var hydrated = normalizeItems(response.data || []);
        $btnClear.toggleClass('d-none', hydrated.length === 0);
        if (multiple) state.items = hydrated;
        else state.item = hydrated[0] || null;
      });
    }
    var instance = { el: el, field: $field, input: $input, state: state, open: openPicker };
    $field.data("mediaField", instance);
    return instance;
  }

  window.MediaField = {
    normalizeIds: function(value) {
      if (Array.isArray(value)) return uniqInt(value);
      var raw = String(value || "").trim();
      return uniqInt(parseStoredValue(raw, raw.charAt(0) === "[" ? "json" : "csv", true));
    },
    bindAll: function (root) {
      var $root = root ? $(root) : $(document);
      var fields = [];
      $root.find("[data-ml-media-field]").each(function () {
        var inst = bindOne(this);
        if (inst) fields.push(inst);
      });
      return fields;
    }
  };

  $(function () {
    window.MediaField.bindAll();
  });

})(window, jQuery);

/* =========================
MARKUP MINIMO (v3)
=========================

A) SINGLE (default): no data-ml-multiple => INT id
----------------------------------------------
<div class="media-field" data-ml-media-field>
  <input type="hidden" name="main_image_id" value="">
  <div class="media-field-preview"></div>
  <button type="button" class="btn btn-outline-primary" data-ml-media-pick>Elegir imagen</button>
  <button type="button" class="btn btn-outline-danger d-none" data-ml-media-clear>Quitar</button>
</div>

B) MULTIPLE: con solo poner data-ml-multiple => JSON array de ints
--------------------------------------------------------------
<div class="media-field" data-ml-media-field data-ml-multiple data-ml-max="12">
  <input type="hidden" name="gallery_ids_json" value="[]">
  <div class="media-field-preview media-field-grid"></div>
  <button type="button" class="btn btn-outline-primary" data-ml-media-pick>Añadir</button>
  <button type="button" class="btn btn-outline-danger d-none" data-ml-media-clear>Limpiar</button>
</div>

Opcionales:
-----------
data-ml-behavior="append|replace" (solo multiple; default append)
data-ml-remove-one="0"           (solo multiple; default permite quitar 1x1)
data-ml-max="N"                  (solo multiple; default 50)
data-ml-title="..."              (título del picker)
data-ml-insert-text="..."        (texto del botón final del picker)

CSS recomendado:
----------------
.media-field-grid{display:flex;gap:10px;flex-wrap:wrap;}
.media-thumb{width:110px;}
.media-thumb img{display:block;width:100%;height:auto;border-radius:8px;}
*/
