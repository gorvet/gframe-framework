(function (window, $) {
    'use strict';
    const instances = new WeakMap();
    function mount(root) {
        if (!root?.dataset || typeof root.querySelector !== 'function') return null;
        if (instances.has(root)) return instances.get(root);
        if (!root.isConnected) return null;
        let url;
        try { url = new URL(root.dataset.presenceUrl, window.location.href); } catch (_) { return null; }
        if (!root.dataset.presenceUrl || url.origin !== window.location.origin || !window.crypto?.getRandomValues) return null;
        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        const token = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
        const status = root.querySelector('[data-content-presence-status]');
        const revision = root.querySelector('[name="content_revision"]');
        if (!status || !revision) return null;
        let destroyed = false, request = null, timer = null;
        function show(message) { status.textContent = message || ''; status.hidden = !message; }
        function schedule() {
            window.clearTimeout(timer);
            if (!destroyed && !document.hidden) timer = window.setTimeout(ping, 30000);
        }
        function ping() {
            if (destroyed || document.hidden || request || !root.isConnected) return;
            const fields = $('#tokens').serializeArray();
            fields.push({ name: 'editor_token', value: token });
            request = $.ajax({ url: url.href, method: 'POST', data: fields, dataType: 'json', timeout: 10000 });
            request.done(function (response) {
                if (destroyed) return;
                show(response.message || (response.status === 'success' ? '' : root.dataset.presenceError));
            }).fail(function () { if (!destroyed) show(root.dataset.presenceError); })
                .always(function () { request = null; schedule(); });
        }
        function visibility() {
            window.clearTimeout(timer);
            if (!document.hidden) ping();
        }
        const instance = {
            confirmSaved(value) {
                if (!destroyed && typeof value === 'string' && value.length > 0 && value.length <= 128) revision.value = value;
            },
            destroy() {
                if (destroyed) return;
                destroyed = true;
                window.clearTimeout(timer);
                document.removeEventListener('visibilitychange', visibility);
                request?.abort();
                instances.delete(root);
            }
        };
        instances.set(root, instance);
        document.addEventListener('visibilitychange', visibility);
        ping();
        return instance;
    }
    window.ContentEditing = { mount };
    $(function () { document.querySelectorAll('[data-content-editing]').forEach(mount); });
})(window, window.jQuery);
