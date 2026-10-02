(function ($) {
    'use strict';
    const form = $('#userFilters');
    const mount = $('#userListMount');
    let currentPage = parseInt(new URL(window.location.href).searchParams.get('page') || '1', 10);
    let pending;
    let timer;
    let revision = 0;

    function clearVisibility() {
        $('#clearUserFilters').prop('hidden', !form.find('[name]').toArray().some(field => String(field.value).trim() !== ''));
    }

    function loadUsers(page, syncUrl) {
        clearTimeout(timer);
        const requestRevision = ++revision;
        if (pending) pending.abort();
        const data = $('#user-admin-tokens').serializeArray().concat(form.serializeArray());
        data.push({name: 'page', value: page});
        clearVisibility();
        mount.attr('aria-busy', 'true');
        pending = $.ajax({url: site_url + 'ajax/admin/users/list', method: 'POST', dataType: 'json', data: data})
            .done(function (response) {
                if (requestRevision !== revision) return;
                if (response.status !== 'success') {
                    alertToast({icon: 'error', title: response.message || 'No se pudo cargar la lista.'});
                    return;
                }
                mount.html(response.html);
                currentPage = parseInt((response.meta || {}).page || page, 10);
                if (syncUrl) {
                    const params = new URLSearchParams(form.serialize());
                    [...params.keys()].forEach(key => { if (!params.get(key)) params.delete(key); });
                    if (currentPage > 1) params.set('page', String(currentPage));
                    history.replaceState({}, '', window.location.pathname + (params.toString() ? '?' + params : ''));
                }
            }).fail(function (xhr, status) {
                if (status !== 'abort') alertToast({icon: 'error', title: 'No se pudo cargar la lista.'});
            }).always(function () {
                if (requestRevision === revision) {
                    pending = null;
                    mount.attr('aria-busy', 'false');
                }
            });
    }

    form.on('submit', function (event) { event.preventDefault(); loadUsers(1, true); });
    $('#userSearch').on('input', function () {
        clearVisibility();
        clearTimeout(timer);
        timer = setTimeout(function () { loadUsers(1, true); }, 300);
    });
    form.on('change', 'select', function () { loadUsers(1, true); });
    $('#clearUserFilters').on('click', function () {
        form.find('[name="search"], [name="role"], [name="status"]').val('');
        loadUsers(1, true);
    });
    clearVisibility();

    function update(row, payload) {
        const data = $('#user-admin-tokens').serializeArray();
        data.push({name: 'user_id', value: row.data('user-id')});
        Object.keys(payload).forEach(key => data.push({name: key, value: payload[key]}));
        return $.post(site_url + 'ajax/admin/users/update', data);
    }

    const modalElement = document.getElementById('userModal');
    const userModal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    let managedRow;
    let saving = false;
    mount.on('click', '.js-user-actions', function () {
        if (saving) return;
        const button = $(this);
        managedRow = button.closest('tr');
        $('#managedUserEmail').text(button.data('email'));
        $('#managedUserStatus').text(managedRow.find('.badge').text());
        $('#managedUserRole').val(String(button.data('role-id')));
        $('#userRoleForm').removeClass('was-validated');
        const status = button.data('status');
        $('#userModal [data-operation="verify"]').prop('hidden', status !== 'unverify');
        $('#userModal [data-operation="suspend"]').prop('hidden', status !== 'verify');
        $('#userModal [data-operation="restore"]').prop('hidden', status !== 'suspended');
        userModal.show();
    });

    function saveAction(payload) {
        if (saving || !managedRow) return;
        saving = true;
        $('#userModal button, #managedUserRole').prop('disabled', true);
        update(managedRow, payload).done(function (response) {
            alertToast({icon: response.status === 'success' ? 'success' : 'error', title: response.message || 'No se pudo completar la acción.'});
            if (response.status === 'success') {
                userModal.hide();
                loadUsers(currentPage, true);
            }
        }).fail(function () {
            alertToast({icon: 'error', title: 'No se pudo completar la solicitud.'});
        }).always(function () {
            saving = false;
            $('#userModal button, #managedUserRole').prop('disabled', false);
        });
    }
    $('#userRoleForm').on('submit', function (event) {
        event.preventDefault();
        $(this).addClass('was-validated');
        if (!this.checkValidity()) return;
        saveAction({operation: 'role', role_id: $('#managedUserRole').val()});
    });
    $('#userModal').on('click', '.js-user-operation', function () {
        const button = $(this);
        const operation = button.data('operation');
        if (operation === 'delete' || operation === 'suspend') {
            swalAlert({
                icon: 'warning', title: button.data('confirm-title'),
                text: button.data('confirm-text'), showCancelButton: true, reverseButtons: true,
                confirmButtonText: button.data('confirm-button') || button.text(),
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.isConfirmed) saveAction({operation: operation, confirmed: '1'});
            });
        } else saveAction({operation: operation});
    });

    mount.on('click', '#all_items_pagination a.page-link', function (event) {
        event.preventDefault();
        let page = currentPage;
        if ($(this).hasClass('prev')) page = Math.max(1, currentPage - 1);
        else if ($(this).hasClass('next')) page = currentPage + 1;
        else page = parseInt($(this).text(), 10) || currentPage;
        loadUsers(page, true);
    });
})(jQuery);
