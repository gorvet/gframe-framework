(function (window, $) {
    'use strict';
    if (window.__gframeNotificationsBound) return;
    window.__gframeNotificationsBound = true;
    const inbox = $('[data-notifications-inbox]');
    const history = $('[data-notifications-history]');
    const detail = $('[data-notification-detail]');
    if (!inbox.length && !history.length && !detail.length) return;

    const tokens = () => $('#tokens input[name="csrfToken"], #tokens input[name="csrfTimestamp"]').serializeArray();
    const post = (action, extra) => $.post(site_url + 'ajax/notifications/' + action, tokens().concat(extra || []), null, 'json');
    const notifyError = response => {
        if (typeof alertToast === 'function') alertToast({icon: 'error', title: response?.message || 'No se pudo completar la operación.'});
    };
    let page = Math.max(1, Number(new URLSearchParams(window.location.search).get('page') || 1));
    let requestVersion = 0;
    let inboxFilter = 'all';
    let inboxVersion = 0;
    const openMenus = new Map();
    function closeMenus() { openMenus.forEach((parent, menu) => { const toggle = parent.querySelector('[data-bs-toggle="dropdown"]'); window.bootstrap?.Dropdown.getInstance(toggle)?.hide(); if (!parent.isConnected) menu.remove(); }); }
    document.addEventListener('show.bs.dropdown', event => {
        const parent = event.target.closest('.notification-actions-dropdown');
        if (!parent) return;
        const menu = parent.querySelector('.dropdown-menu');
        if (!menu) return;
        openMenus.set(menu, parent);
        menu.classList.add('notifications-floating-menu');
        document.body.appendChild(menu);
    });
    document.addEventListener('hidden.bs.dropdown', event => {
        const parent = event.target.closest('.notification-actions-dropdown');
        if (!parent) return;
        openMenus.forEach((original, menu) => { if (original === parent) { if (parent.isConnected) parent.appendChild(menu); else menu.remove(); menu.classList.remove('notifications-floating-menu'); openMenus.delete(menu); } });
    });

    function updateInbox(response) {
        if (response.status !== 'success') { notifyError(response); return; }
        const unread = Number(response.data?.unread || 0);
        closeMenus();
        inbox.html(response.html || '').attr('data-unread', unread).trigger('notifications:updated', [response.data]);
        $('[data-notifications-count]').text(unread).prop('hidden', unread < 1);
    }
    function loadInbox() {
        if (!inbox.length) return;
        const version = ++inboxVersion;
        post('inbox', [{name: 'filter', value: inboxFilter}]).done(response => { if (version === inboxVersion) updateInbox(response); }).fail(() => notifyError());
    }
    function loadHistory(push = false) {
        if (!history.length) return;
        const version = ++requestVersion;
        const filter = String(history.find('[name="filter"]').val() || 'all');
        post('history', [{name: 'page', value: page}, {name: 'filter', value: filter}]).done(response => {
            if (version !== requestVersion) return;
            if (response.status !== 'success') { notifyError(response); return; }
            closeMenus();
            history.find('[data-notifications-results]').html(response.html || '');
            page = Math.max(1, Number(response.meta?.page || 1));
            const url = new URL(window.location.href);
            page > 1 ? url.searchParams.set('page', String(page)) : url.searchParams.delete('page');
            filter === 'unread' ? url.searchParams.set('filter', 'unread') : url.searchParams.delete('filter');
            window.history[push ? 'pushState' : 'replaceState']({}, '', url);
            $('[data-notifications-count]').text(Number(response.data?.unread || 0)).prop('hidden', !Number(response.data?.unread || 0));
        }).fail(() => notifyError());
    }
    function refresh() { loadInbox(); loadHistory(); }
    let detailVersion = 0;
    $(document).on('click', '[data-notification-open]', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const id = Number(this.dataset.notificationOpen);
        if (id <= 0) return;
        const version = ++detailVersion;
        post('detail', [{name: 'notification_id', value: id}]).done(response => {
            if (version !== detailVersion) return;
            if (response.status !== 'success' || !response.html) { notifyError(response); return; }
            closeMenus();
            const bell = document.querySelector('.gframe-notifications > [data-bs-toggle="dropdown"]');
            if (bell) window.bootstrap.Dropdown.getInstance(bell)?.hide();
            const previous = document.getElementById('notification-detail-modal');
            if (previous) { window.bootstrap.Modal.getInstance(previous)?.dispose(); previous.remove(); }
            document.body.insertAdjacentHTML('beforeend', response.html);
            const modal = document.getElementById('notification-detail-modal');
            modal.addEventListener('hidden.bs.modal', () => { window.bootstrap.Modal.getInstance(modal)?.dispose(); modal.remove(); }, {once: true});
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            refresh();
        }).fail(() => notifyError());
    });

    $(document).on('click', '[data-notification-read], [data-notification-unread]', function () {
        const id = Number($(this).closest('[data-notification-id]').data('notification-id'));
        if (id <= 0) return;
        const action = $(this).is('[data-notification-read]') ? 'mark-read' : 'mark-unread';
        post(action, [{name: 'notification_id', value: id}]).done(response => {
            if (response.status === 'success') refresh(); else notifyError(response);
        }).fail(() => notifyError());
    });
    $(document).on('click', '[data-notifications-mark-all]', function () {
        post('mark-all-read').done(response => {
            if (response.status === 'success') refresh(); else notifyError(response);
        }).fail(() => notifyError());
    });
    $(document).on('click', '[data-notifications-inbox-filter]', function () {
        inboxFilter = this.dataset.notificationsInboxFilter;
        $('[data-notifications-inbox-filter]').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        loadInbox();
    });
    $(document).on('show.bs.dropdown', function (event) {
        if ($(event.target).closest('.dropdown').find('[data-notifications-inbox]').length) loadInbox();
    });
    history.on('change', '[name="filter"]', () => { page = 1; loadHistory(true); });
    history.on('click', 'button[data-notifications-filter]', function () {
        history.find('[name="filter"]').val(this.dataset.notificationsFilter);
        history.find('button[data-notifications-filter]').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        page = 1; loadHistory(true);
    });
    history.on('click', '#all_items_pagination a', function (event) {
        event.preventDefault();
        page = $(this).hasClass('prev') ? page - 1 : ($(this).hasClass('next') ? page + 1 : Number($(this).text()));
        page = Math.max(1, page); loadHistory(true);
    });
    history.on('click', '[data-notifications-page]', function () {
        page = Math.max(1, Number(this.dataset.notificationsPage || 1));
        loadHistory(true);
    });
    $(window).on('popstate.gframeNotifications', () => {
        const query = new URLSearchParams(window.location.search);
        page = Math.max(1, Number(query.get('page') || 1));
        history.find('[name="filter"]').val(query.get('filter') === 'unread' ? 'unread' : 'all');
        history.find('button[data-notifications-filter]').each(function () { const active = this.dataset.notificationsFilter === history.find('[name="filter"]').val(); $(this).toggleClass('active', active).attr('aria-pressed', String(active)); });
        loadHistory();
    });
    document.addEventListener('gf:heartbeat:notifications.inbox', event => {
        if (inbox.length) { if (inboxFilter === 'unread') loadInbox(); else { ++inboxVersion; updateInbox(event.detail?.payload || {}); } }
    });
    loadInbox();
    if (history.length) loadHistory();
    const detailID = Number(detail.attr('data-notification-detail') || 0);
    if (detailID > 0) post('mark-read', [{name: 'notification_id', value: detailID}]).done(response => {
        if (response.status === 'success') loadInbox(); else notifyError(response);
    }).fail(() => notifyError());
})(window, jQuery);
