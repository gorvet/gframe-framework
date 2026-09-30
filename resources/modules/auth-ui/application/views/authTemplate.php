<main class="auth-page">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 text-center mb-3">
                <a class="auth-brand" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>" aria-label="Inicio de <?= htmlspecialchars((string)site_name, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="auth-mark" aria-hidden="true">G</span>
                    <span><?= htmlspecialchars((string)site_name, ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </div>
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                <?= $content ?>
            </div>
        </div>
    </div>
    <div id="toastBox" class="toastBox" aria-live="polite"></div>
</main>
