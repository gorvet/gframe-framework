(function($) {
  "use strict";

  var mount = $("#knowledgeMediaList");
  if (!mount.length || typeof window.MediaLibrary !== "function") return;

  function tokens() {
    var data = {};
    $("#tokens").serializeArray().forEach(function(item) { data[item.name] = item.value; });
    return data;
  }

  function request(url, data) {
    return $.ajax({ type: "POST", url: site_url + url, data: data, dataType: "json" }).fail(function(xhr, status, error) {
      var mapped = typeof ajaxError === "function" ? ajaxError(status, error) : null;
      alertToast({ icon: "error", title: mapped && mapped.title ? mapped.title : "No se pudo completar la operación." });
    });
  }

  function notifyError(response) {
    var mapped = typeof successError === "function" ? successError(response.message || "", response.code || "") : null;
    alertToast({ icon: "error", title: mapped && mapped.title ? mapped.title : (response.message || "No se pudo completar la operación.") });
  }

  function absoluteUrl(path) {
    path = String(path || "").trim();
    if (!path || /^https?:\/\//i.test(path)) return path;
    return site_url + path.replace(/^\/+/, "");
  }

  function formatSize(meta) {
    var bytes = parseInt(meta && meta.original ? meta.original.size_bytes || "0" : "0", 10);
    if (!bytes) return "-";
    var units = ["B", "KB", "MB", "GB"];
    var index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return (bytes / Math.pow(1024, index)).toLocaleString("es", { maximumFractionDigits: 1 }) + " " + units[index];
  }

  var library = new window.MediaLibrary({
    mount: mount,
    tokens: $("#tokens"),
    mode: "manage",
    syncUrlEnabled: true,
    endpoints: {
      list: site_url + "ajax/admin/media/list",
      upload: site_url + "ajax/admin/media/upload"
    },
    saveSource: "library",
    uploadMaxMB: parseFloat(mount.attr("data-ml-max-mb")) || 0
  });

  library.attachUpload({
    root: document,
    dropZone: "#knowledgeMediaUpload [data-ml-dropzone]",
    fileInput: "#knowledgeMediaFile",
    uploadButton: "#knowledgeMediaSelect"
  });
  window.MediaLibrary.setActive(library);

  var modalElement = document.getElementById("editMediaModal");
  var modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;

  $("#editMediaPrev, #editMediaNext").addClass("d-none");

  $(document).on("click", "#knowledgeMediaAdd", function() {
    $("#knowledgeMediaUpload").removeClass("d-none");
  });

  $(document).on("click", "#knowledgeMediaClose", function() {
    $("#knowledgeMediaUpload").addClass("d-none");
  });

  mount.on("ml:itemClick.knowledgeMedia", function(event, item) {
    request("ajax/admin/media/details", $.extend(tokens(), { media_id: item.id || item.media_id || 0 })).done(function(response) {
      if (response.status !== "success") return notifyError(response);
      var media = response.data || {};
      var meta = {};
      try { meta = JSON.parse(media.meta_json || "{}"); } catch (error) { meta = {}; }
      $("#media_id").val(media.media_id || "");
      $("#upload_date").text(media.created_at || "-");
      $("#uploaded_by").text(media.uploaded_by || "-");
      $("#uploaded_to").text(media.uploaded_to || "-");
      $("#mime_type").text(media.mime_type || "-");
      $("#name").text(media.name || "-");
      $("#file_weight").text(formatSize(meta));
      var width = meta.original && meta.original.width ? meta.original.width : 0;
      var height = meta.original && meta.original.height ? meta.original.height : 0;
      $("#file_size").text(width && height ? width + " × " + height + " píxeles" : "-");
      $("#alt_text").val(media.alt_text || "");
      $("#img_url").val(absoluteUrl(media.media_url || ""));
      if (media.type === "images") {
        $("#img_details").removeClass("d-none").attr("src", absoluteUrl(media.media_url || "")).attr("alt", media.alt_text || "");
        $("#file_details").addClass("d-none");
      } else {
        $("#img_details").addClass("d-none");
        $("#file_details").removeClass("d-none");
        $("#file_details_ext").text(String(media.ext || "FILE").toUpperCase());
      }
      if (modal) modal.show();
    });
  });

  $(document).on("click", "#editMedia_guardar", function(event) {
    event.preventDefault();
    request("ajax/admin/media/save", $.extend(tokens(), {
      media_id: $("#media_id").val(),
      alt_text: $("#alt_text").val()
    })).done(function(response) {
      if (response.status !== "success") return notifyError(response);
      if (modal) modal.hide();
      library.reload({ push: false, makeActive: true });
      alertToast({ icon: "success", title: response.message });
    });
  });

  $(document).on("click", "#delmedia", function(event) {
    event.preventDefault();
    swalAlert({
      icon: "warning",
      title: "¿Eliminar este archivo?",
      text: "Esta acción no se puede deshacer.",
      confirmButtonText: "Eliminar",
      cancelButtonText: "Cancelar",
      showCancelButton: true,
      customClass: { confirmButton: "btn btn-danger order-2", cancelButton: "btn btn-secondary" }
    }).then(function(result) {
      if (!result.isConfirmed) return;
      request("ajax/admin/media/delete", $.extend(tokens(), { media_id: $("#media_id").val() })).done(function(response) {
        if (response.status !== "success") return notifyError(response);
        if (modal) modal.hide();
        library.load(1, { push: false, makeActive: true });
        alertToast({ icon: "success", title: response.message });
      });
    });
  });

  $(document).on("click", "#copyUrlBtn", function() {
    var value = String($("#img_url").val() || "");
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(function() {
        alertToast({ icon: "success", title: "URL copiada." });
      });
    }
  });
})(jQuery);
