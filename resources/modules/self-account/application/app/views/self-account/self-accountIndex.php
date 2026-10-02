<?php
$account = (array)($data['data']['account'] ?? []);
$role = trim((string)($account['role'] ?? ''));
$mustChangePassword = !empty($_SESSION['must_change_password']);
$policy = $data['data']['deactivation_policy'] ?? null;
$deactivationMessage = $policy ? 'Perderás el acceso inmediatamente. La cuenta se eliminará después de ' . (int)$policy['retention_days'] . ' días, con un aviso al menos ' . (int)$policy['warning_hours'] . ' horas antes.' : 'Perderás el acceso inmediatamente. Tus registros podrán conservarse por integridad y auditoría.';
?>
<div class="col-12 self-account-page">
    <form id="self-account-tokens" class="d-none" aria-hidden="true">
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars((string)($_SESSION['csrfToken'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrfTimestamp" value="<?= htmlspecialchars((string)($_SESSION['csrfTimestamp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    </form>
    <?php if ($mustChangePassword): ?>
        <div class="alert alert-warning" role="alert">
            Debes cambiar tu contraseña antes de continuar.
        </div>
    <?php endif; ?>
            <div class="pagetitle"><h1>Cuenta y seguridad</h1></div>

            <div class="row g-4 mt-1">
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><strong>Información de la cuenta</strong></div>
                        <div class="card-body">
                            <span class="small text-body-secondary d-block">Correo electrónico</span>
                            <strong class="d-block mb-3"><?= htmlspecialchars((string)($account['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="small text-body-secondary d-block">Rol</span>
                            <strong><?= htmlspecialchars($role !== '' ? $role : 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><strong>Cambiar contraseña</strong></div>
                        <div class="card-body">
                            <form id="account-password-form" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label class="form-label" for="current_password">Contraseña actual</label>
                                    <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="new_password">Nueva contraseña</label>
                                    <input class="form-control" type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="new_password_confirmation">Confirmar nueva contraseña</label>
                                    <input class="form-control" type="password" id="new_password_confirmation" name="new_password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
                                </div>
                                <div class="d-flex justify-content-end"><button class="btn btn-primary" id="account-password-button" type="submit">Cambiar contraseña</button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($role !== 'superadministrator'): ?>
                    <div class="col-12">
                        <div class="card border-danger">
                            <div class="card-header text-danger"><strong>Desactivar cuenta</strong></div>
                            <div class="card-body">
                                <p><?= htmlspecialchars($deactivationMessage, ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="d-flex justify-content-end"><button class="btn btn-danger" id="account-deactivate-button" type="button" data-confirm-message="<?= htmlspecialchars($deactivationMessage, ENT_QUOTES, 'UTF-8') ?>">Desactivar mi cuenta</button></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
</div>
