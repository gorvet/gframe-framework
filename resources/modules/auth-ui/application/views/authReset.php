<div class="card login-card auth-card">
    <div class="card-header text-center border-0 py-3"><h1 class="h4 mb-0">Nueva contraseña</h1></div>
    <div class="card-body p-4">
        <div class="alert d-none" id="auth-feedback" role="alert"></div>
        <form method="post" action="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/ajax/reset-password', ENT_QUOTES, 'UTF-8') ?>" id="auth-reset-form" class="needs-validation" novalidate>
            <input type="text" name="middle_name" value="" class="d-none" tabindex="-1" autocomplete="off">
            <input type="hidden" name="reset_token" value="<?= htmlspecialchars((string)($_GET['token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3"><label class="form-label" for="reset_password">Nueva contraseña</label><div class="input-group"><input class="form-control pswd" type="text" name="reset_password" id="reset_password" minlength="8" maxlength="72" autocomplete="new-password" required><button class="showPassword input-group-text" type="button">Ocultar</button></div><div class="invalid-feedback validation_reset_password"></div><div class="passwordMeter" role="status" aria-live="polite"></div></div>
            <button class="btn btn-primary w-100" type="submit">Establecer contraseña</button>
        </form>
        <p class="text-center small mt-3 mb-0"><a href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login', ENT_QUOTES, 'UTF-8') ?>">Volver al acceso</a></p>
    </div>
</div>
