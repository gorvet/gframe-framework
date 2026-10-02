<?php
$users = (array)($response['data'] ?? []);
$meta = (array)($response['meta'] ?? []);
$statusLabels = ['verify' => 'Verificado', 'unverify' => 'Sin verificar', 'disabled' => 'Desactivado', 'suspended' => 'Suspendido'];
$statusClasses = ['verify' => 'success', 'unverify' => 'warning', 'disabled' => 'secondary', 'suspended' => 'danger'];
$currentUserID = (int)($_SESSION['auth']['id'] ?? 0);
?>
<div class="card mt-3">
    <div class="card-body">
        <?php if ($users === []): ?>
            <div class="p-4 text-muted">No se encontraron usuarios.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Correo</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userID = (int)($user['user_id'] ?? 0);
                        $protected = (string)($user['role'] ?? '') === 'superadministrator';
                        $isSelf = $userID === $currentUserID;
                        $status = (string)($user['status'] ?? '');
                        ?>
                        <tr data-user-id="<?= $userID ?>">
                            <td><div class="fw-semibold"><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></td>
                            <td style="min-width:155px">
                                <?php if ($protected): ?>
                                    <span>Superadministrador</span>
                                <?php else: ?>
                                    <span><?= htmlspecialchars((string)($user['role_name'] ?? $user['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-<?= $statusClasses[$status] ?? 'secondary' ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="text-end">
                                <?php if ($isSelf): ?>
                                    <span class="small text-muted">Tu cuenta</span>
                                <?php elseif ($protected): ?>
                                    <span class="small text-muted">Protegido</span>
                                <?php elseif ($canManage): ?>
                                    <button class="btn btn-sm btn-outline-secondary btn-list-actions js-user-actions" type="button" data-user-id="<?= $userID ?>" data-email="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-role-id="<?= (int)($user['role_id'] ?? 0) ?>" data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">Administrar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ((int)($meta['total_pages'] ?? 1) > 1): ?>
    <?php PaginationHelper::render((int)$meta['total_pages'], (int)($meta['page'] ?? 1)); ?>
<?php endif; ?>
