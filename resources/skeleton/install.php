<?php

declare(strict_types=1);

use GFrame\Install\InstallationProfileCatalog;
use GFrame\Install\ProjectInstaller;
use GFrame\Install\DatabasePreflight;
use GFrame\Modules\ModuleCatalog;
use GFrame\Modules\ModuleAssetPublisher;

session_start();
header('Cache-Control: no-store');

$projectRoot = __DIR__;
$autoloadCandidates = [
    $projectRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'autoload.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
];
$autoload = null;
foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}
if ($autoload === null) {
    http_response_code(500);
    exit('No se encontró el cargador de Composer.');
}
require $autoload;

$escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$assetVersion = static fn(string $path): string => substr((string)hash_file('sha256', __DIR__ . '/' . $path), 0, 12);
$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/install.php'), PHP_URL_PATH) ?: '/install.php');
$basePath = preg_replace('#/install\.php$#i', '', $requestPath) ?: '';
$scheme = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
$appUrl = $scheme . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($basePath, '/');
$lock = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json';
$installed = is_file($lock);
if ($installed) {
    header('Location: /' . ltrim(trim($basePath, '/') . '/', '/'), true, 303);
    exit;
}
$moduleCatalog = ModuleCatalog::frameworkDefault();
if (!$installed) {
    // Solo activos del asistente; no instala rutas, tablas ni módulos de aplicación.
    (new ModuleAssetPublisher($moduleCatalog))->publish(['sweetalert2', 'password-utils'], $projectRoot . '/public');
}
$profiles = InstallationProfileCatalog::frameworkDefault()->all();
$optionalByProfile = InstallationProfileCatalog::frameworkDefault()->optionalModules($moduleCatalog);
$optionalGroups = [];
$optionalDependencies = [];
foreach ($optionalByProfile as $slug => $groups) {
    foreach ($groups as $title => $entries) {
        foreach ($entries as $name => $module) {
            $optionalGroups[$title][$name] ??= $module + ['profiles' => []];
            $optionalGroups[$title][$name]['profiles'][] = $slug;
            $optionalDependencies[$name] ??= array_values(array_diff(array_column($moduleCatalog->resolve([$name]), 'name'), [$name]));
        }
    }
}
$error = null;
$result = null;

