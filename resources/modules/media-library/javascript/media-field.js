(function (window, $) {
    'use strict';

    function normalizeIds(value) {
        let values = value;
        if (typeof values === 'string') {
            try { values = JSON.parse(values); }
            catch (_) { values = values.split(','); }
        }
        if (!Array.isArray(values)) values = [values];
        return [...new Set(values.map(Number).filter(id => Number.isSafeInteger(id) && id > 0))];
    }

    function tokens() {
        return $('#tokens input[name="csrfToken"], #tokens input[name="csrfTimestamp"]').serializeArray();
    }

    function bind(field) {
        const $field = $(field);
        if ($field.data('ml-bound')) return;
        $field.data('ml-bound', true);
        const input = $field.find('[data-ml-value]').first();
        const preview = $field.find('[data-ml-preview]').first();
        const multiple = field.hasAttribute('data-ml-multiple');
        const max = Math.max(1, Math.min(50, Number(field.dataset.mlMax || 50)));
        let previewVersion = 0;

        function current() { return normalizeIds(input.val()); }
        function set(ids, notifyChange = true) {
            const next = normalizeIds(ids).slice(0, multiple ? max : 1);
            const version = ++previewVersion;
            input.val(multiple ? JSON.stringify(next) : String(next[0] || ''));
            if (notifyChange) input.trigger('change');
            preview.empty();
            if (!next.length) return;
            const data = tokens();
            data.push({name: 'media_ids', value: JSON.stringify(next)});
            data.push({name: 'variant', value: field.dataset.mlFragment || 'sthumb'});
            data.push({name: 'allow_remove_one', value: field.dataset.mlRemoveOne || '1'});
            $.post(site_url + 'ajax/admin/media/field', data, null, 'json').done(response => {
                if (version !== previewVersion) return;
                if (response.status === 'success') preview.html(response.html || '');
                else if (typeof alertToast === 'function') alertToast({icon: 'error', title: response.message || 'No se pudo cargar la vista previa.'});
            }).fail(() => {
                if (typeof alertToast === 'function') alertToast({icon: 'error', title: 'No se pudo cargar la vista previa.'});
            });
        }

        $field.on('click', '[data-ml-media-pick]', function () {
            if (!window.MediaPicker?.open) {
                if (typeof alertToast === 'function') alertToast({icon: 'error', title: 'El selector multimedia no está disponible.'});
                return;
            }
            window.MediaPicker.open({
                selected: current(), multiple, max,
                kind: field.dataset.mlKind || 'all',
                saveSource: field.dataset.mlSaveSource || 'library',
            }).then(result => {
                if (!result) return;
                const picked = normalizeIds(result.ids);
                const append = multiple && field.dataset.mlBehavior !== 'replace';
                set(append ? current().concat(picked) : picked);
            });
        });
        $field.on('click', '[data-ml-media-clear]', () => set([]));
        $field.on('click', '[data-ml-media-remove]', function () {
            const id = Number($(this).closest('[data-ml-id]').data('ml-id'));
            set(current().filter(value => value !== id));
        });
        set(current(), false);
    }

    window.MediaField = {
        normalizeIds,
        bindAll(root) {
            const $root = $(root || document);
            $root.find('[data-ml-media-field]').addBack('[data-ml-media-field]').each(function () { bind(this); });
        },
    };
    $(function () { window.MediaField.bindAll(); });
})(window, jQuery);
