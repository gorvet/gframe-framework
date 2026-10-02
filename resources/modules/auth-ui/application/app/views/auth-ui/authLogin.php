<div class="col-lg-9 col-xl-8 col-xxl-6">
  <div class="login-card card">
    <div class="card-header text-center p-3">
      <h1 class="h4 fw-bold lh-1 mb-0">Iniciar sesión</h1>
    </div>
    <div class="card-body p-4 mt-0">
      <form method="post" id="login" class="needs-validation " novalidate>
        <input type="hidden" name="middle_name" value="">
        <div class="mb-3">
          <label class="form-label" for="login_email">Correo electrónico</label>
          <input class="form-control" type="email" name="login_email" id="login_email" autocomplete="email" autofocus required pattern="^[a-z0-9_\-.]+@[a-z0-9_\-.]+\.[a-z]{2,3}$">
          <div class="invalid-feedback validation_login_email"></div>
        </div>
        <div class="mb-3 col-sm-12">
          <label class="form-label" for="login_password">Contraseña</label>
          <div class="input-group">
            <input class="form-control pswd" type="password" name="login_password" id="login_password" autocomplete="current-password" required>
            <button class="showPassword input-group-text" type="button">Mostrar</button>
            <div class="invalid-feedback validation_login_password"></div>
          </div>
        </div>
        <div class="mb-3 col-sm-12">
          <a href="<?= htmlspecialchars(site_url . 'login/lostpassword', ENT_QUOTES, 'UTF-8') ?>">Olvidé mi contraseña</a>
        </div>
      </form>
      <div class="mb-3">
        <button id="submit_login" class="btn btn-primary d-block w-100 mt-3" type="button">Acceder</button>
      </div>
      <div class="col-auto text-center">
        <span class="mb-0">¿No tienes cuenta?</span> 
        <a class="toregister" href="<?= htmlspecialchars(site_url . 'login/register', ENT_QUOTES, 'UTF-8') ?>">Crear una cuenta</a>
      </div>
    </div>
  </div>
</div>
