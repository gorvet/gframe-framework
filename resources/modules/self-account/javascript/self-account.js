(function ($) {
    'use strict';

    function request(url, data) {
        const tokens = $('#self-account-tokens').serialize();
        const payload = typeof data === 'string'
            ? [tokens, data].filter(Boolean).join('&')
            : $.extend({}, Object.fromEntries(new URLSearchParams(tokens)), data || {});
        return $.ajax({
            type: 'POST',
            url: site_url + url,
            data: payload,
            dataType: 'json'
        });
    }

    $('#account-password-form').on('submit', function (event) {
        event.preventDefault();
        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            return;
        }

        const form = this;
        const button = $('#account-password-button').prop('disabled', true).text('Actualizando…');
        request('ajax/account/password', $(form).serialize())
            .done(function (response) {
                alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || 'No se pudo actualizar la contraseña.'});
                if (response.status === 'success') {
                    form.reset();
                    form.classList.remove('was-validated');
                }
            })
            .fail(function () {
                alertToast({icon: 'error', title: 'No se pudo completar la solicitud.'});
            })
            .always(function () {
                button.prop('disabled', false).text('Cambiar contraseña');
            });
    });

    $('#account-deactivate-button').on('click', function () {
        swalAlert({
            icon: 'warning',
            title: '¿Desactivar tu cuenta?',
            text: $(this).attr('data-confirm-message'),
            input: 'password',
            inputPlaceholder: 'Contraseña actual',
            showCancelButton: true,
            confirmButtonText: 'Desactivar cuenta',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            inputValidator: function (value) {
                return value ? undefined : 'Escribe tu contraseña.';
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            request('ajax/account/deactivate', {password: result.value}).done(function (response) {
                if (response.status === 'success') {
                    window.location.assign(site_url + (response.redirect || 'login'));
                    return;
                }
                alertToast({icon: 'error', title: response.message || 'No se pudo desactivar la cuenta.'});
            }).fail(function () {
                alertToast({icon: 'error', title: 'No se pudo completar la solicitud.'});
            });
        });
    });
})(jQuery);
