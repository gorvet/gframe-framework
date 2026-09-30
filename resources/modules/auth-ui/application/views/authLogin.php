<div class="col-lg-9 col-xl-8 col-xxl-6">
  <div class="login-card card">
    <div class="card-header text-center p-3">
        <h1 class="h4 fw-bold lh-1 mb-0">Iniciar sesión</h1>
    </div>
    <div class="card-body p-4 mt-0">
        <div class="alert alert-danger d-none" id="auth-error" role="alert"></div>
        <form method="post" action="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/ajax/login', ENT_QUOTES, 'UTF-8') ?>" id="auth-login-form" class="needs-validation" novalidate>
            <input type="text" name="middle_name" value="" class="d-none" tabindex="-1" autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="login_email">Correo electrónico</label>
                <input class="form-control" type="email" name="login_email" id="login_email" autocomplete="email" autofocus required><div class="invalid-feedback validation_login_email"></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="login_password">Contraseña</label>
                <div class="input-group">
                    <input class="form-control pswd" type="password" name="login_password" id="login_password" autocomplete="current-password" required><div class="invalid-feedback validation_login_password"></div>
                    <button class="showPassword input-group-text" type="button">Mostrar</button>
                </div>
            </div>
            <div class="mb-3"><a href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login/lostpassword', ENT_QUOTES, 'UTF-8') ?>">Olvidé mi contraseña</a></div>
            <button class="btn btn-primary d-block w-100 mt-3" id="auth-submit" type="submit">Acceder</button>
        </form>
        <div class="col-auto text-center mt-3">
            <span class="mb-0">¿No tienes cuenta?</span>
            <a class="toregister" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login/register', ENT_QUOTES, 'UTF-8') ?>">Crear una cuenta</a>
        </div>
    </div>
</div>


<template id="auth-unverified-message"><p>Revisa tu correo para verificar la cuenta. Si no encuentras el mensaje, <button class="btn btn-link p-0" type="button" data-auth-resend>solicita otro enlace</button>.</p></template>
