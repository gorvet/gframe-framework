(function(window, document, $) {
  "use strict";

  var registrations = {};
  var activities = new WeakMap();
  var protections = new Map();
  var saveSnapshots = new WeakMap();

  function notifyMediaState(element) {
    if (!element.form) return;
    element.form.querySelectorAll('textarea.js-rich-text-editor').forEach(function(field) {
      var instance = tinymce.get(field.id);
      if (instance && !instance.removed) instance.dispatch('GFrameMediaState');
    });
  }

  function syncEditorTheme(editor) {
    var body = editor.getBody();
    if (!body) return;
    var source = window.getComputedStyle(document.documentElement);
    ['--bs-body-font-family', '--bs-body-font-size', '--bs-body-line-height',
      '--bs-body-color', '--bs-body-bg', '--bs-border-color', '--bs-secondary-bg',
      '--bs-tertiary-bg', '--bs-link-color'].forEach(function(name) {
      body.style.setProperty(name, source.getPropertyValue(name));
    });
    body.style.fontFamily = 'var(--bs-body-font-family)';
    body.style.fontSize = 'var(--bs-body-font-size)';
    body.style.lineHeight = 'var(--bs-body-line-height)';
    body.style.color = 'var(--bs-body-color)';
    body.style.backgroundColor = 'var(--bs-body-bg)';
    body.style.colorScheme = document.documentElement.dataset.bsTheme === 'dark' ? 'dark' : 'light';
  }

  function hasMeaningfulSiblingBefore(node) {
    var sibling = node.previousSibling;
    while (sibling) {
      if (sibling.nodeType === Node.ELEMENT_NODE) return true;
      if (sibling.nodeType === Node.TEXT_NODE && String(sibling.nodeValue || "").trim() !== "") return true;
      sibling = sibling.previousSibling;
    }
    return false;
  }

  function normalizeTableCells(root) {
    if (!root || !root.querySelectorAll) return;

    root.querySelectorAll("td, th").forEach(function(cell) {
      Array.from(cell.children).forEach(function(child) {
        if (child.tagName !== "P") return;

        var hasContent = String(child.textContent || "").trim() !== "" || child.querySelector("img, br, a");
        if (hasContent && hasMeaningfulSiblingBefore(child)) {
          cell.insertBefore(cell.ownerDocument.createElement("br"), child);
        }

        while (child.firstChild) cell.insertBefore(child.firstChild, child);
        child.remove();
      });
    });
  }

  function preserveSemanticInlineStyles(root) {
    root.querySelectorAll("span").forEach(function(span) {
      var fontWeight = String(span.style.fontWeight || "").toLowerCase();
      var textDecoration = String(span.style.textDecoration || span.style.textDecorationLine || "").toLowerCase();
      var wrappers = [];

      if (fontWeight === "bold" || parseInt(fontWeight, 10) >= 600) wrappers.push("strong");
      if (String(span.style.fontStyle || "").toLowerCase() === "italic") wrappers.push("em");
      if (textDecoration.indexOf("underline") !== -1) wrappers.push("u");

      var content = document.createDocumentFragment();
      while (span.firstChild) content.appendChild(span.firstChild);
      wrappers.forEach(function(tagName) {
        var wrapper = document.createElement(tagName);
        wrapper.appendChild(content);
        content = document.createDocumentFragment();
        content.appendChild(wrapper);
      });
      span.replaceWith(content);
    });
  }

  function wordListInfo(paragraph) {
    var source = [paragraph.className || "", paragraph.getAttribute("style") || ""].join(" ");
    if (!/(?:MsoListParagraph|mso-list\s*:)/i.test(source)) return null;

    var levelMatch = source.match(/level(\d+)/i);
    var marker = String(paragraph.textContent || "").trim().match(/^(\(?\d+[.)]|[A-Za-z][.)]|[ivxlcdmIVXLCDM]+[.)]|[^\p{L}\p{N}\s]{1,3})/u);
    var markerText = marker ? marker[1] : "";
    var ordered = /^(?:\(?\d+[.)]|[A-Za-z][.)]|[ivxlcdmIVXLCDM]+[.)])$/.test(markerText);

    return {
      level: Math.max(1, parseInt(levelMatch ? levelMatch[1] : "1", 10)),
      marker: markerText,
      tagName: ordered ? "ol" : "ul"
    };
  }

  function removeWordListMarker(paragraph, marker) {
    var removedMarker = false;
    paragraph.querySelectorAll("span").forEach(function(span) {
      if (/mso-list\s*:\s*ignore/i.test(span.getAttribute("style") || "")) {
        span.remove();
        removedMarker = true;
      }
    });

    if (!marker || removedMarker) return;
    var walker = paragraph.ownerDocument.createTreeWalker(paragraph, NodeFilter.SHOW_TEXT);
    var textNode = walker.nextNode();
    while (textNode && String(textNode.nodeValue || "").trim() === "") textNode = walker.nextNode();
    if (textNode) {
      textNode.nodeValue = String(textNode.nodeValue || "").replace(/^\s*(?:\(?\d+[.)]|[A-Za-z][.)]|[ivxlcdmIVXLCDM]+[.)]|[^\p{L}\p{N}\s]{1,3})\s*/u, "");
    }
  }

  function configureOrderedList(list, marker) {
    var number = String(marker || "").match(/^\(?(\d+)/);
    if (number && parseInt(number[1], 10) > 1) list.setAttribute("start", number[1]);
  }

  function convertDirectWordLists(parent) {
    var lists = [];
    var lastItems = [];

    Array.from(parent.children).forEach(function(child) {
      var info = child.tagName === "P" ? wordListInfo(child) : null;
      if (!info) {
        lists = [];
        lastItems = [];
        return;
      }

      var level = info.level;
      if (level > lists.length + 1) level = lists.length + 1;
      var list = lists[level - 1];
      if (!list || list.tagName.toLowerCase() !== info.tagName) {
        list = child.ownerDocument.createElement(info.tagName);
        configureOrderedList(list, info.marker);
        if (level > 1 && lastItems[level - 2]) lastItems[level - 2].appendChild(list);
        else parent.insertBefore(list, child);
        lists[level - 1] = list;
      }

      lists.length = level;
      lastItems.length = level;
      removeWordListMarker(child, info.marker);

      var item = child.ownerDocument.createElement("li");
      while (child.firstChild) item.appendChild(child.firstChild);
      list.appendChild(item);
      lastItems[level - 1] = item;
      child.remove();
    });
  }

  function convertWordLists(root) {
    Array.from(root.querySelectorAll("div, td, th")).reverse().forEach(convertDirectWordLists);
    convertDirectWordLists(root);
  }

  function wordHeadingLevel(element) {
    var source = [element.className || "", element.getAttribute("style") || ""].join(" ");
    var explicit = source.match(/(?:MsoHeading|MsoTitle|Heading|T[ií]tulo|Ttulo)\s*[-_ ]?([1-6])/i);
    if (explicit) return explicit[1];

    var ariaLevel = parseInt(element.getAttribute("aria-level") || "0", 10);
    if (ariaLevel >= 1 && ariaLevel <= 6) return String(ariaLevel);

    var outline = source.match(/mso-outline-level\s*:\s*([0-5])/i);
    if (outline) return String(parseInt(outline[1], 10) + 1);

    if (/\bMsoSubtitle\b/i.test(source)) return "2";
    if (/\bMsoTitle\b/i.test(source)) return "1";
    return "";
  }

  function pastePreprocess(plugin, args) {
    var container = document.createElement("div");
    container.innerHTML = args.content;

    container.querySelectorAll("p, h1, h2, h3, h4, h5, h6").forEach(function(paragraph) {
      var level = wordHeadingLevel(paragraph);
      if (!level) return;

      if (paragraph.tagName.toLowerCase() === "h" + level) return;

      var heading = document.createElement("h" + level);
      heading.innerHTML = paragraph.innerHTML;
      paragraph.replaceWith(heading);
    });

    convertWordLists(container);
    preserveSemanticInlineStyles(container);
    normalizeTableCells(container);

    args.content = container.innerHTML;
  }

  function baseOptions(element) {
    var editorBaseURL = typeof site_url === "string" ? site_url : "/";
    var options = {
      target: element,
      license_key: "gpl",
      menubar: false,
      branding: false,
      promotion: false,
      language: "es",
      language_url: editorBaseURL + "public/vendors/external/tinymce/langs/es.js",
      statusbar: true,
      browser_spellcheck: true,
      convert_urls: false,
      min_height: parseInt(element.dataset.editorMinHeight || "580", 10),
      height: parseInt(element.dataset.editorMinHeight || "580", 10),
      resize: true,
      plugins: "lists link autolink image table code quickbars wordcount fullscreen",
      toolbar: "undo redo | blocks | bold italic | bullist numlist | link unlink image table | fullscreen gframeOutline gframeMore",
      toolbar_mode: "floating",
      toolbar_groups: {
        gframeMore: {icon: 'more-drawer', tooltip: element.dataset.editorMore, items: 'underline blockquote hr | removeformat code' + (element.dataset.editorPreviewUrl ? ' gframePreview' : '')}
      },
      toolbar_sticky: true,
      toolbar_sticky_offset: 60,
      quickbars_selection_toolbar: "blocks | bold italic underline | bullist numlist | blockquote link",
      quickbars_insert_toolbar: false,
      block_formats: "Párrafo=p; Encabezado 1=h1; Encabezado 2=h2; Encabezado 3=h3; Encabezado 4=h4; Encabezado 5=h5; Encabezado 6=h6; Cita=blockquote",
      table_header_type: "section",
      valid_elements: "p,br,strong/b,em/i,u,s,ul,ol[start|type],li[value],a[href|target|rel],h1,h2,h3,h4,h5,h6,blockquote,table,thead,tbody,tr,td[colspan|rowspan],th[colspan|rowspan],img[src|alt|title|width|height],hr",
      paste_as_text: false,
      paste_merge_formats: true,
      paste_remove_styles_if_webkit: false,
      paste_webkit_styles: "font-weight font-style text-decoration",
      smart_paste: true,
      link_default_protocol: "https",
      link_assume_external_targets: "https",
      paste_preprocess: pastePreprocess,
      paste_postprocess: function(plugin, args) {
        normalizeTableCells(args.node);
      },
      init_instance_callback: function(editor) {
        normalizeTableCells(editor.getBody());
        syncEditorTheme(editor);
        var observer = new MutationObserver(function() { syncEditorTheme(editor); });
        observer.observe(document.documentElement, {attributes: true, attributeFilter: ['data-bs-theme', 'style', 'class']});
        editor.on('remove', function() { observer.disconnect(); });
      },
      content_style: "body { font-family: var(--bs-body-font-family); font-size: var(--bs-body-font-size); line-height: var(--bs-body-line-height); color: var(--bs-body-color); background: var(--bs-body-bg); } a { color: var(--bs-link-color); } h1 { font-size: 2rem; } h2 { font-size: 1.75rem; } h3 { font-size: 1.4rem; } h4 { font-size: 1.2rem; } h5 { font-size: 1.1rem; } h6 { font-size: 1rem; } img { max-width: 100%; height: auto; } table { width: 100%; border-collapse: collapse; font-size: 0.8rem; } td, th { border: 1px solid var(--bs-border-color); padding: 0.5rem; } td > p, th > p { margin: 0; } thead th { background: var(--bs-secondary-bg); font-weight: 700; } tbody tr:nth-child(odd) td { background: var(--bs-tertiary-bg); }"
    };
    options.content_style = 'html, body { overscroll-behavior-y: contain; } ' + options.content_style;
    return options;
  }

  function init(element) {
    if (!element || typeof tinymce === "undefined" || tinymce.get(element.id)) return;
    var options = $.extend(true, {}, baseOptions(element), registrations[element.id] || {});
    options.target = element;
    var activity = {mediaPending: false};
    activities.set(element, activity);
    configureSitePreview(element, options);
    if (options.toolbar_mode !== 'floating' && options.toolbar_groups && options.toolbar_groups.gframeMore) {
      if (typeof options.toolbar === 'string') options.toolbar = options.toolbar.replace(/\bgframeMore\b/g, options.toolbar_groups.gframeMore.items);
      delete options.toolbar_groups.gframeMore;
    }
    configureWriting(element, options);
    configureProtection(element, options);
    configureAutosave(element, options, activity);
    configureSave(element, options, activity);
    if (element.dataset.editorMedia === '1') configureMedia(element, options, activity);
    tinymce.init(options);
  }

  function configureSitePreview(element, options) {
    if (!element.dataset.editorPreviewUrl) return;
    var url;
    try { url = new URL(element.dataset.editorPreviewUrl, window.location.href); } catch (_) { url = null; }
    if (!url || url.origin !== window.location.origin) {
      if (options.toolbar_groups && options.toolbar_groups.gframeMore) options.toolbar_groups.gframeMore.items = options.toolbar_groups.gframeMore.items.replace(/\bgframePreview\b/g, '');
      return;
    }
    var setup = options.setup;
    options.setup = function(editor) {
      if (typeof setup === 'function') setup(editor);
      var action = {
        text: element.dataset.editorPreviewLabel,
        onAction: function() { window.open(url.href, '_blank', 'noopener'); }
      };
      editor.ui.registry.addButton('gframePreview', action);
      editor.ui.registry.addMenuItem('gframePreview', action);
    };
  }

  function configureWriting(element, options) {
    var labels;
    try { labels = JSON.parse(element.dataset.editorWriting || '{}'); } catch (error) { return; }
    if (!labels.paragraph) return;
    var setup = options.setup;
    options.setup = function(editor) {
      if (typeof setup === 'function') setup(editor);
      var commands = {
        paragraph: ['FormatBlock', 'p'], heading2: ['FormatBlock', 'h2'], heading3: ['FormatBlock', 'h3'],
        bullet: ['InsertUnorderedList'], number: ['InsertOrderedList'], quote: ['FormatBlock', 'blockquote'], rule: ['InsertHorizontalRule']
      };
      if (element.dataset.editorOutline) editor.ui.registry.addMenuButton('gframeOutline', {
        icon: 'toc', tooltip: element.dataset.editorOutline,
        fetch: function(callback) {
          var headings = Array.from(editor.getBody().querySelectorAll('h1,h2,h3,h4,h5,h6'));
          callback(headings.length ? headings.map(function(heading) {
            return {type: 'menuitem', text: heading.textContent.trim() || heading.tagName,
              onAction: function() {
                if (!editor.getBody().contains(heading)) return;
                editor.focus(); editor.selection.setCursorLocation(heading, 0); editor.selection.scrollIntoView(heading);
              }};
          }) : [{type: 'menuitem', text: element.dataset.editorOutlineEmpty, enabled: false}]);
        }
      });
      function normalize(value) { return String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
      editor.ui.registry.addAutocompleter('gframeWriting', {
        trigger: '/', minChars: 0, columns: 1,
        matches: function(range) {
          if (editor.mode.isReadOnly()) return false;
          var block = editor.dom.getParent(range.startContainer, 'p');
          if (!block) return false;
          var before = range.cloneRange(); before.selectNodeContents(block); before.setEnd(range.startContainer, range.startOffset);
          return before.toString().trim() === '' && !before.cloneContents().querySelector('img, table, figure, hr');
        },
        fetch: function(pattern) {
          return Promise.resolve(Object.keys(commands).filter(function(key) { return normalize(labels[key]).indexOf(normalize(pattern)) !== -1; })
            .map(function(key) { return {type: 'autocompleteitem', value: key, text: labels[key]}; }));
        },
        onAction: function(api, range, value) {
          api.hide();
          if (editor.mode.isReadOnly() || !commands[value]) return;
          editor.undoManager.transact(function() {
            editor.selection.setRng(range); editor.selection.setContent('');
            editor.execCommand(commands[value][0], false, commands[value][1]); editor.nodeChanged();
          });
          editor.focus(); editor.save({set_dirty: false});
        }
      });
    };
  }

  function configureProtection(element, options) {
    if (element.dataset.editorProtect === undefined && !element.dataset.editorDraftKey) return;
    var setup = options.setup;
    options.setup = function(editor) {
      if (typeof setup === 'function') setup(editor);
      editor.on('init', function() {
        normalizeTableCells(editor.getBody());
        var state = {editor: editor, baseline: editor.getContent(), lastAck: 0, sequence: 0};
        protections.set(editor.id, state);
        var timer, removed = false, candidate = null, recovered = null, ownRaw = null;
        var panel = document.getElementById(element.id + '-recovery');
        var status = document.getElementById(element.id + '-draft-status');
        var messages = {};
        try { messages = JSON.parse(element.dataset.editorDraftMessages || '{}'); } catch (error) { /* No storage UI for an invalid custom view. */ }
        var appPrefix = 'gframe:rte:v1:' + encodeURIComponent(new URL(typeof site_url === 'string' ? site_url : '/', window.location.href).href) + ':';
        var prefix = element.dataset.editorDraftKey && panel ? appPrefix + encodeURIComponent(element.dataset.editorDraftKey) + ':' + encodeURIComponent(element.id) + ':' : null;
        var random = Array.from(window.crypto.getRandomValues(new Uint32Array(4))).join('-');
        var ownKey = prefix ? prefix + random : null;
        var maxSize = 1024 * 1024, maxAge = 7 * 24 * 60 * 60 * 1000;
        function current() { return editor.getContent(); }
        function changed() { return current() !== state.baseline; }
        state.hasChanges = changed;
        function message(key) {
          if (status && messages[key]) { status.textContent = messages[key]; status.hidden = false; }
        }
        function removeExact(key, raw) {
          if (key && raw && localStorage.getItem(key) === raw) localStorage.removeItem(key);
        }
        function display() {
          if (!panel) return;
          panel.hidden = !candidate;
          if (candidate) panel.querySelector('[data-editor-draft-message]').textContent = messages[candidate.data.baseline === state.baseline ? 'available' : 'conflict'];
        }
        function findDraft() {
          if (!prefix) return;
          try {
            var found = [], keys = [];
            for (var index = 0; index < localStorage.length; index++) {
              var key = localStorage.key(index); if (key && key.indexOf(prefix) === 0 && key !== ownKey) keys.push(key);
            }
            keys.forEach(function(key) {
              var raw = localStorage.getItem(key), data;
              if (!raw || raw.length > maxSize) return;
              try { data = JSON.parse(raw); } catch (error) { return; }
              if (!data || data.version !== 1 || typeof data.content !== 'string' || typeof data.baseline !== 'string' || !Number.isFinite(data.updated)) return;
              if (Date.now() - data.updated > maxAge) { removeExact(key, raw); return; }
              if (data.updated > Date.now() + 60000 || data.content === state.baseline) return;
              found.push({key: key, raw: raw, data: data});
            });
            found.sort(function(a, b) { return b.data.updated - a.data.updated; });
            candidate = found[0] || null; display();
          } catch (error) { message('failed'); }
        }
        function persist() {
          clearTimeout(timer);
          if (!ownKey || removed || !editor.initialized) return;
          try {
            if (!changed()) { removeExact(ownKey, ownRaw); ownRaw = null; if (status) status.hidden = true; return; }
            var raw = JSON.stringify({version: 1, baseline: state.baseline, content: current(), updated: Date.now()});
            if (raw.length * 2 > maxSize) throw new Error('draft_size');
            localStorage.setItem(ownKey, raw); ownRaw = raw; message('stored');
          } catch (error) { message('failed'); }
        }
        function schedule() { clearTimeout(timer); timer = setTimeout(persist, 600); }
        function beforeUnload(event) {
          persist();
          if (element.dataset.editorProtect !== '0' && changed()) { event.preventDefault(); event.returnValue = ''; }
        }
        function visibility() { if (document.visibilityState === 'hidden') persist(); }
        function storage(event) { if (prefix && event.key && event.key.indexOf(prefix) === 0) findDraft(); }
        function actions(event) {
          var button = event.target.closest('button'); if (!button || !candidate) return;
          if (button.hasAttribute('data-editor-draft-discard')) {
            if (typeof window.swalAlert !== 'function') return message('confirm_missing');
            var selected = candidate;
            Promise.resolve(window.swalAlert({icon: 'warning', title: messages.discard, text: messages.discard_question,
              showCancelButton: true, cancelButtonText: messages.cancel, confirmButtonText: messages.discard,
              focusCancel: true, allowEscapeKey: true
            })).then(function(result) {
              if (removed || !result.isConfirmed) return;
              removeExact(selected.key, selected.raw); candidate = null; findDraft();
              if (candidate) panel.querySelector('[data-editor-draft-restore]').focus(); else editor.focus();
            }).catch(function() { if (!removed) message('failed'); });
          } else if (button.hasAttribute('data-editor-draft-restore') && !editor.mode.isReadOnly()) {
            recovered = candidate; candidate = null; display();
            editor.undoManager.transact(function() { editor.setContent(recovered.data.content); editor.nodeChanged(); });
            editor.focus(); editor.save({set_dirty: false}); persist(); message('restored');
          }
        }
        state.snapshot = function() {
          normalizeTableCells(editor.getBody());
          var snapshot = Object.freeze({editorID: editor.id, content: current()});
          saveSnapshots.set(snapshot, {state: state, sequence: ++state.sequence, recovered: recovered}); return snapshot;
        };
        state.confirm = function(snapshot, sequence, source, baseVersion) {
          if (sequence < state.lastAck) return false;
          state.lastAck = sequence; state.baseline = snapshot.content;
          if (!changed()) editor.setDirty(false);
          try {
            if (source) {
              removeExact(source.key, source.raw);
              if (recovered === source) recovered = null;
            }
          } catch (error) { message('failed'); }
          persist(); editor.dispatch('GFrameContentPublished', {snapshot: snapshot, baseVersion: baseVersion}); return true;
        };
        editor.on('input change Undo Redo SetContent', schedule);
        window.addEventListener('beforeunload', beforeUnload);
        window.addEventListener('pagehide', persist);
        window.addEventListener('storage', storage);
        document.addEventListener('visibilitychange', visibility);
        if (panel) panel.addEventListener('click', actions);
        function mode() { if (panel) panel.querySelector('[data-editor-draft-restore]').disabled = editor.mode.isReadOnly(); }
        editor.on('SwitchMode', mode); mode();
        findDraft();
        editor.on('remove', function() {
          persist(); removed = true; clearTimeout(timer);
          if (protections.get(editor.id) === state) protections.delete(editor.id);
          window.removeEventListener('beforeunload', beforeUnload); window.removeEventListener('pagehide', persist);
          window.removeEventListener('storage', storage); document.removeEventListener('visibilitychange', visibility);
          if (panel) panel.removeEventListener('click', actions);
        });
      });
    };
  }

  function configureAutosave(element, options, activity) {
    if (!element.dataset.editorAutosave) return;
    var config, messages;
    try { config = JSON.parse(element.dataset.editorAutosave); messages = JSON.parse(element.dataset.editorAutosaveMessages); } catch (error) { return; }
    var setup = options.setup;
    options.setup = function(editor) {
      if (typeof setup === 'function') setup(editor);
      editor.on('init', function() {
        var box = document.getElementById(element.id + '-autosave'); if (!box) return;
        var status = box.querySelector('[data-autosave-status]'), panel = box.querySelector('[data-autosave-recovery]');
        var retry = box.querySelector('[data-autosave-retry]'), removed = false, timer, busy = false, paused = true, loaded = false;
        var version = null, candidate = null, pendingPublish = null, published = editor.getContent(), saved = published, failures = 0, lastAttempt = 0;
        var interval = Math.max(5000, Number(config.interval) || 15000), endpoint;
        try {
          endpoint = new URL(config.url, typeof site_url === 'string' ? site_url : window.location.href);
          if (endpoint.origin !== window.location.origin || endpoint.username || endpoint.password || !/^https?:$/.test(endpoint.protocol)) throw new Error('origin');
        } catch (error) { status.textContent = messages.failed; return; }
        function alive() { return !removed && tinymce.get(editor.id) === editor && element.isConnected; }
        function show(key, text) { if (alive()) status.textContent = text || messages[key]; }
        function canonical(content) {
          return tinymce.html.Serializer({entity_encoding: editor.options.get('entity_encoding')}, editor.schema).serialize(editor.parser.parse(content));
        }
        function valid(data) {
          return data && (data.version === null || /^[a-f0-9]{32}$/.test(data.version)) && typeof data.available === 'boolean'
            && typeof data.content === 'string' && data.content.length <= 1048576 && typeof data.base_version === 'string';
        }
        function offer(data) {
          candidate = data; panel.hidden = !candidate;
          if (candidate) box.querySelector('[data-autosave-message]').textContent = messages[data.base_version === config.base_version ? 'available' : 'different'];
          mode();
        }
        function schedule() {
          clearTimeout(timer);
          if (!alive() || paused || busy || editor.mode.isReadOnly()) return;
          timer = setTimeout(pump, Math.max(1000, lastAttempt + interval - Date.now()));
        }
        function fail(response, transport) {
          failures++; show('failed', response && response.message);
          var retryable = !(response && response.code !== 'autosave_unavailable') && transport !== 401 && transport !== 403 && failures < 3;
          if (!retryable) {
            paused = true; retry.hidden = !!(response && response.code === 'autosave_conflict');
          }
          return retryable;
        }
        function request(action, extra, accept) {
          if (!alive() || busy || editor.mode.isReadOnly()) return;
          busy = true; lastAttempt = Date.now(); retry.hidden = true;
          var failed = false, retryable = false;
          var data = {action: action, version: version || '', base_version: config.base_version};
          $('#tokens').serializeArray().forEach(function(token) { data[token.name] = token.value; });
          $.extend(data, extra || {}); show(action === 'load' ? 'loading' : 'saving');
          $.ajax({type: 'POST', url: endpoint.href, dataType: 'json', data: data, timeout: 20000}).done(function(response) {
            if (!alive()) return;
            if (!response || response.status !== 'success' || !valid(response.data)) { failed = true; retryable = fail(response); return; }
            failures = 0; version = response.data.version; accept(response.data);
          }).fail(function(xhr) { if (alive()) { failed = true; retryable = fail(null, xhr.status); } }).always(function() {
            busy = false;
            if (!alive()) return;
            if (failed) {
              if (retryable) timer = setTimeout(function() { if (pendingPublish) pump(); else if (!loaded) load(); else { paused = false; pump(); } }, interval);
              else pendingPublish = null;
              return;
            }
            if (pendingPublish) pump();
            else if (!loaded && !paused) timer = setTimeout(load, interval);
            else schedule();
          });
        }
        function load() {
          paused = false;
          request('load', {}, function(data) {
            loaded = true;
            if (pendingPublish) return;
            if (data.available && canonical(data.content) !== published) { paused = true; offer(data); }
            else { paused = false; offer(null); saved = published; show('published'); }
          });
        }
        function pump() {
          clearTimeout(timer);
          if (!alive() || busy || editor.mode.isReadOnly() || activity.mediaPending) return schedule();
          if (pendingPublish) {
            var operation = pendingPublish;
            request('discard', {}, function() {
              if (pendingPublish !== operation) return;
              pendingPublish = null; paused = false; offer(null); saved = operation.content; show('published');
            });
            return;
          }
          if (!loaded || paused || editor.getContent() === saved) return;
          var snapshot = window.AdminRichTextEditor.getSaveSnapshot(editor.id); if (!snapshot) return;
          request('save', {content: snapshot.content}, function(data) {
            saved = canonical(data.content);
            if (!pendingPublish && saved !== snapshot.content) { paused = true; offer(data); }
            show('saved');
          });
        }
        function actions(event) {
          var button = event.target.closest('button'); if (!button || busy || editor.mode.isReadOnly()) return;
          if (button.hasAttribute('data-autosave-retry')) { failures = 0; loaded = false; paused = false; load(); }
          else if (candidate && button.hasAttribute('data-autosave-restore')) {
            var content = candidate.content; saved = canonical(content); offer(null); paused = false;
            editor.undoManager.transact(function() { editor.setContent(content); editor.nodeChanged(); });
            editor.focus(); editor.save({set_dirty: false}); schedule(); show('saved');
          } else if (candidate && button.hasAttribute('data-autosave-discard')) {
            if (typeof window.swalAlert !== 'function') return show('confirm_missing');
            var selected = candidate;
            Promise.resolve(window.swalAlert({icon: 'warning', title: messages.discard, text: messages.discard_question,
              showCancelButton: true, cancelButtonText: messages.cancel, confirmButtonText: messages.discard, focusCancel: true, allowEscapeKey: true
            })).then(function(result) {
              if (!alive() || !result.isConfirmed || candidate !== selected || busy) return;
              request('discard', {}, function() { offer(null); paused = false; saved = published; show('published'); editor.focus(); });
            }).catch(function() { if (alive()) show('failed'); });
          }
        }
        function publication(event) {
          published = event.snapshot.content;
          if (typeof event.baseVersion !== 'string' || !event.baseVersion || event.baseVersion.length > 128) {
            paused = true; pendingPublish = null; clearTimeout(timer); show('revision_missing'); return;
          }
          config.base_version = event.baseVersion;
          pendingPublish = {content: published}; paused = true;
          if (!busy) pump();
        }
        function mode() {
          box.querySelectorAll('button').forEach(function(button) { button.disabled = editor.mode.isReadOnly(); });
          if (!editor.mode.isReadOnly()) { if (!loaded && !busy) load(); else schedule(); }
        }
        editor.on('input change Undo Redo SetContent GFrameMediaState', schedule);
        editor.on('GFrameContentPublished', publication); editor.on('SwitchMode', mode);
        box.addEventListener('click', actions);
        editor.on('remove', function() { removed = true; clearTimeout(timer); box.removeEventListener('click', actions); });
        if (!editor.mode.isReadOnly()) load();
      });
    };
  }

  function configureSave(element, options, activity) {
    var buttonID = element.dataset.editorSaveButton;
    if (!buttonID) return;
    var setup = options.setup;
    options.add_form_submit_trigger = false;
    if (typeof options.toolbar === 'string') options.toolbar = 'gframeSave | ' + options.toolbar;
    options.setup = function(editor) {
      if (typeof setup === 'function') setup(editor);
      editor.on('submit', function() {
        if (editor.initialized) { normalizeTableCells(editor.getBody()); editor.save({set_dirty: false}); }
      });
      function target() {
        var button = document.getElementById(buttonID);
        return element.isConnected && element.form && button && button.form === element.form && /^(BUTTON|INPUT)$/.test(button.tagName)
          && /^(submit|button|image)$/.test(button.type) ? button : null;
      }
      function enabled() {
        var button = target();
        var pending = element.form && Array.from(element.form.querySelectorAll('textarea.js-rich-text-editor')).some(function(field) {
          var state = activities.get(field); return state && state.mediaPending;
        });
        return !!(button && !button.matches(':disabled') && button.getAttribute('aria-disabled') !== 'true' && !button.classList.contains('disabled') && !editor.mode.isReadOnly() && !pending);
      }
      function save() {
        if (!enabled()) return;
        var button = target();
        element.form.querySelectorAll('textarea.js-rich-text-editor').forEach(function(field) {
          var instance = tinymce.get(field.id);
          if (instance) { normalizeTableCells(instance.getBody()); instance.save({set_dirty: false}); }
        });
        // Reuse the owning form's click/validation/AJAX flow, including its success handling.
        button.click();
      }
      var button = target();
      var label = button ? (button.getAttribute('aria-label') || button.textContent.trim() || button.value) : '';
      label = label || element.dataset.editorSaveLabel;
      editor.ui.registry.addButton('gframeSave', {
        icon: 'save', tooltip: label, onAction: save,
        onSetup: function(api) {
          var update = function() { api.setEnabled(enabled()); };
          var observer = new MutationObserver(function(records) {
            if (records.some(function(record) { return record.type === 'childList' || record.target.id === buttonID; })) update();
          });
          if (element.form) observer.observe(element.form, {subtree: true, childList: true, attributes: true, attributeFilter: ['disabled', 'aria-disabled', 'class']});
          if (button && !element.form.contains(button)) observer.observe(button, {attributes: true, attributeFilter: ['disabled', 'aria-disabled', 'class']});
          editor.on('NodeChange SwitchMode GFrameMediaState', update);
          update();
          return function() { observer.disconnect(); editor.off('NodeChange SwitchMode GFrameMediaState', update); };
        }
      });
      editor.addCommand('gframeSave', save);
      editor.addShortcut('meta+s', label, save);
    };
  }

  function configureMedia(element, options, activity) {
    var setup = options.setup;
    options.extended_valid_elements = [options.extended_valid_elements, 'figure[data-media-gallery]', 'img[src|alt|title|width|height|data-media-id]'].filter(Boolean).join(',');
    if (typeof options.toolbar === 'string') {
      options.toolbar = /\bimage\b/.test(options.toolbar)
        ? options.toolbar.replace(/\bimage\b/, 'image gframeImage gframeGallery')
        : options.toolbar + ' | gframeImage gframeGallery';
    }
    var baseURL = typeof site_url === 'string' ? site_url : '/';
    options.content_css = [].concat(options.content_css || [], baseURL + 'public/css/modules/media-library/media-content.css');
    if (options.quickbars_image_toolbar === undefined) options.quickbars_image_toolbar = false;
    options.setup = function(editor) {
      if (typeof setup === 'function') setup(editor);
      var pending = false, removed = false;
      editor.on('remove', function() { removed = true; activity.mediaPending = false; notifyMediaState(element); });
      function alive() { return !removed && tinymce.get(editor.id) === editor && element.isConnected; }
      function notify(text) {
        if (!alive()) return;
        if (typeof window.alertToast === 'function') window.alertToast({icon: 'warning', title: text});
        else editor.notificationManager.open({text: text, type: 'warning'});
      }
      function openMedia(gallery) {
        if (editor.mode.isReadOnly()) return;
        if (pending) return notify(element.dataset.editorMediaPending);
        var pickerModal = document.getElementById('mediaPickerModal');
        if (!window.MediaPicker || !pickerModal || pickerModal.getAttribute('aria-hidden') !== 'true' || element.closest('.modal.show')) return notify(element.dataset.editorMediaMissing);
        var node = editor.selection.getNode();
        if (!gallery && editor.dom.getParent(node, 'figure[data-media-gallery="1"]')) return openMedia(true);
        var target = gallery ? editor.dom.getParent(node, 'figure[data-media-gallery="1"]') : (node.nodeName === 'IMG' ? node : null);
        if (gallery && target && (target.textContent.trim() !== '' || Array.from(target.querySelectorAll('img')).some(function(image) {
          return !/^[1-9][0-9]*$/.test(image.getAttribute('data-media-id') || '');
        }))) return notify(element.dataset.editorMediaCustom);
        var fullscreen = editor.plugins.fullscreen;
        var resumeFullscreen = fullscreen && fullscreen.isFullscreen() && !editor.options.get('fullscreen_native');
        if (fullscreen && fullscreen.isFullscreen()) editor.execCommand('mceFullScreen');
        function restoreFullscreen() {
          if (resumeFullscreen && alive() && !document.body.classList.contains('tox-fullscreen')) editor.execCommand('mceFullScreen');
        }
        var selected = [];
        if (target) {
          var images = gallery ? target.querySelectorAll('img[data-media-id]') : [target];
          Array.from(images).forEach(function(image) {
            var id = Number(image.getAttribute('data-media-id'));
            if (Number.isSafeInteger(id) && id > 0 && selected.indexOf(id) === -1) selected.push(id);
          });
        }
        var bookmark = editor.selection.getBookmark(2, true);
        var content = editor.getContent();
        pending = true;
        activity.mediaPending = true; notifyMediaState(element);
        // Picker resolves on confirm, before Bootstrap's fade has released its focus trap.
        var hidden = new Promise(function(resolve) { $(pickerModal).one('hidden.bs.modal.richTextMedia', resolve); });
        var picked;
        try {
          picked = window.MediaPicker.open({kind: 'images', multiple: gallery, selected: selected, max: 50, allowEmpty: false, saveSource: 'library'});
        } catch (error) {
          pending = activity.mediaPending = false; notifyMediaState(element);
          restoreFullscreen();
          $(pickerModal).off('hidden.bs.modal.richTextMedia'); return notify(element.dataset.editorMediaFailed);
        }
        Promise.resolve(picked).then(function(result) {
          if (!result || !alive()) return hidden.then(function() { if (alive()) editor.focus(); });
          var tokens = {};
          $('#tokens').serializeArray().forEach(function(token) { tokens[token.name] = token.value; });
          return new Promise(function(resolve, reject) {
            $.ajax({type: 'POST', url: baseURL + 'ajax/admin/media/field', dataType: 'json',
              data: $.extend(tokens, {media_ids: JSON.stringify(result.ids), variant: gallery ? 'editor_gallery' : 'editor_image'})
            }).done(resolve).fail(reject);
          }).then(function(response) {
            return hidden.then(function() {
              if (!alive()) return;
              if (response.status !== 'success') return notify(response.message || element.dataset.editorMediaFailed);
              if (editor.getContent() !== content || (target && !editor.getBody().contains(target))) return notify(element.dataset.editorMediaFailed);
              editor.focus();
              editor.undoManager.transact(function() {
                editor.selection.moveToBookmark(bookmark);
                if (target && gallery) {
                  editor.dom.setOuterHTML(target, response.html);
                } else if (target) {
                  var item = response.data[0];
                  editor.dom.setAttribs(target, {src: item.media_url, alt: item.alt_text || '', 'data-media-id': item.media_id});
                } else {
                  editor.insertContent(response.html);
                }
                editor.nodeChanged();
              });
              editor.save({set_dirty: false});
            });
          });
        }).catch(function() {
          return hidden.then(function() { if (alive()) { editor.focus(); notify(element.dataset.editorMediaFailed); } });
        }).finally(function() { pending = activity.mediaPending = false; restoreFullscreen(); if (alive()) notifyMediaState(element); });
      }
      function galleryAt(node) { return editor.dom.getParent(node, 'figure[data-media-gallery="1"]'); }
      function removeMedia(gallery) {
        if (editor.mode.isReadOnly() || pending) return;
        var node = editor.selection.getNode(), target = gallery ? galleryAt(node) : (node.nodeName === 'IMG' && !galleryAt(node) ? node : null);
        if (!target) return;
        editor.undoManager.transact(function() { editor.dom.remove(target); editor.nodeChanged(); });
        editor.focus(); editor.save({set_dirty: false});
      }
      editor.ui.registry.addButton('gframeImage', {text: element.dataset.editorMediaImage, onAction: function() { openMedia(false); }});
      editor.ui.registry.addButton('gframeGallery', {text: element.dataset.editorMediaGallery, onAction: function() { openMedia(true); }});
      editor.ui.registry.addButton('gframeReplaceImage', {text: element.dataset.editorMediaReplace, onAction: function() { openMedia(false); }});
      editor.ui.registry.addButton('gframeEditGallery', {text: element.dataset.editorMediaEditGallery, onAction: function() { openMedia(true); }});
      editor.ui.registry.addButton('gframeRemoveImage', {text: element.dataset.editorMediaRemoveImage, onAction: function() { removeMedia(false); }});
      editor.ui.registry.addButton('gframeRemoveGallery', {text: element.dataset.editorMediaRemoveGallery, onAction: function() { removeMedia(true); }});
      editor.ui.registry.addContextToolbar('gframeImageContext', {
        predicate: function(node) { return !editor.mode.isReadOnly() && node.nodeName === 'IMG' && !galleryAt(node); },
        items: 'gframeReplaceImage gframeRemoveImage', position: 'node', scope: 'node'
      });
      editor.ui.registry.addContextToolbar('gframeGalleryContext', {
        predicate: function(node) { return !editor.mode.isReadOnly() && !!galleryAt(node); },
        items: 'gframeEditGallery gframeRemoveGallery', position: 'node', scope: 'node'
      });
      editor.addCommand('gframeImage', function() { openMedia(false); });
      editor.addCommand('gframeGallery', function() { openMedia(true); });
    };
  }

  window.AdminRichTextEditor = {
    hasUnsavedChanges: function(editorID) {
      if (editorID !== undefined) {
        var state = protections.get(String(editorID)); return !!state && state.hasChanges();
      }
      return Array.from(protections.values()).some(function(state) { return state.hasChanges(); });
    },
    getSaveSnapshot: function(editorID) {
      var state = protections.get(String(editorID)); return state ? state.snapshot() : null;
    },
    markSaved: function(snapshot, baseVersion) {
      var entry = snapshot && saveSnapshots.get(snapshot);
      if (!entry || protections.get(snapshot.editorID) !== entry.state) return false;
      return entry.state.confirm(snapshot, entry.sequence, entry.recovered, baseVersion);
    },
    register: function(editorID, options) {
      registrations[String(editorID || "")] = options || {};
    },
    init: init,
    initAll: function(root) {
      (root || document).querySelectorAll("textarea.js-rich-text-editor").forEach(init);
    },
    saveAll: function() {
      if (typeof tinymce === "undefined") return;
      document.querySelectorAll("textarea.js-rich-text-editor").forEach(function(element) {
        var editor = tinymce.get(element.id);
        if (editor) normalizeTableCells(editor.getBody());
      });
      tinymce.triggerSave();
    },
    destroy: function(elementOrID) {
      if (typeof tinymce === "undefined") return;
      var id = typeof elementOrID === "string" ? elementOrID : elementOrID && elementOrID.id;
      var editor = id ? tinymce.get(id) : null;
      if (editor) editor.remove();
    },
    destroyAll: function(root) {
      if (typeof tinymce === "undefined") return;
      (root || document).querySelectorAll("textarea.js-rich-text-editor").forEach(function(element) {
        var editor = tinymce.get(element.id);
        if (editor) editor.remove();
      });
    }
  };

  $(function() {
    window.AdminRichTextEditor.initAll();
  });
})(window, document, jQuery);
