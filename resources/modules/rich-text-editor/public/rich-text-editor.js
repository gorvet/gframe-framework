(function(window, document, $) {
  "use strict";

  var registrations = {};

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
    return {
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
      resize: true,
      plugins: "lists link autolink image table code preview autoresize quickbars",
      toolbar: "undo redo | blocks | bold italic underline | bullist numlist | link unlink image table | blockquote hr | removeformat code preview",
      toolbar_mode: "sliding",
      toolbar_sticky: true,
      toolbar_sticky_offset: 60,
      quickbars_selection_toolbar: "bold italic underline | bullist numlist | link",
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
      },
      content_style: "body { font-family: Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.65; } h1 { font-size: 2rem; } h2 { font-size: 1.75rem; } h3 { font-size: 1.4rem; } h4 { font-size: 1.2rem; } h5 { font-size: 1.1rem; } h6 { font-size: 1rem; } img { max-width: 100%; height: auto; } table { width: 100%; border-collapse: collapse; font-size: 0.8rem; } td, th { border: 1px solid #d9dee5; padding: 0.5rem; } td > p, th > p { margin: 0; } thead th { background: #e9ecef; font-weight: 700; } tbody tr:nth-child(odd) td { background: #f8f9fa; }"
    };
  }

  function init(element) {
    if (!element || typeof tinymce === "undefined" || tinymce.get(element.id)) return;
    var options = $.extend(true, {}, baseOptions(element), registrations[element.id] || {});
    options.target = element;
    tinymce.init(options);
  }

  window.AdminRichTextEditor = {
    register: function(editorID, options) {
      registrations[String(editorID || "")] = options || {};
    },
    init: init,
    initAll: function() {
      document.querySelectorAll("textarea.js-rich-text-editor").forEach(init);
    },
    saveAll: function() {
      if (typeof tinymce === "undefined") return;
      document.querySelectorAll("textarea.js-rich-text-editor").forEach(function(element) {
        var editor = tinymce.get(element.id);
        if (editor) normalizeTableCells(editor.getBody());
      });
      tinymce.triggerSave();
    }
  };

  $(function() {
    window.AdminRichTextEditor.initAll();
  });
})(window, document, jQuery);
