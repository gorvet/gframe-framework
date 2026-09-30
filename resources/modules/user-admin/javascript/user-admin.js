(function ($) {
    'use strict';

    function update(row, payload) {
        const data = $('#user-admin-tokens').serializeArray();
        data.push({name: 'user_id', value: row.data('user-id')});
        Object.keys(payload).forEach(key => data.push({name: key, value: payload[key]}));
        return $.post(site_url + 'ajax/admin/users/update', data);
    }

    $('.user-role').on('focus', function () {
        $(this).data('previous-value', this.value);
    }).on('change', function () {
        const select = $(this);
        update(select.closest('tr'), {operation: 'role', role_id: this.value}).done(function (response) {
            alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || 'No se pudo actualizar el rol.'});
            if (response.status !== 'success') select.val(select.data('previous-value'));
            else select.data('previous-value', select.val());
        }).fail(function () {
            select.val(select.data('previous-value'));
            alertToast({icon: 'error', title: 'No se pudo completar la solicitud.'});
        });
    });

    $('.user-status').on('click', function () {
        update($(this).closest('tr'), {operation: 'status', active: $(this).data('active')}).done(function (response) {
            alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || 'No se pudo actualizar el usuario.'});
            if (response.status === 'success') window.location.reload();
        }).fail(function () {
            alertToast({icon: 'error', title: 'No se pudo completar la solicitud.'});
        });
    });

    $('#all_items_pagination').on('click', 'a.page-link', function (event) {
        event.preventDefault();
        const url = new URL(window.location.href);
        const current = parseInt(url.searchParams.get('page') || '1', 10);
        let page = current;
        if ($(this).hasClass('prev')) page = Math.max(1, current - 1);
        else if ($(this).hasClass('next')) page = current + 1;
        else page = parseInt($(this).text(), 10) || current;
        url.searchParams.set('page', String(page));
        window.location.assign(url.toString());
    });
})(jQuery);
