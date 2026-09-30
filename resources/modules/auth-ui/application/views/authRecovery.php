<div id="recovery_step" class="col-lg-9 col-xl-8 col-xxl-6">
  <div class="card login-card">
    <div class="card-header text-center p-3"><h1 class="h4 fw-bold lh-1 mb-0">Recuperar cuenta</h1></div>
    <div class="card-body p-4 mt-0">
        <div class="alert d-none" id="auth-feedback" role="alert"></div>
        <form method="post" action="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/ajax/recovery', ENT_QUOTES, 'UTF-8') ?>" id="auth-recovery-form" class="needs-validation" novalidate>
            <input type="text" name="middle_name" value="" class="d-none" tabindex="-1" autocomplete="off">
            <div class="mb-3"><label class="form-label" for="recovery_email">Correo electrónico</label><input class="form-control" type="email" name="recovery_email" id="recovery_email" autocomplete="email" required><div class="invalid-feedback validation_recovery_email"></div></div>
            <button class="btn btn-primary d-block w-100 mt-3" type="submit">Recuperar</button>
        </form>
        <div class="col-auto text-center mt-3"><a class="tologin" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login', ENT_QUOTES, 'UTF-8') ?>">Acceder</a> <span aria-hidden="true">·</span> <a class="toregister" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login/register', ENT_QUOTES, 'UTF-8') ?>">Registrarse</a></div>
    </div>
</div>
