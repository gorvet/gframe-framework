<div id="reset_step" class="col-lg-9 col-xl-8 col-xxl-6">
  <div class="login-card card">
    <div class="card-header text-center p-3">
      <h1 class="h4 fw-bold lh-1 mb-0">Nueva contraseña</h1>
    </div>
    <div class="card-body p-4 mt-0">
      <form method="post" id="reset" class="needs-validation " novalidate>
        <input type="hidden" class="middle_name" name="middle_name" value="">
        <input type="hidden" id="rpuser_token" name="rpuser_token" value="">
        <div class="mb-3 col-sm-12">
          <label class="form-label" for="reset_password">Contraseña</label>
          <div class="input-group">
            <input class="form-control pswd" type="password" name="reset_password" id="reset_password" autocomplete="new-password" required>
            <button class="showPassword input-group-text" type="button">Mostrar</button>
          </div>
          <div class="passwordMeter"></div>
        </div>
      </form>
      <div class="mb-3">
        <button id="submit_reset" class="btn btn-primary d-block w-100 mt-3" type="button">Establecer</button>
      </div>
      <div class="col-auto text-center">
        <a class="tologin" href="<?= htmlspecialchars(site_url . 'login', ENT_QUOTES, 'UTF-8') ?>">Acceder</a> <span aria-hidden="true">·</span> <a class="toregister" href="<?= htmlspecialchars(site_url . 'login/register', ENT_QUOTES, 'UTF-8') ?>">Registrarse</a>
      </div>
    </div>
  </div>
</div>
