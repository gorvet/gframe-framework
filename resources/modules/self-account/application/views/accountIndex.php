<?php
$account = (array)($data['data']['account'] ?? []);
$role = trim((string)($account['role'] ?? ''));
$mustChangePassword = !empty($_SESSION['must_change_password']);
?>
<div class="container py-4 py-lg-5">
    <form id="self-account-tokens" class="d-none" aria-hidden="true">
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars((string)($_SESSION['csrfToken'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrfTimestamp" value="<?= htmlspecialchars((string)($_SESSION['csrfTimestamp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    </form>
    <?php if ($mustChangePassword): ?>
        <div class="alert alert-warning" role="alert">
            Debes cambiar tu contraseña antes de continuar.
        </div>
    <?php endif; ?>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h2 mb-1">Mi cuenta</h1>
                    <p class="text-body-secondary mb-0">Administra tus datos de acceso.</p>
                </div>
                <a class="btn btn-outline-primary" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-5">
                    <section class="card h-100">
                        <div class="card-header"><strong>Información de la cuenta</strong></div>
                        <div class="card-body">
                            <span class="small text-body-secondary d-block">Correo electrónico</span>
                            <strong class="d-block mb-3"><?= htmlspecialchars((string)($account['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="small text-body-secondary d-block">Rol</span>
                            <strong><?= htmlspecialchars($role !== '' ? $role : 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    </section>
                </div>

                <div class="col-12 col-md-7">
                    <section class="card h-100">
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
                                <button class="btn btn-primary" id="account-password-button" type="submit">Cambiar contraseña</button>
                            </form>
                        </div>
                    </section>
                </div>

                <?php if ($role !== 'superadministrator'): ?>
                    <div class="col-12">
                        <section class="card border-danger">
                            <div class="card-header text-danger"><strong>Desactivar cuenta</strong></div>
                            <div class="card-body">
                                <p>Perderás el acceso inmediatamente. Tus registros podrán conservarse por integridad y auditoría.</p>
                                <button class="btn btn-outline-danger" id="account-deactivate-button" type="button">Desactivar mi cuenta</button>
                            </div>
                        </section>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
