<main class="error-page">
    <div class="container py-4 py-lg-5">
        <div class="row align-items-center justify-content-center">
            <div class="col-12 col-md-9 col-lg-7 col-xl-6">
                <a class="error-brand" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir al inicio de <?= htmlspecialchars((string)site_name, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="error-mark" aria-hidden="true">G</span>
                    <span><?= htmlspecialchars((string)site_name, ENT_QUOTES, 'UTF-8') ?></span>
                </a>
                <?= $content ?>
            </div>
        </div>
    </div>
</main>
