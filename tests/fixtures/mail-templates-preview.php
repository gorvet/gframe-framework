<?php
// Auditoría local de las plantillas originales, sin SMTP ni datos reales.
if (PHP_SAPI !== 'cli-server') exit;
if (str_starts_with((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/resources/')) return false;
require dirname(__DIR__, 2) . '/packages/autoload.php';
$root = dirname(__DIR__, 2);
$values = [
    'title' => 'Mensaje de prueba', 'h1' => 'Verifica tu cuenta',
    'greeting' => 'Hola, Ana.', 'p1' => 'Confirma tu correo para activar la cuenta.',
    'p2' => 'Si no solicitaste esta operación, ignora el mensaje.',
    'aHref' => 'https://example.test/verify', 'aText' => 'Verificar mi cuenta',
    'name' => 'Ana', 'subject' => 'Consulta de prueba',
    'message' => "Primera línea del mensaje.\nSegunda línea del mensaje.",
    'action_url' => 'https://example.test/notification', 'action_display' => 'block',
    'mailSiteName' => 'Proyecto de prueba', 'mailLogo' => '/resources/skeleton/public/img/logo.png',
];
$templates = [
    'mailTemplate' => $root . '/resources/skeleton/app/views/templates/mail',
    'contactTemplate' => $root . '/resources/skeleton/app/views/templates/mail',
    'notification' => $root . '/resources/modules/notifications-email/application/mail',
];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auditoría de correos</title></head><body>
<?php foreach ($templates as $id => $directory): ?>
<h2><?= $id ?></h2>
<iframe data-template="<?= $id ?>" title="<?= $id ?>" style="width:100%;height:640px;border:1px solid #ccc" srcdoc="<?= htmlspecialchars((new GFrame\Mail\MailTemplateRegistry($directory))->render($id, $values), ENT_QUOTES, 'UTF-8') ?>"></iframe>
<?php endforeach; ?>
</body></html>
