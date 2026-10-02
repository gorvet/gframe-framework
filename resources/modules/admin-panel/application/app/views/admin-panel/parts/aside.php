<div class="d-flex align-items-center justify-content-between d-lg-none px-3 py-2">
    <a class="logo text-decoration-none" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)site_name, ENT_QUOTES, 'UTF-8') ?></a>
    <button class="btn js-mobile-toggle" type="button" aria-label="Cerrar menú"><i class="gicon-close" aria-hidden="true"></i></button>
</div>
<div class="d-none d-lg-flex justify-content-end px-3 py-2">
    <button class="btn toggle-sidebar-btn" type="button" aria-label="Contraer menú" aria-controls="sidebar" aria-expanded="true"><i class="gicon-arrowrw" aria-hidden="true"></i></button>
</div>
<ul class="sidebar-nav" id="sidebar-nav">
    <?php include \GFrame\Modules\ModuleRuntime::file('views', 'admin-panel/parts/menu.php', 'admin-panel'); ?>
    <?php
    $menuSections = ['user' => '', 'admin' => ''];
    foreach (glob(ABSPATH . 'app/views/admin-panel/parts/menu-items/*.php') ?: [] as $menuPart) {
        $menuSection = 'admin';
        ob_start();
        include $menuPart;
        $menuHtml = trim((string)ob_get_clean());
        $menuSections[$menuSection === 'user' ? 'user' : 'admin'] .= $menuHtml;
    }
    echo $menuSections['user'];
    if ($menuSections['admin'] !== ''):
    ?>
    <li class="nav-heading">Administración</li>
    <?= $menuSections['admin'] ?>
    <?php endif; ?>
    <?php $accountUrl = rtrim((string)site_url, '/') . '/account'; ?>
    <li class="nav-heading">Mi cuenta</li>
    <li class="nav-item"><a class="nav-link<?= rtrim((string)($routeParams['currentURL'] ?? ''), '/') === $accountUrl ? ' current' : '' ?>" href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="gicon-user" aria-hidden="true"></i><span>Cuenta y seguridad</span></a></li>
</ul>
