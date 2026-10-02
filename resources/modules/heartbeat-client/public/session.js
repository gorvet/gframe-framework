(function ($) {
    'use strict';

    function scopeID() {
        const base = String(window.site_url || window.location.origin + '/');
        return base.replace(/^https?:\/\//i, '').replace(/\/+$/g, '').toLowerCase()
            .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'default';
    }

    const scope = scopeID();
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('auth_' + scope) : null;
    const closedKey = 'session_closed_' + scope;
    const expiredKey = 'session_expired_' + scope;
    let handling = false;

    function redirectTarget(reason) {
        const base = String(window.site_url || '/');
        if (reason === 'logout') return base + 'login';
        const current = String(window.location.href || '').replace(base, '').replace(/^\/+/, '');
        return base + 'login?rd=' + encodeURIComponent(current);
    }

    function stop(reason) {
        window.__gfAuthInactive = true;
        window.is_protected = false;
        window.GFHeartbeat?.stop(reason);
    }

    function showClosed(reason) {
        if (!window.is_protected || handling) return;
        handling = true;
        stop(reason);
        const title = reason === 'expired'
            ? 'Tu sesión se cerró por inactividad.'
            : 'Tu sesión se cerró.';

        if (typeof window.swalAlert === 'function') {
            window.swalAlert({icon: 'info', title: title, confirmButtonText: 'Acceder', allowOutsideClick: false})
                .then(() => window.location.assign(redirectTarget(reason)));
            return;
        }

        window.location.assign(redirectTarget(reason));
    }

    function broadcast(reason, showNotice = true) {
        channel?.postMessage({type: reason});
        try { localStorage.setItem(reason === 'expired' ? expiredKey : closedKey, String(Date.now())); } catch (_) {}
        if (showNotice) {
            showClosed(reason);
        } else {
            handling = true;
            stop(reason);
            window.location.assign(redirectTarget(reason));
        }
    }

    function csrfData() {
        const explicit = $('#tokens, #media-tokens, #self-account-tokens, #user-admin-tokens').first();
        if (explicit.length) return explicit.serialize();
        return $('input[name="csrfToken"], input[name="csrfTimestamp"]').serialize();
    }

    $(document).on('click', '[data-gf-logout]', function (event) {
        event.preventDefault();
        const proceed = typeof window.swalAlert === 'function'
            ? window.swalAlert({icon: 'warning', title: '¿Deseas cerrar la sesión?', showCancelButton: true, confirmButtonText: 'Cerrar sesión', cancelButtonText: 'Cancelar'})
                .then(result => !!result.isConfirmed)
            : Promise.resolve(false);

        proceed.then(confirmed => {
            if (!confirmed) return;
            $.post(window.site_url + 'ajax/logout', csrfData(), function (response) {
                if (response?.status === 'success') {
                    broadcast('logout', false);
                } else {
                    window.alertToast?.({ icon: 'error', title: response?.message || 'No se pudo cerrar la sesión.' });
                }
            }, 'json').fail(() => window.alertToast?.({ icon: 'error', title: 'No se pudo cerrar la sesión.' }));
        });
    });

    channel && (channel.onmessage = event => {
        const reason = event?.data?.type;
        if (reason === 'logout' || reason === 'expired') showClosed(reason);
    });

    window.addEventListener('storage', event => {
        if (event.key === closedKey) showClosed('logout');
        if (event.key === expiredKey) showClosed('expired');
    });

    document.addEventListener('gf:heartbeat:expired', () => broadcast('expired'));
    window.GFHeartbeat?.triggerNow();
})(jQuery);