if (empty($_SESSION['gframe_installer_csrf'])) {
    $_SESSION['gframe_installer_csrf'] = bin2hex(random_bytes(24));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    try {
        if (!hash_equals((string)$_SESSION['gframe_installer_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('La sesión del instalador venció. Recarga la página.');
        }
        $selectedProfile = (string)($_POST['profile'] ?? 'managed');
        InstallationProfileCatalog::frameworkDefault()->get($selectedProfile);
        $allowedOptional = [];
        foreach ($optionalByProfile[$selectedProfile] as $entries) $allowedOptional = array_merge($allowedOptional, array_keys($entries));
        if (array_diff((array)($_POST['modules'] ?? []), $allowedOptional) !== []) {
            throw new RuntimeException('Hay módulos opcionales que no corresponden al tipo de proyecto. Revisa la selección.');
        }

        $driver = (string)($_POST['database_driver'] ?? 'mysql');
        $database = $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'path' => 'storage/database.sqlite']
            : [
                'driver' => 'mysql',
                'host' => (string)($_POST['database_host'] ?? 'localhost'),
                'port' => (int)($_POST['database_port'] ?? 3306),
                'database' => (string)($_POST['database_name'] ?? ''),
                'username' => (string)($_POST['database_user'] ?? ''),
                'password' => (string)($_POST['database_password'] ?? ''),
                'auto_create' => true,
            ];

        if (($_POST['installer_action'] ?? '') === 'check_database') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode((new DatabasePreflight())->check($projectRoot, $database), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!empty($profiles[(string)($_POST['profile'] ?? 'managed')]['database'])) {
            $check = (new DatabasePreflight())->check($projectRoot, $database);
            if ($check['status'] !== 'success') throw new RuntimeException($check['message']);
        }

        $result = ProjectInstaller::frameworkDefault()->install([
            'project_root' => $projectRoot,
            'profile' => (string)($_POST['profile'] ?? 'managed'),
            'app_name' => trim((string)($_POST['app_name'] ?? 'GFrame')),
            'app_url' => $appUrl,
            'environment' => 'production',
            'debug' => false,
            'language' => 'es',
            'database' => $database,
            'superadministrator' => [
                'email' => (string)($_POST['email'] ?? ''),
                'password' => (string)($_POST['password'] ?? ''),
            ],
            'modules' => array_values(array_map('strval', (array)($_POST['modules'] ?? []))),
            'tenant_key' => 'tenant_id',
            'tenant_table' => 'tenants',
            'seo_robots' => true,
            'seo_llms' => true,
        ]);
        if (($result['status'] ?? '') !== 'success') {
            throw new RuntimeException('No se pudo completar la instalación: ' . (string)($result['code'] ?? 'error'));
        }
        unset($_SESSION['gframe_installer_csrf']);
        $installed = true;
    } catch (Exception $exception) {
        if (($_POST['installer_action'] ?? '') === 'check_database') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'code' => 'installer_check_failed', 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalar GFrame</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="public/img/favicon.png">
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/common.css">
    <?php if (!$installed): ?>
    <link rel="stylesheet" href="public/vendors/external/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="public/vendors/external/sweetalert2/sweetTheme.css">
    <link rel="stylesheet" href="public/vendors/internal/passwordUtils/passwordUtils.css">
    <?php endif; ?>
    <link rel="stylesheet" href="public/css/install/install.css?v=<?= $assetVersion('public/css/install/install.css') ?>">
</head>
<body>
<main class="shell">
    <div class="brand"><img src="public/img/logo.png" class="install-logo" alt="GFrame"></div>
    <section class="card install-card">
        <?php if ($installed): ?>
            <div class="result">
                <div class="alert success">La aplicación quedó instalada correctamente.</div>
                <p>El instalador ha quedado bloqueado automáticamente.</p>
                <a href="<?= $escape(rtrim($basePath, '/') . '/') ?>">Abrir la aplicación</a>
                <?php if (!empty($result['superadministrator'])): ?><a href="<?= $escape(rtrim($basePath, '/') . '/login') ?>">Acceder</a><?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post" id="installer-form">
                <noscript><p class="alert error" role="alert">Activa JavaScript para utilizar el instalador.</p></noscript>
                <input type="hidden" name="csrf" value="<?= $escape($_SESSION['gframe_installer_csrf']) ?>">
                <?php if ($error !== null): ?><div class="alert error" role="alert"><?= $escape($error) ?></div><?php endif; ?>

                <div class="section" data-step="Aplicación">
                    <h1>Tu proyecto</h1>
                    <div class="installer-field"><label for="app_name">Nombre del proyecto</label><input id="app_name" name="app_name" required value="<?= $escape($_POST['app_name'] ?? '') ?>"><p>El nombre que aparecerá en tu aplicación.</p></div>
                    <div class="installer-field"><label for="profile">Tipo de proyecto</label><select id="profile" name="profile"><?php foreach ($profiles as $profile): ?><option value="<?= $escape($profile['slug']) ?>" data-database="<?= $profile['database'] ? '1' : '0' ?>" data-auth="<?= $profile['auth'] ? '1' : '0' ?>" data-tenancy="<?= $profile['tenancy'] ? '1' : '0' ?>" data-public="<?= $profile['public'] ? '1' : '0' ?>"><?= $escape($profile['name']) ?></option><?php endforeach; ?></select><p id="profile-description"></p></div>
                    <fieldset id="installer-account" class="installer-account">
                        <legend>Cuenta administrativa</legend>
                        <div class="installer-field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" autocomplete="email"><p>Lo usarás para iniciar sesión.</p></div>
                        <div class="installer-field"><label for="password">Contraseña</label><div><div class="installer-password"><input id="password" name="password" type="password" minlength="8" autocomplete="new-password"><button type="button" id="installer-show-password" aria-controls="password" aria-pressed="false">Mostrar</button></div><div class="passwordMeter d-none" role="status"></div></div><p>Al menos ocho caracteres.</p></div>
                    </fieldset>
                </div>

                <div class="section" data-step="Base de datos" data-section="database" hidden>
                    <h1>Conexión con la base de datos</h1>
                    <p>Introduce los datos de conexión. Si no los conoces, consulta con tu proveedor de alojamiento.</p>
                    <div class="installer-field"><label for="database_driver">Motor</label><select id="database_driver" name="database_driver"><option value="mysql">MySQL</option><option value="sqlite">SQLite</option></select><p>MySQL usa un servidor; SQLite guarda un archivo en el proyecto.</p></div>
                    <div class="installer-field" data-mysql><label for="database_name">Base de datos</label><input id="database_name" name="database_name"><p>Usa una base vacía. Si no existe, intentaremos crearla.</p></div>
                    <div class="installer-field" data-mysql><label for="database_user">Usuario</label><input id="database_user" name="database_user" autocomplete="off"><p>El usuario con permisos para instalar las tablas.</p></div>
                    <div class="installer-field" data-mysql><label for="database_password">Contraseña</label><input id="database_password" name="database_password" type="password" autocomplete="off"><p>La contraseña del usuario de la base de datos.</p></div>
                    <div class="installer-field" data-mysql><label for="database_host">Servidor</label><input id="database_host" name="database_host" value="localhost"><p>Normalmente es <code>localhost</code>.</p></div>
                    <div class="installer-field" data-mysql><label for="database_port">Puerto</label><input id="database_port" name="database_port" type="number" value="3306" min="1" max="65535"><p>Usa 3306 salvo que tu alojamiento indique otro.</p></div>
                    <p class="help full" data-sqlite hidden>SQLite guardará la base de datos en <code>storage/database.sqlite</code>.</p>
                    <p class="help full" id="database-status" role="status"></p>
                </div>

                <div class="section" data-step="Módulos" hidden>
                    <h1>Módulos opcionales</h1>
                    <p>Puedes instalar estos módulos más adelante.</p>
                    <div class="installer-module-groups">
                        <?php foreach ($optionalGroups as $title => $entries): ?>
                        <fieldset class="installer-module-group">
                            <legend><?= $escape($title) ?></legend>
                            <div class="modules">
                            <?php foreach ($entries as $module): ?>
                            <label class="check module" data-module-profiles="<?= $escape(implode(' ', $module['profiles'])) ?>"><input type="checkbox" name="modules[]" value="<?= $escape($module['name']) ?>"><span><?= $escape($module['label']) ?><small class="help"><?= $escape($module['description']) ?></small></span></label>
                            <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section" data-step="Resumen" hidden>
                    <h2>Revisar e instalar</h2>
                    <dl id="installer-summary"></dl>
                </div>
                <div class="installer-actions">
                    <button type="button" id="installer-back" hidden>Anterior</button>
                    <button type="button" id="installer-next" hidden>Continuar</button>
                    <button type="submit" id="installer-submit">Instalar GFrame</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php if (!$installed): ?>
<script type="application/json" id="installer-data"><?= json_encode([
    'descriptions' => array_map(static fn(array $profile): string => (string)$profile['description'], $profiles),
    'dependencies' => $optionalDependencies,
    'values' => array_intersect_key($_POST, array_flip(['app_name', 'profile', 'database_driver', 'database_host', 'database_port', 'database_name', 'database_user', 'email', 'modules'])),
    'posted' => $_SERVER['REQUEST_METHOD'] === 'POST',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="public/vendors/external/jquery/jquery.min.js"></script>
<script src="public/vendors/external/sweetalert2/sweetalert2.all.min.js"></script>
<script src="public/vendors/internal/passwordUtils/passwordUtils.js"></script>
<script src="public/js/install/install.js?v=<?= $assetVersion('public/js/install/install.js') ?>"></script>
<?php endif; ?>
</body>
</html>
