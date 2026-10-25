(function ($) {
    'use strict';
    const form = $('#user-create-form');
    let saving = false;
    form.on('submit', function (event) {
        event.preventDefault();
        if (saving) return;
        form.addClass('was-validated');
        if (!this.checkValidity()) return;
        saving = true;
        const button = form.find('[type="submit"]').prop('disabled', true);
        $.ajax({url: form.data('save-url'), method: 'POST', dataType: 'json', data: form.serialize()})
            .done(function (response) {
                const created = response.status === 'success';
                const warning = response.code === 'user_created_mail_failed';
                alertToast({icon: created ? (warning ? 'warning' : 'success') : 'error', title: response.message});
                $('#user-create-result').prop('hidden', false)
                    .removeClass('alert-success alert-warning alert-danger')
                    .addClass(created ? (warning ? 'alert-warning' : 'alert-success') : 'alert-danger')
                    .text(response.message);
                if (created) {
                    form.find('input, select').prop('disabled', true);
                } else {
                    saving = false;
                    button.prop('disabled', false);
                }
            }).fail(function (xhr, status, error) {
                saving = false;
                button.prop('disabled', false);
                alertToast(Object.assign({icon: 'error'}, ajaxError(status, error)));
            });
    });
})(jQuery);
