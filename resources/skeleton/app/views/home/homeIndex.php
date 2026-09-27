<header class="gframe-header">
    <nav class="container d-flex align-items-center justify-content-between py-4" aria-label="Navegación principal">
        <a class="gframe-brand" href="<?= htmlspecialchars(site_url, ENT_QUOTES, 'UTF-8') ?>" aria-label="Inicio de <?= htmlspecialchars(site_name, ENT_QUOTES, 'UTF-8') ?>">
            <span class="gframe-mark" aria-hidden="true">G</span>
            <span><?= htmlspecialchars(site_name, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a class="gframe-doc-link" href="https://github.com/gorvet/gframe-framework" target="_blank" rel="noopener noreferrer">Documentación</a>
    </nav>
</header>

<main class="gframe-welcome">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-12 col-lg-7">
                <p class="gframe-eyebrow">GFrame está listo</p>
                <h1>La base está preparada.<br>Lo próximo lo construyes tú.</h1>
                <p class="gframe-lead">Una estructura PHP ligera, organizada y lista para convertirse en tu aplicación.</p>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <a class="btn btn-primary btn-lg" href="https://github.com/gorvet/gframe-framework" target="_blank" rel="noopener noreferrer">Conocer GFrame</a>
                    <span class="gframe-version">PHP · MVC · Bootstrap</span>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="gframe-start">
                    <span class="gframe-start-label">Tu primer cambio</span>
                    <code>app/views/home/homeIndex.php</code>
                    <p>Edita esta vista y empieza a darle identidad a tu proyecto.</p>
                </div>
            </div>
        </div>
    </div>
</main>
