<div id="register_step" class="col-lg-9 col-xl-8 col-xxl-6">
  <div class="login-card card">
    <div class="card-header text-center p-3">
      <h1 class="h4 fw-bold lh-1 mb-0">Crear cuenta</h1>
    </div>
    <div class="card-body p-4 mt-0">
      <form method="post" id="register" class="needs-validation " novalidate>
        <input type="hidden" id="middle_name" class="middle_name" name="middle_name">
        <div class="mb-3">
          <label class="form-label" for="register_email">Correo electrónico</label>
          <input class="form-control" type="email" name="register_email" id="register_email" autocomplete="email" autofocus required pattern="^[a-z0-9_\-.]+@[a-z0-9_\-.]+\.[a-z]{2,3}$">
          <div class="invalid-feedback validation_register_email"></div>
        </div>
        <div class="mb-3 col-sm-12">
          <label class="form-label" for="register_password">Contraseña</label>
          <div class="input-group">
            <input class="form-control pswd" type="password" name="register_password" id="register_password" autocomplete="new-password" required>
            <button class="showPassword input-group-text" type="button">Mostrar</button>
          </div>
          <div class="invalid-feedback validation_register_password"></div>
          <div class="passwordMeter"></div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="cover-register-checkbox" checked required>
          <label class="form-label" for="cover-register-checkbox">Acepto los <a href="<?= htmlspecialchars(site_url . 'terminos-uso', ENT_QUOTES, 'UTF-8') ?>">términos y condiciones</a> de uso</label>
        </div>
      </form>
      <div class="mb-3">
        <button id="submit_register" class="btn btn-primary d-block w-100 mt-3" type="button">Crear cuenta</button>
      </div>
      <div class="col-auto text-center">
        <span class="mb-0">¿Ya eres usuario?</span> 
        <a class="tologin" href="<?= htmlspecialchars(site_url . 'login', ENT_QUOTES, 'UTF-8') ?>">Acceder</a>
      </div>
    </div>
  </div>
</div>
