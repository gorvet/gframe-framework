// AJAX/global error helpers.

function toErrorView(errorType, tolink, infoMsg) {
  var link = (typeof tolink === 'undefined' || tolink === '') ? window.location.href : tolink;
  var info = (typeof infoMsg === 'undefined') ? '' : infoMsg;
  $.ajax({
    type: 'POST',
    url: (typeof site_url !== 'undefined' ? site_url : '') + 'index.php',
    data: {
      errorType: errorType,
      tolink: link,
      infoMsg: info
    },
    dataType: 'html',
    complete: function(xhr) {
      var response = xhr.responseText || '';
      if (response === '') return;
      document.open();
      document.write(response);
      document.close();
    }
  });
}

function ajaxError(status, error) {
  var toAlertToast;
  if (status === 'timeout') {
    toAlertToast = { title: 'La solicitud ha tardado demasiado en responder.' };
  } else if (status === 'error') {
    toAlertToast = { title: 'Se ha producido un error en la solicitud: ' + error };
  } else if (status === 'abort') {
    toAlertToast = { title: 'La solicitud ha sido cancelada.' };
  } else if (status === 'parsererror') {
    toAlertToast = { title: 'No se puede analizar la respuesta JSON.' };
  } else {
    toAlertToast = { title: 'Ha ocurrido un error desconocido: ' + error };
  }
  return toAlertToast;
}

function successError(error, code) {
  var toAlertToast;
  if (code === 'no_token' || code === 'forbidden' || code === '403') {
    toAlertToast = {};
    toErrorView('forbidden');
  } else if (code === 'not_found' || code === '404') {
    toAlertToast = {};
    toErrorView('not_found');
  } else if (code === 'service_unavailable' || code === '503' || code == 1045 || code == 2002 || code == 1049) {
    toAlertToast = {};
    toErrorView('service_unavailable');
  } else if (code === 'to_reload' || code === 'to_login') {
    toAlertToast = {};
    location.reload();
  } else if (code === 'external_api_error' || code === '0') {
    toAlertToast = { title: 'No se pudo conectar con API externa.' };
  } else if (error !== '') {
    toAlertToast = { title: error };
  } else {
    toAlertToast = { title: 'Ha ocurrido un error desconocido: ' + code };
  }
  return toAlertToast;
}

(function() {
  $(document).ajaxSend(function(event, xhr) {
    xhr.setRequestHeader('X-GFrame-Page-Url', window.location.href);
  });

  $(document).ajaxComplete(function(event, xhr) {
    if (window.__gfAuthInactive === true) return;
    var contentType = xhr.getResponseHeader('content-type') || '';
    if (contentType.includes('text/html') && xhr.status === 200) {
      document.open();
      document.write(xhr.responseText);
      document.close();
    }
  });
})();

