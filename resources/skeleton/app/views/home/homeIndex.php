<?php
$base = rtrim((string)site_url, '/') . '/';
$identity = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
$hasAuth = \GFrame\Modules\ModuleRuntime::file('views', 'auth-ui/authLogin.php', 'auth-ui') !== null
    || is_file(ABSPATH . 'app/views/auth/authLogin.php');
$displayName = trim((string)($identity['name'] ?? ''));
if ($displayName === '') $displayName = explode('@', (string)($identity['email'] ?? ''), 2)[0];
?>
<header id="homeHeader" class="header">
    <div class="container d-flex justify-content-between align-items-center h-100">
        <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>" aria-label="Inicio de GFrame">
            <img src="<?= htmlspecialchars($base . 'public/img/navlogo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GFrame" class="home-logo">
        </a>
        <?php if ($hasAuth): ?>
        <nav id="navbar" class="navbar" aria-label="Navegación principal">
            <ul class="d-flex align-items-center gap-3 mb-0 list-unstyled">
                <?php if ($identity !== []): ?>
                    <li>Hola, <?= htmlspecialchars(ucfirst($displayName), ENT_QUOTES, 'UTF-8') ?></li>
                    <li><a href="<?= htmlspecialchars($base . 'admin', ENT_QUOTES, 'UTF-8') ?>">Dashboard</a></li>
                <?php else: ?>
                    <li><a href="<?= htmlspecialchars($base . 'login', ENT_QUOTES, 'UTF-8') ?>">Entrar</a></li>
                    <li><a class="home-register" href="<?= htmlspecialchars($base . 'login/register', ENT_QUOTES, 'UTF-8') ?>">Crear cuenta</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</header>

<main class="site-wrapper" aria-labelledby="homeTitle">
    <div class="site-wrapper-inner">
        <div class="container">
            <div class="inner cover">
                <h1 id="homeTitle" class="cover-heading">G-<span>Frame</span></h1>
                <p class="lead">Algo maravilloso se construye aquí.</p>
            </div>
        </div>
    </div>
</main>
