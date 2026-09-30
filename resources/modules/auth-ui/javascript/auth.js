(function ($) {
    'use strict';
    const base = String(window.site_url || '/');
    const returnPath = new URLSearchParams(window.location.search).get('rd') || '';
    const formSelectors = '#auth-login-form, #auth-register-form, #auth-recovery-form, #auth-reset-form';

    function validate(form) {
        const password = form.querySelector('#register_password, #reset_password');
        if (password) {
            const valid = passwordValidate(password.value, form.querySelector('input[type="email"]')?.value || '');
            password.setCustomValidity(valid ? '' : 'La contraseña debe tener entre 8 y 72 bytes UTF-8.');
        }
        const messages = {};
        form.querySelectorAll('input[id]').forEach(input => {
            messages[input.id] = {
                valueMissing: input.type === 'email' ? 'Introduce tu correo electrónico.' : 'Completa este campo.',
                typeMismatch: 'Introduce un correo electrónico válido.',
                customError: input.validationMessage
            };
        });
        form.classList.add('was-validated');
        $(form).find('.invalid-feedback').removeClass('d-block');
        if (form.checkValidity()) return true;
        validationFeedback($(form), messages);
        form.querySelectorAll('input:invalid').forEach(input => {
            if (input.id) $(form).find('.validation_' + input.id).addClass('d-block');
        });
        form.querySelector(':invalid')?.focus();
        return false;
    }

    function errorFeedback(response) {
        const code = response.code;
        const message = response.message || 'No se pudo completar la solicitud.';
        if (code === 'unverified_account') {
            swalAlert({ icon: 'warning', title: 'Cuenta sin verificar', html: document.getElementById('auth-unverified-message')?.innerHTML || message });
        } else if (code === 'user_exists') {
            swalAlert({ icon: 'warning', title: message, html: document.getElementById('auth-existing-message')?.innerHTML || '' });
        } else if (code === 'invalid_user' || code === 'suspended_account' || code === 'invalid_token') {
            swalAlert({ icon: 'error', title: message });
        } else {
            const options = successError(message, code);
            if (options.title) alertToast({ icon: 'error', title: options.title });
        }
    }

    function submit(form, verification = false) {
        if (form.dataset.submitting === '1' || !validate(form)) return;
        form.dataset.submitting = '1';
        const buttons = $(form).find('button');
        buttons.prop('disabled', true);
        const payload = $(form).serializeArray();
        if (form.id === 'auth-login-form' && !verification) payload.push({ name: 'rd', value: returnPath });
        $.ajax({
            type: 'POST',
            url: verification ? base + 'ajax/verification' : form.action,
            data: $.param(payload),
            dataType: 'json'
        }).done(response => {
            if (response.code === 'already_logged') {
                window.location.assign(base + 'login');
                return;
            }
            if (response.status !== 'success') { errorFeedback(response); return; }
            if (form.id === 'auth-login-form' && !verification) {
                window.location.assign(response.redirect || base);
                return;
            }
            const message = response.message || 'Solicitud completada.';
            swalAlert({ icon: 'success', title: message, confirmButtonText: 'Volver al acceso' })
                .then(result => { if (result.isConfirmed) window.location.assign(base + 'login'); });
        }).fail((xhr, status) => {
            if (xhr.responseJSON?.code === 'already_logged') {
                window.location.assign(base + 'login');
                return;
            }
            const options = ajaxError(status, '');
            alertToast({ icon: 'error', title: options.title || 'No se pudo completar la solicitud.' });
        }).always(() => {
            buttons.prop('disabled', false);
            delete form.dataset.submitting;
        });
    }

    $(formSelectors).on('submit', function (event) {
        event.preventDefault();
        submit(this);
    });
    $(document).on('click', '[data-auth-resend]', function (event) {
        event.preventDefault();
        const form = document.getElementById('auth-login-form');
        if (form) submit(form, true);
    });

    const password = document.querySelector('#register_password, #reset_password');
    if (password) {
        const generated = generatePassword();
        if (generated) password.value = generated;
        const update = () => {
            const email = document.querySelector('#register_email')?.value || '';
            updateMeterPassword(password.value, email);
            password.setCustomValidity(passwordValidate(password.value, email) ? '' : 'La contraseña debe tener entre 8 y 72 bytes UTF-8.');
        };
        password.addEventListener('input', update);
        document.querySelector('#register_email')?.addEventListener('input', update);
        update();
    }
    showPassword('.pswd', '.showPassword');
})(jQuery);
