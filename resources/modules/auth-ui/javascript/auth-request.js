// Recupera únicamente solicitudes rechazadas por CSRF antes de la acción.
function authRequest(options) {
  const result = jQuery.Deferred();
  const settings = Object.assign({}, options);
  delete settings.success;
  delete settings.error;
  delete settings.complete;
  const recoveryMessage = 'El formulario caducó y no se pudo renovar. Recarga la página e inténtalo de nuevo.';

  if (options.success) result.done(options.success);
  if (options.error) result.fail(options.error);
  if (options.complete) result.always(options.complete);

  function rejectRecovery() {
    // Se entrega como error de negocio para que el formulario libere su estado.
    result.resolve({status: 'error', code: 'csrf_recovery_failed', message: recoveryMessage});
  }

  function recover() {
    let loginURL;
    try {
      loginURL = new URL((typeof site_url !== 'undefined' ? site_url : '/') + 'login', window.location.href);
      if (loginURL.origin !== window.location.origin) { rejectRecovery(); return; }
    } catch (_) { rejectRecovery(); return; }
    jQuery.ajax({
      type: 'GET', url: loginURL.href, dataType: 'html', cache: false,
      global: false, timeout: 15000
    }).done(html => {
      const page = new DOMParser().parseFromString(html, 'text/html');
      const form = page.querySelector('#login, #auth-login-form');
      const token = form?.querySelector('input[name="csrfToken"]')?.value;
      const timestamp = form?.querySelector('input[name="csrfTimestamp"]')?.value;
      if (!token || !/^\d+$/.test(timestamp || '')) { rejectRecovery(); return; }
      const payload = new URLSearchParams(settings.data);
      payload.set('csrfToken', token);
      payload.set('csrfTimestamp', timestamp);
      settings.data = payload.toString();
      document.querySelectorAll('input[name="csrfToken"]').forEach(input => { input.value = token; });
      document.querySelectorAll('input[name="csrfTimestamp"]').forEach(input => { input.value = timestamp; });
      send(true);
    }).fail(rejectRecovery);
  }

  function handle(response, retried, resolve) {
    if (response && (response.code === 'to_reload' || response.code === 'fail_csrf')) {
      if (retried) rejectRecovery();
      else recover();
      return;
    }
    resolve();
  }

  function send(retried) {
    jQuery.ajax(settings).done((response, status, xhr) => {
      handle(response, retried, () => result.resolve(response, status, xhr));
    }).fail((xhr, status, error) => {
      handle(xhr.responseJSON, retried, () => result.reject(xhr, status, error));
    });
  }
  send(false);
  return result.promise();
}
