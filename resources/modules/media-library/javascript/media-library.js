(function (window, $) {
    'use strict';

    function tokens() {
        return $('#tokens input[name="csrfToken"], #tokens input[name="csrfTimestamp"]').serializeArray();
    }

    function notify(response, fallback) {
        if (typeof alertToast !== 'function') return;
        alertToast({icon: response?.status === 'success' ? 'success' : 'error', title: response?.message || fallback});
    }

    class MediaLibrary {
        constructor(options = {}) {
            this.mount = $(options.mount || '[data-ml-mount]').first();
            this.filters = this.mount.find('[data-ml-filters]');
            this.results = this.mount.find('[data-ml-results]');
            this.saveSource = options.saveSource || this.mount.attr('data-ml-save-source') || 'library';
            this.kind = options.kind || this.mount.attr('data-ml-kind') || 'all';
            this.uploadMaxMB = Number(options.uploadMaxMB || this.mount.attr('data-ml-max-mb') || 0);
            this.syncUrl = options.syncUrl !== false && this.mount.attr('data-ml-sync-url') !== '0';
            this.page = 1;
            this.detailOrder = [];
            if (this.mount.length) this.bind();
        }

        setKind(kind) { this.kind = String(kind || 'all'); this.page = 1; return this.load(); }
        setSaveSource(source) { this.saveSource = String(source || 'library'); return this; }
        getSaveSource() { return this.saveSource; }
        setUploadMaxMB(mb) { this.uploadMaxMB = Math.max(0, Number(mb) || 0); return this; }

        stateFromUrl() {
            if (!this.syncUrl) return;
            const query = new URLSearchParams(window.location.search);
            this.page = Math.max(1, Number(query.get('page') || 1));
            this.filters.find('[name="search"]').val(query.get('search') || '');
            this.filters.find('[name="kind"]').val(query.get('kind') || 'all');
            this.filters.find('[name="ym"]').val(query.get('ym') || '');
        }

        updateUrl(push) {
            if (!this.syncUrl) return;
            const url = new URL(window.location.href);
            const search = String(this.filters.find('[name="search"]').val() || '').trim();
            const kind = String(this.filters.find('[name="kind"]').val() || 'all');
            const ym = String(this.filters.find('[name="ym"]').val() || '');
            search ? url.searchParams.set('search', search) : url.searchParams.delete('search');
            kind !== 'all' ? url.searchParams.set('kind', kind) : url.searchParams.delete('kind');
            ym ? url.searchParams.set('ym', ym) : url.searchParams.delete('ym');
            this.page > 1 ? url.searchParams.set('page', String(this.page)) : url.searchParams.delete('page');
            window.history[push ? 'pushState' : 'replaceState']({}, '', url);
        }

        load(page = this.page, push = false) {
            this.page = Math.max(1, Number(page) || 1);
            const data = this.filters.serializeArray().concat(tokens());
            data.push({name: 'page', value: this.page}, {name: 'fragment', value: 'library'}, {name: 'source', value: this.saveSource});
            if (this.kind !== 'all') data.push({name: 'kind', value: this.kind});
            return $.post(site_url + 'ajax/admin/media/list', data, null, 'json').done(response => {
                if (response.status !== 'success') { notify(response, 'No se pudo cargar la biblioteca.'); return; }
                this.results.html(response.html || '');
                this.detailOrder = this.results.find('[data-media-id]').map(function () { return Number(this.dataset.mediaId); }).get();
                this.page = Math.max(1, Number(response.meta?.page || 1));
                this.updateUrl(push);
            }).fail(() => notify(null, 'No se pudo conectar con el servidor.'));
        }

        refresh(page = this.page) { return this.load(page, false); }
        reload() { return this.refresh(); }
        resetFilters() {
            this.filters[0]?.reset();
            this.page = 1;
            return this.load(1, true);
        }

        upload(files) {
            const accepted = [...(files || [])];
            if (!accepted.length) return;
            const pending = accepted.map(file => {
                if (this.uploadMaxMB > 0 && file.size > this.uploadMaxMB * 1048576) {
                    notify(null, 'El archivo supera el tamaño permitido.');
                    return $.Deferred().reject().promise();
                }
                const data = new FormData();
                tokens().forEach(item => data.append(item.name, item.value));
                data.append('file', file);
                data.append('source', this.saveSource);
                return $.ajax({url: site_url + 'ajax/admin/media/upload', method: 'POST', data, processData: false, contentType: false, dataType: 'json'})
                    .done(response => notify(response, 'No se pudo añadir el archivo.'))
                    .fail(() => notify(null, 'No se pudo conectar con el servidor.'));
            });
            let remaining = pending.length;
            pending.forEach(request => request.always(() => {
                remaining--;
                if (remaining === 0) { this.refresh(1); this.loadQuota(); }
            }));
        }

        attachUpload() {
            this.mount.on('change', '#media-upload, [data-ml-file]', event => {
                this.upload(event.currentTarget.files);
                $(event.currentTarget).val('');
            });
            this.mount.on('dragover', '[data-ml-dropzone]', event => event.preventDefault());
            this.mount.on('drop', '[data-ml-dropzone]', event => {
                event.preventDefault();
                this.upload(event.originalEvent?.dataTransfer?.files);
            });
        }

        loadQuota() {
            $.post(site_url + 'ajax/admin/media/quota', tokens(), null, 'json').done(response => {
                if (response.status !== 'success') return;
                const used = Number(response.data?.used_bytes || 0) / 1048576;
                const limit = Number(response.data?.limit_bytes || 0) / 1048576;
                $('#media-quota').text(limit > 0 ? used.toFixed(1) + ' MB de ' + limit.toFixed(1) + ' MB usados' : used.toFixed(1) + ' MB usados');
            });
        }

        bind() {
            this.stateFromUrl();
            this.filters.on('submit', event => { event.preventDefault(); this.load(1, true); });
            this.filters.on('change', 'select, input[type="month"]', () => this.load(1, true));
            this.results.on('click', '#all_items_pagination a.page-link', event => {
                event.preventDefault();
                const link = $(event.currentTarget);
                const next = link.hasClass('prev') ? this.page - 1 : (link.hasClass('next') ? this.page + 1 : Number(link.text()));
                this.load(Math.max(1, next), true);
            });
            $(window).on('popstate.mediaLibrary', () => { this.stateFromUrl(); this.load(this.page); });
            this.attachUpload();
            this.detailOrder = this.results.find('[data-media-id]').map(function () { return Number(this.dataset.mediaId); }).get();
            this.mount.on('submit', '[data-ml-hotlink]', event => {
                event.preventDefault();
                const form = $(event.currentTarget);
                if (!event.currentTarget.checkValidity()) { event.currentTarget.reportValidity(); return; }
                const button = form.find('[type="submit"]').prop('disabled', true);
                $.post(site_url + 'ajax/admin/media/hotlink', form.serializeArray().concat(tokens(), [{name: 'source', value: this.saveSource}]), null, 'json')
                    .done(response => { notify(response, 'No se pudo añadir el enlace.'); if (response.status === 'success') { event.currentTarget.reset(); this.refresh(1); } })
                    .fail(() => notify(null, 'No se pudo conectar con el servidor.'))
                    .always(() => button.prop('disabled', false));
            });
            this.loadQuota();
            this.mount.on('click', '.media-delete', event => {
                const id = Number($(event.currentTarget).closest('[data-media-id]').data('media-id'));
                swalAlert({icon: 'warning', title: '¿Eliminar este archivo?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'}).then(result => {
                    if (!result.isConfirmed) return;
                    $.post(site_url + 'ajax/admin/media/delete', tokens().concat([{name: 'media_id', value: id}]), null, 'json')
                        .done(response => { notify(response, 'No se pudo eliminar el archivo.'); if (response.status === 'success') { this.refresh(); this.loadQuota(); } })
                        .fail(() => notify(null, 'No se pudo conectar con el servidor.'));
                });
            });
            const detailsModal = $('#media-details-modal');
            const openDetails = id => {
                $.post(site_url + 'ajax/admin/media/details', tokens().concat([{name: 'media_id', value: id}]), null, 'json')
                    .done(response => {
                        if (response.status !== 'success') { notify(response, 'No se pudieron cargar los detalles.'); return; }
                        const form = $('#media-details-form');
                        form.find('[name="media_id"]').val(response.data.media_id);
                        form.find('[name="original_name"]').val(response.data.original_name || '');
                        form.find('[name="alt_text"]').val(response.data.alt_text || '');
                        const path = String(response.data.path || '');
                        const url = response.data.remote_url || new URL('public/' + path.replace(/^\/+/, ''), site_url).href;
                        detailsModal.find('#media-details-url').val(url);
                        const image = detailsModal.find('[data-ml-details-preview] img');
                        image.toggleClass('d-none', response.data.kind !== 'images').attr('src', response.data.kind === 'images' ? url : '').attr('alt', response.data.alt_text || '');
                        detailsModal.find('[data-ml-details-extension]').toggleClass('d-none', response.data.kind === 'images').text(String(response.data.name || '').split('.').pop().toUpperCase());
                        detailsModal.find('[data-ml-details-date]').text(response.data.created_at || '');
                        detailsModal.find('[data-ml-details-type]').text(response.data.mime_type || '');
                        detailsModal.find('[data-ml-details-size]').text(response.data.remote_url ? 'Enlace externo' : ((Number(response.data.size_bytes || 0) / 1024).toFixed(1) + ' KB'));
                        const index = this.detailOrder.indexOf(Number(response.data.media_id));
                        detailsModal.find('[data-ml-details-prev]').prop('disabled', index <= 0);
                        detailsModal.find('[data-ml-details-next]').prop('disabled', index < 0 || index >= this.detailOrder.length - 1);
                        bootstrap.Modal.getOrCreateInstance(detailsModal[0]).show();
                    }).fail(() => notify(null, 'No se pudo conectar con el servidor.'));
            };
            this.mount.on('click', '.media-edit', event => {
                openDetails(Number($(event.currentTarget).closest('[data-media-id]').data('media-id')));
            });
            detailsModal.on('click', '[data-ml-details-prev], [data-ml-details-next]', event => {
                const current = Number(detailsModal.find('[name="media_id"]').val());
                const index = this.detailOrder.indexOf(current) + ($(event.currentTarget).is('[data-ml-details-next]') ? 1 : -1);
                if (index >= 0 && index < this.detailOrder.length) openDetails(this.detailOrder[index]);
            });
            detailsModal.on('click', '[data-ml-copy-url]', () => {
                const url = String(detailsModal.find('#media-details-url').val() || '');
                if (url && navigator.clipboard?.writeText) navigator.clipboard.writeText(url).then(() => notify({status: 'success', message: 'URL copiada.'}, 'URL copiada.'));
            });
            detailsModal.on('click', '[data-ml-details-delete]', () => {
                const id = Number(detailsModal.find('[name="media_id"]').val());
                swalAlert({icon: 'warning', title: '¿Eliminar este archivo?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'}).then(result => {
                    if (!result.isConfirmed) return;
                    $.post(site_url + 'ajax/admin/media/delete', tokens().concat([{name: 'media_id', value: id}]), null, 'json').done(response => {
                        notify(response, 'No se pudo eliminar el archivo.');
                        if (response.status === 'success') { bootstrap.Modal.getOrCreateInstance(detailsModal[0]).hide(); this.refresh(); this.loadQuota(); }
                    }).fail(() => notify(null, 'No se pudo conectar con el servidor.'));
                });
            });
            $('#media-details-form').on('submit', event => {
                event.preventDefault();
                if (!event.currentTarget.checkValidity()) {
                    event.currentTarget.classList.add('was-validated');
                    return;
                }
                $.post(site_url + 'ajax/admin/media/save', $(event.currentTarget).serializeArray().concat(tokens()), null, 'json')
                    .done(response => {
                        notify(response, 'No se pudieron guardar los cambios.');
                        if (response.status === 'success') {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('media-details-modal')).hide();
                            this.refresh();
                        }
                    }).fail(() => notify(null, 'No se pudo conectar con el servidor.'));
            });
            this.mount.on('click', '#media-sync', event => {
                const button = $(event.currentTarget).prop('disabled', true);
                $.post(site_url + 'ajax/admin/media/sync', tokens(), null, 'json')
                    .done(response => { notify(response, 'No se pudo sincronizar la biblioteca.'); if (response.status === 'success') { this.refresh(); this.loadQuota(); } })
                    .fail(() => notify(null, 'No se pudo conectar con el servidor.'))
                    .always(() => button.prop('disabled', false));
            });
        }
    }

    window.MediaLibrary = MediaLibrary;
    $(function () { $('[data-ml-mount]:not([data-ml-noauto])').each(function () { new MediaLibrary({mount: this}); }); });
})(window, jQuery);
