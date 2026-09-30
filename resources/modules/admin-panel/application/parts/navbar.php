<?php
$identity = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
$displayName = trim((string)($identity['name'] ?? ''));
if ($displayName === '') $displayName = explode('@', trim((string)($identity['email'] ?? '')), 2)[0];
?>
<header id="header" class="header fixed-top d-flex align-items-center">
    <a class="logo d-none d-lg-flex align-items-center text-decoration-none" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)site_name, ENT_QUOTES, 'UTF-8') ?></a>
    <nav class="navbar ms-auto" aria-label="Acciones de la cuenta">
        <ul class="navbar-nav flex-row align-items-center">
            <?php foreach (glob(ABSPATH . 'app/views/admin/parts/header-actions/*.php') ?: [] as $headerAction): include $headerAction; endforeach; ?>
            <li class="nav-item"><button id="darkmode" class="nav-link btn" type="button" aria-label="Cambiar tema" aria-pressed="false"><i class="gicon-darkmode" aria-hidden="true"></i></button></li>
            <li class="nav-item dropdown">
                <button class="nav-link dropdown-toggle btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="gicon-user" aria-hidden="true"></i> <span class="d-none d-sm-inline"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/account', ENT_QUOTES, 'UTF-8') ?>">Mi cuenta</a></li>
                    <li><button class="dropdown-item" type="button" data-gf-logout>Cerrar sesión</button></li>
                </ul>
            </li>
        </ul>
    </nav>
    <button class="btn js-mobile-toggle d-lg-none ms-3" type="button" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><i class="gicon-menu" aria-hidden="true"></i></button>
</header>
