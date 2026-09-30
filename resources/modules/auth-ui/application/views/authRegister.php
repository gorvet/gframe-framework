<div class="card login-card auth-card">
    <div class="card-header text-center border-0 py-3"><h1 class="h4 mb-0">Crear cuenta</h1></div>
    <div class="card-body p-4">
        <div class="alert d-none" id="auth-feedback" role="alert"></div>
        <form method="post" action="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/ajax/register', ENT_QUOTES, 'UTF-8') ?>" id="auth-register-form" class="needs-validation" novalidate>
            <input type="text" name="middle_name" value="" class="d-none" tabindex="-1" autocomplete="off">
            <div class="mb-3"><label class="form-label" for="register_email">Correo electrónico</label><input class="form-control" type="email" name="register_email" id="register_email" autocomplete="email" required><div class="invalid-feedback validation_register_email"></div></div>
            <div class="mb-3"><label class="form-label" for="register_password">Contraseña</label><div class="input-group"><input class="form-control pswd" type="text" name="register_password" id="register_password" minlength="8" maxlength="72" autocomplete="new-password" required><button class="showPassword input-group-text" type="button">Ocultar</button></div><div class="invalid-feedback validation_register_password"></div><div class="passwordMeter" role="status" aria-live="polite"></div></div>
            <button class="btn btn-primary w-100" type="submit">Crear cuenta</button>
        </form>
        <p class="text-center small mt-3 mb-0"><a href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login', ENT_QUOTES, 'UTF-8') ?>">Ya tengo una cuenta</a></p>
    </div>
</div>


<template id="auth-existing-message"><p>Si olvidaste tu contraseña, <a href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/login/recovery', ENT_QUOTES, 'UTF-8') ?>">recupera tu cuenta</a>.</p></template>
