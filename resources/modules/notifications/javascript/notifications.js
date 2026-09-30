(function (window, $) {
    'use strict';
    if (window.__gframeNotificationsBound) return;
    window.__gframeNotificationsBound = true;
    const inbox = $('[data-notifications-inbox]');
    const history = $('[data-notifications-history]');
    if (!inbox.length && !history.length) return;

    const tokens = () => $('#tokens input[name="csrfToken"], #tokens input[name="csrfTimestamp"]').serializeArray();
    const post = (action, extra) => $.post(site_url + 'ajax/notifications/' + action, tokens().concat(extra || []), null, 'json');
    const notifyError = response => {
        if (typeof alertToast === 'function') alertToast({icon: 'error', title: response?.message || 'No se pudo completar la operación.'});
    };
    let page = Math.max(1, Number(new URLSearchParams(window.location.search).get('page') || 1));
    let requestVersion = 0;

    function updateInbox(response) {
        if (response.status !== 'success') { notifyError(response); return; }
        const unread = Number(response.data?.unread || 0);
        inbox.html(response.html || '').attr('data-unread', unread).trigger('notifications:updated', [response.data]);
        $('[data-notifications-count]').text(unread).prop('hidden', unread < 1);
    }
    function loadInbox() {
        if (!inbox.length) return;
        post('inbox').done(updateInbox).fail(() => notifyError());
    }
    function loadHistory(push = false) {
        if (!history.length) return;
        const version = ++requestVersion;
        const filter = String(history.find('[name="filter"]').val() || 'all');
        post('history', [{name: 'page', value: page}, {name: 'filter', value: filter}]).done(response => {
            if (version !== requestVersion) return;
            if (response.status !== 'success') { notifyError(response); return; }
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

    $(document).on('click', '[data-notification-read], [data-notification-delete]', function () {
        const id = Number($(this).closest('[data-notification-id]').data('notification-id'));
        if (id <= 0) return;
        const action = $(this).is('[data-notification-read]') ? 'mark-read' : 'delete';
        post(action, [{name: 'notification_id', value: id}]).done(response => {
            if (response.status === 'success') refresh(); else notifyError(response);
        }).fail(() => notifyError());
    });
    $(document).on('click', '[data-notifications-mark-all]', function () {
        post('mark-all-read').done(response => {
            if (response.status === 'success') refresh(); else notifyError(response);
        }).fail(() => notifyError());
    });
    history.on('change', '[name="filter"]', () => { page = 1; loadHistory(true); });
    history.on('click', '[data-notifications-page]', function () {
        page = Math.max(1, Number(this.dataset.notificationsPage || 1));
        loadHistory(true);
    });
    $(window).on('popstate.gframeNotifications', () => {
        const query = new URLSearchParams(window.location.search);
        page = Math.max(1, Number(query.get('page') || 1));
        history.find('[name="filter"]').val(query.get('filter') === 'unread' ? 'unread' : 'all');
        loadHistory();
    });
    document.addEventListener('gf:heartbeat:notifications.inbox', event => {
        if (inbox.length) updateInbox(event.detail?.payload || {});
    });
    loadInbox();
    if (history.length) loadHistory();
})(window, jQuery);
