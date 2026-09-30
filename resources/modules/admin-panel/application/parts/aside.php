<div class="d-flex align-items-center justify-content-between d-lg-none px-3 py-2">
    <a class="logo text-decoration-none" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)site_name, ENT_QUOTES, 'UTF-8') ?></a>
    <button class="btn js-mobile-toggle" type="button" aria-label="Cerrar menú"><i class="gicon-close" aria-hidden="true"></i></button>
</div>
<div class="d-none d-lg-flex justify-content-end px-3 py-2">
    <button class="btn toggle-sidebar-btn" type="button" aria-label="Contraer menú" aria-controls="sidebar" aria-expanded="true"><i class="gicon-arrowrw" aria-hidden="true"></i></button>
</div>
<ul class="sidebar-nav" id="sidebar-nav">
    <?php include ABSPATH . 'app/views/admin/parts/menu.php'; ?>
    <?php foreach (glob(ABSPATH . 'app/views/admin/parts/menu-items/*.php') ?: [] as $menuPart): include $menuPart; endforeach; ?>
</ul>
