<?php
$moduleData = (array)($data['data'] ?? []);
$response = (array)($moduleData['users'] ?? []);
$meta = (array)($response['meta'] ?? []);
$roles = (array)($moduleData['roles'] ?? []);
$canManage = !empty($moduleData['can_manage']);
?>
<div class="col-12">
    <form id="user-admin-tokens" class="d-none" aria-hidden="true">
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars((string)($_SESSION['csrfToken'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrfTimestamp" value="<?= htmlspecialchars((string)($_SESSION['csrfTimestamp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    </form>
    <div class="pagetitle">
        <h1>Usuarios</h1>
    </div>
    <div class="card">
        <div class="card-body">
            <form id="userFilters" class="row g-3 align-items-end" method="get">
                <div class="col-12 col-lg-7">
                    <label for="userSearch" class="form-label">Buscar</label>
                    <input type="search" id="userSearch" name="search" class="form-control" maxlength="120" value="<?= htmlspecialchars((string)($meta['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Correo electrónico">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="userRoleFilter" class="form-label">Rol</label>
                    <select id="userRoleFilter" name="role" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars((string)$role['slug'], ENT_QUOTES, 'UTF-8') ?>"<?= (string)($meta['role'] ?? '') === (string)$role['slug'] ? ' selected' : '' ?>><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="userStatusFilter" class="form-label">Estado</label>
                    <select id="userStatusFilter" name="status" class="form-select">
                        <?php foreach (['' => 'Todos', 'verify' => 'Verificado', 'unverify' => 'Sin verificar', 'disabled' => 'Desactivado', 'suspended' => 'Suspendido'] as $value => $label): ?>
                            <option value="<?= $value ?>"<?= (string)($meta['status'] ?? '') === $value ? ' selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-1">
                    <button type="button" id="clearUserFilters" class="btn btn-outline-secondary" aria-label="Limpiar filtros" title="Limpiar filtros"<?= empty($meta['search']) && empty($meta['role']) && empty($meta['status']) ? ' hidden' : '' ?>><i class="gicon-close" aria-hidden="true"></i></button>
                </div>
            </form>
        </div>
    </div>
    <div id="userListMount"><?php
        $partial = \GFrame\Modules\ModuleRuntime::file('views', 'user-admin/_userList.php', 'user-admin');
        if ($partial === null) throw new \RuntimeException('No se encontró el parcial del listado de usuarios.');
        include $partial;
    ?></div>
    <?php if ($canManage): ?>
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="userModalTitle">Administrar usuario</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p id="managedUserEmail" class="text-break fw-semibold mb-4"></p>
                <form id="userRoleForm" class="needs-validation" novalidate>
                    <label for="managedUserRole" class="form-label">Rol</label>
                    <div class="input-group mb-4">
                        <select id="managedUserRole" name="role_id" class="form-select" required>
                            <?php foreach ($roles as $role): ?><option value="<?= (int)$role['role_id'] ?>"><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary text-nowrap">Guardar rol</button>
                    </div>
                </form>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 border-top pt-3">
                    <div><span class="small text-body-secondary d-block">Estado de la cuenta</span><span id="managedUserStatus" class="fw-semibold"></span></div>
                    <div class="d-flex flex-wrap gap-2 ms-auto">
                    <button type="button" class="btn btn-success js-user-operation" data-operation="verify" hidden>Verificar cuenta</button>
                    <button type="button" class="btn btn-warning js-user-operation" data-operation="suspend" data-confirm-title="¿Suspender esta cuenta?" data-confirm-text="Se bloqueará su acceso y se cerrarán sus sesiones." hidden>Suspender</button>
                    <button type="button" class="btn btn-success js-user-operation" data-operation="restore" hidden>Restablecer acceso</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-end gap-2">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
                <button type="button" class="btn btn-link text-danger text-decoration-none px-0 js-user-operation" data-operation="delete" data-confirm-title="¿Eliminar esta cuenta definitivamente?" data-confirm-text="Se borrarán la cuenta, sus membresías y sus sesiones. Esta acción no se puede deshacer." data-confirm-button="Eliminar cuenta">Eliminar cuenta</button>
            </div>
        </div></div>
    </div>
    <?php endif; ?>
</div>
