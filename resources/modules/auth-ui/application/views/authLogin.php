<div class="card login-card auth-card">
    <div class="card-header text-center border-0 py-3">
        <h1 class="h4 mb-0">Iniciar sesión</h1>
    </div>
    <div class="card-body p-4">
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
            <button class="btn btn-primary w-100" id="auth-submit" type="submit">Acceder</button>
        </form>
        <div class="d-flex justify-content-between gap-3 mt-3 small">
            <a href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login/recovery', ENT_QUOTES, 'UTF-8') ?>">Olvidé mi contraseña</a>
            <a href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login/register', ENT_QUOTES, 'UTF-8') ?>">Crear cuenta</a>
        </div>
    </div>
</div>


<template id="auth-unverified-message"><p>Revisa tu correo para verificar la cuenta. Si no encuentras el mensaje, <button class="btn btn-link p-0" type="button" data-auth-resend>solicita otro enlace</button>.</p></template>
