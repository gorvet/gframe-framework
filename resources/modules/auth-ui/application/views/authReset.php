<div id="reset_step" class="col-lg-9 col-xl-8 col-xxl-6">
  <div class="card login-card">
    <div class="card-header text-center p-3"><h1 class="h4 fw-bold lh-1 mb-0">Nueva contraseña</h1></div>
    <div class="card-body p-4 mt-0">
        <div class="alert d-none" id="auth-feedback" role="alert"></div>
        <form method="post" action="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/ajax/reset-password', ENT_QUOTES, 'UTF-8') ?>" id="auth-reset-form" class="needs-validation" novalidate>
            <input type="text" name="middle_name" value="" class="d-none" tabindex="-1" autocomplete="off">
            <input type="hidden" name="reset_token" value="<?= htmlspecialchars((string)($_GET['rp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3"><label class="form-label" for="reset_password">Nueva contraseña</label><div class="input-group"><input class="form-control pswd" type="text" name="reset_password" id="reset_password" minlength="8" maxlength="72" autocomplete="new-password" required><button class="showPassword input-group-text" type="button">Ocultar</button></div><div class="invalid-feedback validation_reset_password"></div><div class="passwordMeter" role="status" aria-live="polite"></div></div>
            <button class="btn btn-primary d-block w-100 mt-3" type="submit">Establecer</button>
        </form>
        <div class="col-auto text-center mt-3"><a class="tologin" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login', ENT_QUOTES, 'UTF-8') ?>">Acceder</a> <span aria-hidden="true">·</span> <a class="toregister" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login/register', ENT_QUOTES, 'UTF-8') ?>">Registrarse</a></div>
    </div>
</div>
