(function($) {
  "use strict";

  var mount = $("#knowledgeMediaList");
  if (!mount.length || typeof window.MediaLibrary !== "function") return;
  var messages = JSON.parse(document.getElementById('media-admin-messages').textContent);

  function tokens() {
    var data = {};
    $("#tokens").serializeArray().forEach(function(item) { data[item.name] = item.value; });
    return data;
  }

  function request(url, data, current) {
    return $.ajax({ type: "POST", url: site_url + url, data: data, dataType: "json" }).fail(function(xhr, status, error) {
      if (current && !current()) return;
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
    resultsRoot: '#knowledgeMediaUpload',
    dropZone: "#knowledgeMediaUpload [data-ml-dropzone]",
    fileInput: "#knowledgeMediaFile",
    uploadButton: "#knowledgeMediaSelect"
  });
  window.MediaLibrary.setActive(library);
  library.initFromUrlOrDom();

  var modalElement = document.getElementById("editMediaModal");
  var modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;

  var sequence = 0, busy = false, saving = false, activeID = 0, baseline = null, returnFocusID = 0;
  var $modal = $(modalElement), $form = $('#editMedia');
  var $status = $('#media-details-status');
  function values() { return {name: $('#media-original-name').val(), alt: $('#alt_text').val()}; }
  function dirty() { return baseline && (values().name !== baseline.name || values().alt !== baseline.alt); }
  function status(text, focus) { $status.text(text || ''); if (focus) $status.trigger('focus'); }
  function mayLeave() {
    if (saving || dirty()) { status(saving ? messages.saving : messages.pending, true); return false; }
    return true;
  }
  function releasePreview() {
    $modal.find('audio, video').each(function() { this.pause(); this.removeAttribute('src'); this.load(); }).addClass('d-none');
    $('#img_details').addClass('d-none').removeAttr('src');
    $('#file_details').addClass('d-none');
  }
  function listIDs() { return mount.find('.m-list-card').map(function() { return Number($(this).attr('data-media-id')); }).get(); }
  function updateControls() {
    var ids = listIDs(), index = ids.indexOf(activeID), $meta = mount.find('[data-ml-page]');
    var page = Number($meta.attr('data-ml-page') || 1), last = Number($meta.attr('data-ml-total-pages') || 1);
    $('#editMediaPrev').prop('disabled', busy || saving || index < 0 || (index === 0 && page <= 1));
    $('#editMediaNext').prop('disabled', busy || saving || index < 0 || (index === ids.length - 1 && page >= last));
    $('#editMedia_guardar, #media-original-name, #alt_text').prop('disabled', busy || saving || !activeID);
    $('#media-details-discard').toggleClass('d-none', !dirty()).prop('disabled', saving || busy);
    $('#delmedia').toggleClass('disabled', busy || saving || !activeID).attr('aria-disabled', busy || saving || !activeID ? 'true' : 'false');
  }
  $form.on('input', updateControls).on('submit', function(event) { event.preventDefault(); $('#editMedia_guardar').trigger('click'); });
  $('#media-details-discard').on('click', function() {
    if (saving || busy || !baseline) return;
    $('#media-original-name').val(baseline.name); $('#alt_text').val(baseline.alt);
    $form.removeClass('was-validated'); status(''); updateControls();
  });
  function restoreFocus() {
    var $target = mount.find('.m-list-card[data-media-id="' + returnFocusID + '"]').first();
    if (!$target.length) $target = mount.find('[data-ml-filter="view"]').first();
    $target.trigger('focus');
  }
  $modal.on('hide.bs.modal', function(event) {
    if (!mayLeave()) { event.preventDefault(); return; }
    returnFocusID = activeID;
    ++sequence; busy = false; activeID = 0; baseline = null; releasePreview(); updateControls();
  }).on('hidden.bs.modal', function() {
    restoreFocus();
  });
  mount.on('ml:render.knowledgeMedia', function() {
    updateControls();
    if (returnFocusID && !activeID && !$modal.hasClass('show') && document.activeElement === document.body) restoreFocus();
  });

  $(document).on("click", "#knowledgeMediaAdd", function() {
    $("#knowledgeMediaUpload").removeClass("d-none");
  });

  $(document).on("click", "#knowledgeMediaClose", function() {
    $("#knowledgeMediaUpload").addClass("d-none");
  });

  function openDetails(id) {
    if (!mayLeave()) return;
    var epoch = ++sequence;
    busy = true; status(messages.loading); updateControls();
    request("ajax/admin/media/details", $.extend(tokens(), { media_id: id }), function() { return epoch === sequence; }).done(function(response) {
      if (epoch !== sequence) return;
      if (response.status !== "success") return notifyError(response);
      releasePreview();
      var media = response.data || {};
      activeID = Number(media.media_id);
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
      $("#file_size").text(width && height ? width + " × " + height + ' ' + messages.pixels : "-");
      $('#media-original-name').val(media.original_name || media.name || '');
      $("#alt_text").val(media.alt_text || "");
      $("#img_url").val(absoluteUrl(media.media_url || ""));
      $('#media-open-file').attr('href', absoluteUrl(media.media_url || ''));
      baseline = values(); $form.removeClass('was-validated');
      if (media.type === "images") {
        $("#img_details").removeClass("d-none").attr("src", absoluteUrl(media.media_url || "")).attr("alt", media.alt_text || "");
        $("#file_details").addClass("d-none");
      } else if (media.type === 'audios' || media.type === 'videos') {
        $(media.type === 'audios' ? '#media-details-audio' : '#media-details-video').removeClass('d-none').attr('src', absoluteUrl(media.media_url));
      } else {
        $("#img_details").addClass("d-none");
        $("#file_details").removeClass("d-none");
        $("#file_details_ext").text(String(media.ext || "FILE").toUpperCase());
      }
      if (modal) modal.show();
      status('');
    }).always(function() { if (epoch === sequence) { busy = false; updateControls(); if ($status.text() === messages.loading) status(''); } });
  }
  mount.on("ml:itemClick.knowledgeMedia", function(event, item) {
    openDetails(item.id || item.media_id || 0);
  });
  function navigate(step) {
    if (busy || saving || !mayLeave()) return;
    var ids = listIDs(), index = ids.indexOf(activeID), target = index + step;
    if (index < 0) return;
    if (target >= 0 && target < ids.length) { openDetails(ids[target]); return; }
    var $meta = mount.find('[data-ml-page]'), page = Number($meta.attr('data-ml-page') || 1) + step;
    if (page < 1 || page > Number($meta.attr('data-ml-total-pages') || 1)) return;
    var epoch = ++sequence; busy = true; status(messages.loading); updateControls();
    var pending = library.load(page, {push: true, makeActive: true}), listSequence = library._loadSequence;
    pending.done(function(response) {
      if (epoch !== sequence || listSequence !== library._loadSequence || response.status !== 'success') return;
      var next = listIDs(); busy = false;
      if (next.length) openDetails(step > 0 ? next[0] : next[next.length - 1]);
    }).always(function() { if (epoch === sequence) { busy = false; status(''); updateControls(); } });
  }
  $('#editMediaPrev').on('click', function() { navigate(-1); });
  $('#editMediaNext').on('click', function() { navigate(1); });

  $(document).on("click", "#editMedia_guardar", function(event) {
    event.preventDefault();
    if (busy || saving || !activeID) return;
    if (!$form.get(0).checkValidity()) { $form.addClass('was-validated'); $form.get(0).reportValidity(); return; }
    var epoch = sequence;
    saving = true; status(messages.saving); updateControls();
    request("ajax/admin/media/save", $.extend(tokens(), {
      media_id: $("#media_id").val(),
      alt_text: $("#alt_text").val(), original_name: $('#media-original-name').val()
    }), function() { return epoch === sequence; }).done(function(response) {
      if (epoch !== sequence) return;
      if (response.status !== "success") return notifyError(response);
      var media = response.data || {};
      $('#media-original-name').val(media.original_name || ''); $('#alt_text').val(media.alt_text || '');
      baseline = values(); status('');
      library.reload({ push: false, makeActive: true });
      alertToast({ icon: "success", title: response.message });
    }).always(function() { if (epoch === sequence) { saving = false; updateControls(); if ($status.text() === messages.saving) status(''); } });
  });

  $(document).on("click", "#delmedia", function(event) {
    event.preventDefault();
    if (busy || saving || !activeID || !mayLeave()) return;
    var deleteID = activeID, epoch = sequence;
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
      if (epoch !== sequence || activeID !== deleteID) return;
      saving = true; updateControls();
      request("ajax/admin/media/delete", $.extend(tokens(), { media_id: deleteID })).done(function(response) {
        if (response.status !== "success") return notifyError(response);
        saving = false; baseline = null;
        if (modal) modal.hide();
        library.reload({ push: false, makeActive: true });
        alertToast({ icon: "success", title: response.message });
      }).always(function() { saving = false; updateControls(); });
    });
  });

  $(document).on("click", "#copyUrlBtn", function() {
    var value = String($("#img_url").val() || "");
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(function() {
        alertToast({ icon: "success", title: messages.copied });
      }).catch(function() { alertToast({icon: 'error', title: messages.failed}); });
    }
  });
})(jQuery);
