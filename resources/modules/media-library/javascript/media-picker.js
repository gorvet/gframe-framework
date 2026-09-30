(function (window, $) {
    'use strict';

    const picker = $('#gframe-media-picker');
    if (!picker.length || typeof bootstrap === 'undefined') return;
    const modal = bootstrap.Modal.getOrCreateInstance(picker[0]);
    const grid = picker.find('[data-media-picker-grid]');
    const empty = picker.find('[data-media-picker-empty]');
    let options = null;
    let selected = new Set();
    let items = new Map();
    let page = 1;
    let totalPages = 1;
    let timer = null;
    let resolveOpen = null;
    let requestVersion = 0;

    function tokens() {
        return $('#tokens input[name="csrfToken"], #tokens input[name="csrfTimestamp"]').serializeArray();
    }

    function notify(message) {
        if (typeof alertToast === 'function') alertToast({icon: 'error', title: message});
    }

    function paint() {
        grid.find('[data-media-picker-id]').each(function () {
            const active = selected.has(Number(this.dataset.mediaPickerId));
            $(this).toggleClass('border-primary', active).attr('aria-pressed', active ? 'true' : 'false');
        });
        picker.find('[data-media-picker-page]').text(page + ' / ' + totalPages);
        picker.find('[data-media-picker-prev]').prop('disabled', page <= 1);
        picker.find('[data-media-picker-next]').prop('disabled', page >= totalPages);
    }

    function load() {
        if (!options) return;
        const version = ++requestVersion;
        const data = picker.find('[data-media-picker-filter]').serializeArray().concat(tokens());
        data.push({name: 'page', value: page}, {name: 'fragment', value: 'picker'}, {name: 'source', value: options.saveSource});
        if (options.kind !== 'all') data.push({name: 'kind', value: options.kind});
        $.post(site_url + 'ajax/admin/media/list', data, null, 'json').done(response => {
            if (version !== requestVersion) return;
            if (response.status !== 'success') {
                notify(response.message || 'No se pudo cargar la biblioteca multimedia.');
                return;
            }
            (response.data || []).forEach(item => items.set(Number(item.media_id), item));
            grid.html(response.html || '');
            empty.toggleClass('d-none', (response.data || []).length > 0);
            totalPages = Math.max(1, Number(response.meta?.total_pages || 1));
            page = Math.min(page, totalPages);
            paint();
        }).fail(() => notify('No se pudo conectar con el servidor.'));
    }

    function open(input) {
        if (resolveOpen) resolveOpen(null);
        options = {
            multiple: Boolean(input?.multiple),
            max: Math.max(1, Math.min(50, Number(input?.max || 50))),
            kind: String(input?.kind || 'all'),
            saveSource: String(input?.saveSource || 'library'),
        };
        selected = new Set((input?.selected || []).map(Number).filter(id => id > 0).slice(0, options.multiple ? options.max : 1));
        requestVersion++;
        items = new Map();
        page = 1;
        picker.find('[data-media-picker-filter]')[0].reset();
        picker.find('[data-media-picker-filter] [name="kind"]').val(options.kind);
        picker.find('[data-media-picker-filter] [name="kind"]').prop('disabled', options.kind !== 'all');
        modal.show();
        load();
        return new Promise(resolve => { resolveOpen = resolve; });
    }

    picker.on('click', '[data-media-picker-id]', function () {
        const id = Number(this.dataset.mediaPickerId);
        if (!id) return;
        if (options.multiple) {
            if (selected.has(id)) selected.delete(id);
            else if (selected.size < options.max) selected.add(id);
            else notify('Se alcanzó el máximo de archivos permitidos.');
        } else selected = new Set([id]);
        paint();
    });
    picker.on('input change', '[data-media-picker-filter] :input:not([type="file"])', function () {
        clearTimeout(timer);
        page = 1;
        timer = setTimeout(load, 300);
    });
    picker.on('click', '[data-media-picker-prev]', () => { if (page > 1) { page--; load(); } });
    picker.on('click', '[data-media-picker-next]', () => { if (page < totalPages) { page++; load(); } });
    picker.on('click', '[data-media-picker-confirm]', function () {
        const ids = [...selected];
        const result = {ids, items: ids.map(id => items.get(id)).filter(Boolean)};
        const resolve = resolveOpen;
        resolveOpen = null;
        modal.hide();
        if (resolve) resolve(result);
    });
    picker.on('hidden.bs.modal', function () {
        if (resolveOpen) { resolveOpen(null); resolveOpen = null; }
    });
    picker.on('change', '[data-media-picker-upload]', function () {
        const file = this.files?.[0];
        if (!file || !options) return;
        const payload = new FormData();
        tokens().forEach(item => payload.append(item.name, item.value));
        payload.append('file', file);
        payload.append('source', options.saveSource);
        $(this).prop('disabled', true);
        $.ajax({url: site_url + 'ajax/admin/media/upload', method: 'POST', data: payload, processData: false, contentType: false, dataType: 'json'})
            .done(response => {
                if (response.status !== 'success') { notify(response.message || 'No se pudo subir el archivo.'); return; }
                page = 1;
                load();
            })
            .fail(() => notify('No se pudo conectar con el servidor.'))
            .always(() => { $(this).val('').prop('disabled', false); });
    });

    picker.on('submit', '[data-media-picker-hotlink]', function (event) {
        event.preventDefault();
        if (!options) return;
        if (!this.checkValidity()) { this.reportValidity(); return; }
        const form = $(this);
        const button = form.find('[type="submit"]').prop('disabled', true);
        $.post(site_url + 'ajax/admin/media/hotlink', form.serializeArray().concat(tokens(), [{name: 'source', value: options.saveSource}]), null, 'json')
            .done(response => {
                if (response.status !== 'success') { notify(response.message || 'No se pudo añadir el enlace.'); return; }
                this.reset(); page = 1; load();
            })
            .fail(() => notify('No se pudo conectar con el servidor.'))
            .always(() => button.prop('disabled', false));
    });

    window.MediaPicker = {open};
})(window, jQuery);
