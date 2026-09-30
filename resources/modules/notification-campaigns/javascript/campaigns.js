(function ($) {
    'use strict';

    const root = $('[data-campaigns-root]');
    if (!root.length) return;
    const endpoint = (path) => String(window.site_url || '').replace(/\/$/, '') + '/' + path;
    const tokens = () => $('#tokens').serializeArray();
    const notify = (response) => alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || 'No se pudo completar la operación.'});

    function reload() {
        const data = tokens().concat($('[data-campaign-filters]').serializeArray());
        $.post(endpoint('ajax/admin/notifications/campaigns/list'), $.param(data)).done(function (response) {
            if (response.status === 'success' && response.html) $('[data-campaign-list]').html(response.html);
            else notify(response);
        }).fail(function (xhr, status, error) { alertToast({icon: 'error', title: ajaxError(status, error)}); });
    }

    root.on('submit', '[data-campaign-form]', function (event) {
        event.preventDefault();
        const form = this;
        if (!form.checkValidity()) { form.classList.add('was-validated'); validationFeedback($(form), {}); return; }
        const data = tokens().concat($(form).serializeArray());
        $.post(endpoint('ajax/admin/notifications/campaigns/create'), $.param(data)).done(function (response) {
            notify(response);
            if (response.status === 'success') { form.reset(); form.classList.remove('was-validated'); reload(); }
        }).fail(function (xhr, status, error) { alertToast({icon: 'error', title: ajaxError(status, error)}); });
    });

    root.on('submit', '[data-campaign-filters]', function (event) { event.preventDefault(); reload(); });
    root.on('click', '[data-campaign-action]', function () {
        const button = $(this); const action = button.data('campaign-action');
        const execute = () => $.post(endpoint('ajax/admin/notifications/campaigns/action'), $.param(tokens().concat([{name: 'campaign_id', value: button.data('campaign-id')}, {name: 'action', value: action}]))).done(function (response) { notify(response); if (response.status === 'success') reload(); }).fail(function (xhr, status, error) { alertToast({icon: 'error', title: ajaxError(status, error)}); });
        if (action === 'cancel') swalAlert({title: '¿Cancelar esta campaña?', text: 'Los trabajos ya enviados a la cola no se eliminarán.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Cancelar campaña'}).then(function (result) { if (result.isConfirmed) execute(); }); else execute();
    });
})(jQuery);
