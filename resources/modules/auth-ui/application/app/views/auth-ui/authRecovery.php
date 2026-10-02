<div id="recovery_step" class="col-lg-9 col-xl-8 col-xxl-6">
  <div class="login-card card">
    <div class="card-header text-center p-3">
      <h1 class="h4 fw-bold lh-1 mb-0">Recuperar cuenta</h1>
    </div>
    <div class="card-body p-4 mt-0">
      <form method="post" id="recovery" class="needs-validation " novalidate>
        <input type="hidden" name="middle_name" value="">
        <div class="mb-3">
          <label class="form-label" for="recovery_email">Correo electrónico</label>
          <input class="form-control" type="email" name="recovery_email" id="recovery_email" autocomplete="email" autofocus required pattern="^[a-z0-9_\-.]+@[a-z0-9_\-.]+\.[a-z]{2,3}$">
          <div class="invalid-feedback validation_recovery_email"></div>
        </div>
      </form>
      <div class="mb-3">
        <button id="submit_recovery" class="btn btn-primary d-block w-100 mt-3" type="button">Recuperar</button>
      </div>
      <div class="col-auto text-center">
        <a class="tologin" href="<?= htmlspecialchars(site_url . 'login', ENT_QUOTES, 'UTF-8') ?>">Acceder</a> <span aria-hidden="true">·</span> <a class="toregister" href="<?= htmlspecialchars(site_url . 'login/register', ENT_QUOTES, 'UTF-8') ?>">Registrarse</a>
      </div>
    </div>
  </div>
</div>
