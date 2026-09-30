<div class="card login-card auth-card">
    <div class="card-header text-center border-0 py-3"><h1 class="h4 mb-0">Recuperar cuenta</h1></div>
    <div class="card-body p-4">
        <div class="alert d-none" id="auth-feedback" role="alert"></div>
        <form method="post" action="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/ajax/recovery', ENT_QUOTES, 'UTF-8') ?>" id="auth-recovery-form" class="needs-validation" novalidate>
            <input type="text" name="middle_name" value="" class="d-none" tabindex="-1" autocomplete="off">
            <div class="mb-3"><label class="form-label" for="recovery_email">Correo electrónico</label><input class="form-control" type="email" name="recovery_email" id="recovery_email" autocomplete="email" required><div class="invalid-feedback validation_recovery_email"></div></div>
            <button class="btn btn-primary w-100" type="submit">Recuperar</button>
        </form>
        <p class="text-center small mt-3 mb-0"><a href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login', ENT_QUOTES, 'UTF-8') ?>">Volver al acceso</a></p>
    </div>
</div>
