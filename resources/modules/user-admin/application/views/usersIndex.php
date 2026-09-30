<?php
$moduleData = (array)($data['data'] ?? []);
$response = (array)($moduleData['users'] ?? []);
$users = (array)($response['data'] ?? []);
$meta = (array)($response['meta'] ?? []);
$roles = (array)($moduleData['roles'] ?? []);
$canManage = !empty($moduleData['can_manage']);
?>
<div class="col-12 py-4 py-lg-5">
    <form id="user-admin-tokens" class="d-none" aria-hidden="true">
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars((string)($_SESSION['csrfToken'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrfTimestamp" value="<?= htmlspecialchars((string)($_SESSION['csrfTimestamp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    </form>
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div><h1 class="h2 mb-1">Usuarios</h1><p class="text-body-secondary mb-0">Administra el acceso y los roles.</p></div>
        <a class="btn btn-outline-primary" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
    <form class="row g-2 mb-4" method="get">
        <div class="col-12 col-md"><label class="visually-hidden" for="user-search">Buscar</label><input class="form-control" id="user-search" name="search" value="<?= htmlspecialchars((string)($meta['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por correo electrónico"></div>
        <div class="col-12 col-md-3"><label class="visually-hidden" for="user-role-filter">Rol</label><select class="form-select" id="user-role-filter" name="role"><option value="">Todos los roles</option><?php foreach ($roles as $role): ?><option value="<?= htmlspecialchars((string)$role['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= (string)($meta['role'] ?? '') === (string)$role['slug'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-3"><label class="visually-hidden" for="user-status-filter">Estado</label><select class="form-select" id="user-status-filter" name="status"><?php foreach (['' => 'Todos los estados', 'verify' => 'Activo', 'unverify' => 'Sin verificar', 'disabled' => 'Desactivado', 'suspended' => 'Suspendido'] as $value => $label): ?><option value="<?= $value ?>" <?= (string)($meta['status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-auto"><button class="btn btn-primary w-100" type="submit">Filtrar</button></div>
    </form>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Correo</th><th>Estado</th><th>Rol</th><th class="text-end">Acciones</th></tr></thead><tbody>
    <?php if ($users === []): ?><tr><td class="text-center text-body-secondary py-5" colspan="4">No se encontraron usuarios.</td></tr><?php endif; ?>
    <?php foreach ($users as $user): ?>
        <?php $protected = (string)($user['role'] ?? '') === 'superadministrator'; ?>
        <tr data-user-id="<?= (int)($user['user_id'] ?? 0) ?>">
            <td><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($user['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><select class="form-select form-select-sm user-role" <?= $protected || !$canManage ? 'disabled' : '' ?>><?php foreach ($roles as $role): ?><option value="<?= (int)$role['role_id'] ?>" <?= (int)($user['role_id'] ?? 0) === (int)$role['role_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$role['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></td>
            <td class="text-end"><?php if (!$protected && $canManage): ?><button class="btn btn-sm btn-outline-primary user-status" data-active="<?= (string)($user['status'] ?? '') === 'verify' ? '0' : '1' ?>" type="button"><?= (string)($user['status'] ?? '') === 'verify' ? 'Desactivar' : 'Activar' ?></button><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
    <?php PaginationHelper::render((int)($meta['total_pages'] ?? 1), (int)($meta['page'] ?? 1)); ?>
</div>
