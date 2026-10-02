(function ($) {
    'use strict';
    $('[data-automatic-campaigns]').on('click', '[data-automatic-send]', function () {
        const button = $(this);
        if (button.prop('disabled')) return;
        button.prop('disabled', true);
        $.post(button.attr('data-send-url'), $.param($('#tokens').serializeArray().concat([{name: 'rule_key', value: button.attr('data-automatic-send')}]))).done(function (response) {
            alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || successError(response.message, response.code)});
        }).fail(function (xhr, status, error) { alertToast({icon: 'error', title: ajaxError(status, error)}); }).always(function () { button.prop('disabled', false); });
    });
    $('[data-automatic-campaigns]').on('submit', '[data-automatic-form]', function (event) {
        event.preventDefault();
        const form = this;
        if ($(form).data('saving')) return;
        if (!form.checkValidity()) { form.classList.add('was-validated'); validationFeedback($(form), {}); return; }
        $(form).data('saving', true);
        const buttons = $(form).find('button[type="submit"]').prop('disabled', true);
        $.post($(form).attr('data-save-url'), $.param($('#tokens').serializeArray().concat($(form).serializeArray())))
            .done(function (response) {
                alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || successError(response.message, response.code)});
                if (response.status !== 'success') return;
                if (!$(form).is('[data-manual-send]')) {
                    const key = $(form).find('[name="rule_key"]').val();
                    $('[data-rule-state="' + key + '"]').text(response.data.state_label);
                    $('[data-rule-cooldown="' + key + '"]').text(response.data.cooldown_days);
                }
                bootstrap.Modal.getOrCreateInstance($(form).closest('.modal').get(0)).hide();
            })
            .fail(function (xhr, status, error) { alertToast({icon: 'error', title: ajaxError(status, error)}); })
            .always(function () { $(form).data('saving', false); buttons.prop('disabled', false); });
    });
})(jQuery);
