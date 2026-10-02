<main class="auth-page">
  <div class="container-fluid py-3 py-lg-4">
    <div class="row align-items-center justify-content-center">
      <div class="col-12 d-flex justify-content-center">
        <a class="auth-brand" href="<?= htmlspecialchars(site_url, ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir al inicio de <?= htmlspecialchars(site_name, ENT_QUOTES, 'UTF-8') ?>">
          <img src="<?= htmlspecialchars(site_url . 'public/img/logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GFrame">
        </a>
      </div>
      <div class="col-sm-10 col-md-6 align-self-center mx-auto">
        <div class="row justify-content-center g-0">
          <?= $content ?>
        </div>
      </div>
      <div class="col-12 text-center mt-3">
        <a class="auth-home-link" href="<?= htmlspecialchars(site_url, ENT_QUOTES, 'UTF-8') ?>"><span aria-hidden="true">←</span> Volver al inicio</a>
      </div>
    </div>
  </div>
</main>
