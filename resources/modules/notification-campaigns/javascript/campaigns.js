(function ($) {
    'use strict';

    const root = $('[data-campaigns-root]');
    if (!root.length) return;
    root.find('[name="user_timezone"]').val(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC');
    const schedule = root.find('#campaign-scheduled');
    if (schedule.length) {
        const stored = schedule.attr('data-scheduled-utc');
        const defaultDate = stored ? new Date(stored.replace(' ', 'T') + 'Z') : null;
        flatpickr('#campaign-scheduled', {enableTime: true, time_24hr: true, dateFormat: 'Y-m-d H:i:S', altInput: true, altFormat: 'd/m/Y H:i', locale: 'es', disableMobile: true, defaultDate: defaultDate});
    }
    function updateSaveLabel() {
        const button = root.find('[data-save-label]');
        const label = root.find('[data-campaign-form]').attr('data-editing') === '1' ? 'data-label-edit' : (schedule.val() ? 'data-label-schedule' : 'data-label-send');
        button.text(button.attr(label));
    }
    root.on('change input', '#campaign-scheduled', updateSaveLabel);
    updateSaveLabel();
    const userSelect = root.find('#campaign-users').get(0);
    if (userSelect) new GFSelect(userSelect, {multiple: true, searchable: true, loadStyles: false});
    function updateAudience() {
        const manual = root.find('#campaign-audience').val() === 'manual';
        root.find('[data-manual-audience]').prop('hidden', !manual);
        root.find('#campaign-users').prop('disabled', !manual).prop('required', manual);
    }
    updateAudience();
    root.on('change', '#campaign-audience', updateAudience);
    let placeholderField = null;
    root.on('focusin', '#campaign-title, #campaign-message, #campaign-action-url', function () {
        placeholderField = this;
    });
    root.on('mousedown', '.js-notif-placeholder', function (event) {
        event.preventDefault();
    });
    function insertPlaceholder($field, placeholder) {
        if (!$field.length || !placeholder) return;
        var input = $field.get(0);
        var value = String($field.val() || '');
        var start = input && typeof input.selectionStart === 'number' ? input.selectionStart : value.length;
        var end = input && typeof input.selectionEnd === 'number' ? input.selectionEnd : value.length;
        $field.val(value.slice(0, start) + placeholder + value.slice(end)).trigger('input').trigger('focus');
        if (input && typeof input.setSelectionRange === 'function') input.setSelectionRange(start + placeholder.length, start + placeholder.length);
    }
    root.on('click', '.js-notif-placeholder', function () {
        if (placeholderField) insertPlaceholder($(placeholderField), String($(this).attr('data-placeholder') || ''));
    });
    const tokens = () => $('#tokens').serializeArray();
    const notify = (response) => alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || successError(response.message, response.code)});
    const fail = (xhr, status, error) => alertToast({icon: 'error', title: ajaxError(status, error)});
    let page = Number(root.attr('data-page') || 1);
    let request = null;
    let revision = 0;

    function reload(targetPage) {
        page = targetPage || 1;
        const current = ++revision;
        if (request) request.abort();
        const data = tokens().concat(root.find('[data-campaign-filters]').serializeArray(), [{name: 'page', value: page}]);
        request = $.post(root.attr('data-list-url'), $.param(data)).done(function (response) {
            if (current !== revision) return;
            if (response.status === 'success' && typeof response.html === 'string') {
                root.find('[data-campaign-list]').html(response.html);
                page = Number(response.meta.page);
            } else notify(response);
        }).fail(function (xhr, status, error) { if (status !== 'abort') fail(xhr, status, error); });
    }

    root.on('submit', '[data-campaign-form]', function (event) {
        event.preventDefault();
        const form = this;
        if ($(form).data('saving')) return;
        if (!form.checkValidity()) { form.classList.add('was-validated'); validationFeedback($(form), {}); return; }
        const buttons = $(form).find('button[type="submit"]');
        $(form).data('saving', true);
        buttons.prop('disabled', true);
        const data = tokens().concat($(form).serializeArray());
        $.post($(form).attr('data-save-url'), $.param(data)).done(function (response) {
            if (response.status === 'success') window.location.assign($(form).attr('data-return-url'));
            else notify(response);
        }).fail(fail).always(function () { $(form).data('saving', false); buttons.prop('disabled', false); });
    });
    root.on('click', '[data-preview-audience], [data-test-campaign]', function () {
        const button = $(this);
        const form = root.find('[data-campaign-form]');
        button.prop('disabled', true);
        $.post(button.attr('data-url'), $.param(tokens().concat(form.serializeArray())))
            .done(function (response) {
                if (button.is('[data-preview-audience]') && response.status === 'success') {
                    root.find('[data-audience-preview]').html(response.html);
                    bootstrap.Modal.getOrCreateInstance(root.find('#campaign-audience-preview').get(0)).show();
                } else notify(response);
            }).fail(fail).always(function () { button.prop('disabled', false); });
    });

    root.on('submit', '[data-campaign-filters]', function (event) { event.preventDefault(); reload(1); });
    root.on('change', '[data-campaign-filters] select', function () { reload(1); });
    root.on('click', '[data-campaign-list] .pagination a', function (event) {
        event.preventDefault();
        const link = $(this);
        const target = link.hasClass('prev') ? page - 1 : (link.hasClass('next') ? page + 1 : Number(link.text()));
        if (Number.isInteger(target) && target > 0) reload(target);
    });
    root.on('click', '[data-campaign-action]', function () {
        const button = $(this);
        if (button.prop('disabled')) return;
        const action = button.attr('data-campaign-action');
        const execute = function () {
            button.prop('disabled', true);
            $.post(root.attr('data-action-url'), $.param(tokens().concat([{name: 'campaign_id', value: button.attr('data-campaign-id')}, {name: 'action', value: action}]))).done(function (response) {
                notify(response);
                if (response.status === 'success') reload(page);
            }).fail(fail).always(function () { button.prop('disabled', false); });
        };
        if (action === 'cancel') swalAlert({title: '¿Cancelar esta campaña?', text: 'Los trabajos ya enviados a la cola no se eliminarán.', icon: 'warning', showCancelButton: true, reverseButtons: true, confirmButtonText: 'Cancelar campaña'}).then(function (result) { if (result.isConfirmed) execute(); });
        else execute();
    });
})(jQuery);
