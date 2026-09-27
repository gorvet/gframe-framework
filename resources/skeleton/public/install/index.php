<?php

declare(strict_types=1);

use GFrame\Install\InstallationProfileCatalog;
use GFrame\Install\ProjectInstaller;
use GFrame\Modules\ModuleCatalog;

session_start();

$projectRoot = dirname(__DIR__, 2);
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
$lock = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'gframe-installed.json';
$installed = is_file($lock);
$profiles = InstallationProfileCatalog::frameworkDefault()->all();
$modules = ModuleCatalog::frameworkDefault()->all();
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
        if ((string)($_POST['password'] ?? '') !== (string)($_POST['password_confirmation'] ?? '')) {
            throw new RuntimeException('Las contraseñas no coinciden.');
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
                'auto_create' => !empty($_POST['database_auto_create']),
            ];

        $result = ProjectInstaller::frameworkDefault()->install([
            'project_root' => $projectRoot,
            'profile' => (string)($_POST['profile'] ?? 'managed'),
            'app_name' => trim((string)($_POST['app_name'] ?? 'GFrame')),
            'environment' => 'production',
            'debug' => false,
            'timezone' => (string)($_POST['timezone'] ?? 'America/Havana'),
            'language' => 'es',
            'database' => $database,
            'superadministrator' => [
                'email' => (string)($_POST['email'] ?? ''),
                'password' => (string)($_POST['password'] ?? ''),
            ],
            'modules' => array_values(array_map('strval', (array)($_POST['modules'] ?? []))),
            'tenant_key' => 'tenant_id',
            'tenant_table' => 'tenants',
            'seo_enabled' => !empty($_POST['seo_enabled']),
            'seo_allow_indexing' => !empty($_POST['seo_enabled']),
            'seo_sitemap' => !empty($_POST['seo_sitemap']),
            'seo_robots' => !empty($_POST['seo_robots']),
            'seo_llms' => !empty($_POST['seo_llms']),
            'metricool_enabled' => !empty($_POST['metricool_enabled']),
            'metricool_hash' => trim((string)($_POST['metricool_hash'] ?? '')),
            'password_expiration_enabled' => !empty($_POST['password_expiration_enabled']),
            'password_expiration_days' => (int)($_POST['password_expiration_days'] ?? 90),
            'password_expiration_warning_days' => 7,
        ]);
        if (($result['status'] ?? '') !== 'success') {
            throw new RuntimeException('No se pudo completar la instalación: ' . (string)($result['code'] ?? 'error'));
        }
        unset($_SESSION['gframe_installer_csrf']);
        $installed = true;
    } catch (Throwable $exception) {
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
    <style>
        :root{color-scheme:light;--primary:#0aa6d5;--dark:#0b0a28;--border:#d9e1e8;--muted:#667085;--bg:#f4f7f9}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--dark);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
        .shell{width:min(960px,calc(100% - 32px));margin:48px auto}.brand{text-align:center;margin-bottom:24px}.brand strong{font-size:1.5rem}
        .card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 12px 36px rgba(11,10,40,.08);overflow:hidden}
        .header{padding:24px 32px;background:linear-gradient(100deg,var(--primary),#1673a6);color:#fff}.header h1{margin:0;font-size:1.6rem}.header p{margin:.35rem 0 0;opacity:.9}
        form,.result{padding:32px}.section{padding-bottom:28px;margin-bottom:28px;border-bottom:1px solid var(--border)}.section:last-of-type{border:0}
        h2{font-size:1.05rem;margin:0 0 16px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.full{grid-column:1/-1}
        label{display:block;font-weight:600;font-size:.9rem;margin-bottom:6px}.help{font-size:.82rem;color:var(--muted);font-weight:400;margin-top:4px}
        input,select{width:100%;border:1px solid #cbd5df;border-radius:8px;padding:.72rem .8rem;background:#fff;font:inherit}
        input[type=checkbox]{width:auto}.check{display:flex;gap:9px;align-items:flex-start;font-weight:500}.check input{margin-top:.3rem}
        .modules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 20px}.module{border:1px solid var(--border);border-radius:9px;padding:10px 12px}
        button{width:100%;border:0;border-radius:8px;background:var(--primary);color:#fff;padding:.85rem 1rem;font:600 1rem inherit;cursor:pointer}
        .alert{padding:12px 14px;border-radius:8px;margin:0 0 20px}.error{background:#fff1f0;color:#9d261d}.success{background:#edfdf5;color:#176b47}
        [hidden]{display:none!important}@media(max-width:700px){.shell{margin:20px auto}.grid,.modules{grid-template-columns:1fr}.header,form,.result{padding:22px}}
    </style>
</head>
<body>
<main class="shell">
    <div class="brand"><strong>GFrame</strong></div>
    <section class="card">
        <header class="header">
            <h1>Configurar la aplicación</h1>
            <p>Base de datos, módulos y primera cuenta administrativa.</p>
        </header>
        <?php if ($installed): ?>
            <div class="result">
                <div class="alert success">La aplicación quedó instalada correctamente.</div>
                <p>Por seguridad, elimina o bloquea el directorio <code>public/install</code> antes de publicar el sitio.</p>
                <a href="../../">Abrir la aplicación</a>
            </div>
        <?php else: ?>
            <form method="post" id="installer-form">
                <input type="hidden" name="csrf" value="<?= $escape($_SESSION['gframe_installer_csrf']) ?>">
                <?php if ($error !== null): ?><div class="alert error"><?= $escape($error) ?></div><?php endif; ?>

                <div class="section grid">
                    <h2 class="full">Aplicación</h2>
                    <div><label for="app_name">Nombre</label><input id="app_name" name="app_name" required value="<?= $escape($_POST['app_name'] ?? '') ?>"></div>
                    <div><label for="profile">Tipo de proyecto</label><select id="profile" name="profile"><?php foreach ($profiles as $profile): ?><option value="<?= $escape($profile['slug']) ?>" data-database="<?= $profile['database'] ? '1' : '0' ?>" data-auth="<?= $profile['auth'] ? '1' : '0' ?>" data-tenancy="<?= $profile['tenancy'] ? '1' : '0' ?>"><?= $escape($profile['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label for="timezone">Zona horaria</label><input id="timezone" name="timezone" value="America/Havana" required></div>
                </div>

                <div class="section grid" data-section="database">
                    <h2 class="full">Base de datos</h2>
                    <div><label for="database_driver">Motor</label><select id="database_driver" name="database_driver"><option value="mysql">MySQL</option><option value="sqlite">SQLite</option></select></div>
                    <div data-mysql><label for="database_host">Servidor</label><input id="database_host" name="database_host" value="localhost"></div>
                    <div data-mysql><label for="database_port">Puerto</label><input id="database_port" name="database_port" type="number" value="3306"></div>
                    <div data-mysql><label for="database_name">Base de datos</label><input id="database_name" name="database_name"></div>
                    <div data-mysql><label for="database_user">Usuario</label><input id="database_user" name="database_user"></div>
                    <div data-mysql><label for="database_password">Contraseña</label><input id="database_password" name="database_password" type="password"></div>
                    <label class="check full" data-mysql><input type="checkbox" name="database_auto_create" value="1"><span>Crear la base de datos si el usuario tiene permisos</span></label>
                    <p class="help full" data-sqlite hidden>SQLite guardará la base de datos en <code>storage/database.sqlite</code>.</p>
                </div>

                <div class="section grid" data-section="auth">
                    <h2 class="full">Superadministrador</h2>
                    <div><label for="email">Correo electrónico</label><input id="email" name="email" type="email" autocomplete="email"></div>
                    <div></div>
                    <div><label for="password">Contraseña</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password"></div>
                    <div><label for="password_confirmation">Confirmar contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password"></div>
                    <label class="check full"><input type="checkbox" id="password_expiration_enabled" name="password_expiration_enabled" value="1"><span>Exigir renovación periódica de contraseñas</span></label>
                    <div id="password-expiration-days" hidden><label for="expiration_days">Días de vigencia</label><input id="expiration_days" name="password_expiration_days" type="number" min="1" value="90"></div>
                </div>

                <div class="section grid" data-section="tenancy" hidden>
                    <h2 class="full">Tenancy</h2>
                    <p class="help full">Se instalará la estructura multitenant estándar con la tabla <code>tenants</code> y la clave <code>tenant_id</code>.</p>
                </div>

                <div class="section">
                    <h2>Módulos opcionales</h2>
                    <div class="modules">
                        <?php foreach ($modules as $module): if (!empty($module['default'])) continue; ?>
                            <?php $needsDatabase = !empty($module['schemas']) || !empty($module['requires_schema']); ?>
                            <label class="check module" data-module-database="<?= $needsDatabase ? '1' : '0' ?>"><input type="checkbox" name="modules[]" value="<?= $escape($module['name']) ?>"><span><?= $escape($module['name']) ?><small class="help"><?= $escape($module['description'] ?? '') ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section grid">
                    <h2 class="full">Publicación y medición</h2>
                    <label class="check"><input type="checkbox" name="seo_enabled" value="1" checked><span>Activar SEO</span></label>
                    <label class="check"><input type="checkbox" name="seo_sitemap" value="1" checked><span>Generar sitemap</span></label>
                    <label class="check"><input type="checkbox" name="seo_robots" value="1" checked><span>Generar robots.txt</span></label>
                    <label class="check"><input type="checkbox" name="seo_llms" value="1" checked><span>Generar llms.txt</span></label>
                    <label class="check"><input type="checkbox" name="metricool_enabled" value="1"><span>Activar Metricool</span></label>
                    <div class="full"><label for="metricool_hash">Hash de Metricool</label><input id="metricool_hash" name="metricool_hash"><p class="help">Déjalo vacío si todavía no has configurado la medición.</p></div>
                </div>

                <button type="submit">Instalar GFrame</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<script>
(() => {
    const profile = document.querySelector('#profile');
    const driver = document.querySelector('#database_driver');
    const expiration = document.querySelector('#password_expiration_enabled');
    const toggle = () => {
        const option = profile?.selectedOptions[0];
        document.querySelector('[data-section="database"]')?.toggleAttribute('hidden', option?.dataset.database !== '1');
        document.querySelector('[data-section="auth"]')?.toggleAttribute('hidden', option?.dataset.auth !== '1');
        document.querySelector('[data-section="tenancy"]')?.toggleAttribute('hidden', option?.dataset.tenancy !== '1');
        document.querySelectorAll('[data-module-database="1"] input').forEach(input => {
            input.disabled = option?.dataset.database !== '1';
            if (input.disabled) input.checked = false;
        });
        document.querySelectorAll('[data-mysql]').forEach(el => el.toggleAttribute('hidden', driver?.value !== 'mysql'));
        document.querySelectorAll('[data-sqlite]').forEach(el => el.toggleAttribute('hidden', driver?.value !== 'sqlite'));
        document.querySelector('#password-expiration-days')?.toggleAttribute('hidden', !expiration?.checked);
    };
    profile?.addEventListener('change', toggle);
    driver?.addEventListener('change', toggle);
    expiration?.addEventListener('change', toggle);
    toggle();
})();
</script>
</body>
</html>
