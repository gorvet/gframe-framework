<?php
// Datos ficticios y módulos originales en una aplicación temporal, sin SMTP ni tareas enviadas.
require $argv[1] . '/packages/autoload.php';
$root = $argv[2]; $url = $argv[3];
$result = GFrame\Install\ProjectInstaller::frameworkDefault()->install([
    'project_root' => $root, 'profile' => 'managed', 'app_name' => 'Estudio Nexo', 'app_url' => $url,
    'database' => ['driver' => 'sqlite', 'path' => $root . '/storage/demo.sqlite'],
    'modules' => ['notifications', 'notifications-email', 'notification-campaigns'],
    'superadministrator' => ['email' => 'equipo@estudionexo.example', 'password' => 'Local-Demo-Only-793!'],
]);
if ($result['status'] !== 'success') throw new RuntimeException('No se pudo preparar la aplicación temporal.');
mkdir($root . '/packages');
file_put_contents($root . '/packages/autoload.php', '<?php return require ' . var_export($argv[1] . '/packages/autoload.php', true) . ';');
$pdo = new PDO('sqlite:' . $root . '/storage/demo.sqlite');
$insert = $pdo->prepare('INSERT INTO user_notifications (user_id,type,importance,title,message,action_url,is_read,created_at) VALUES (1,?,?,?,?,?,?,?)');
foreach ([
    ['team','info','Tu espacio de trabajo está preparado','Ya puedes acceder al panel, consultar tus notificaciones y revisar la información de tu cuenta. El equipo compartirá aquí los avisos que requieren seguimiento.',0],
    ['access','warning','Revisa los accesos de tu equipo','Antes de incorporar a una nueva persona, confirma qué tareas realizará y qué permisos necesita. Evita conceder acceso administrativo si no es necesario.',0],
    ['support','success','Solicitud de soporte resuelta','La solicitud sobre los datos de la cuenta se ha resuelto. Puedes consultar el contexto desde tu espacio de trabajo y responder si necesitas otra aclaración.',1],
    ['team','info','Próxima reunión de coordinación','El jueves revisaremos las tareas abiertas, los responsables y las necesidades del equipo. Prepara tus preguntas para aprovechar la reunión.',1],
] as [$type,$importance,$title,$message,$read]) $insert->execute([$type,$importance,$title,$message,$url.'account',$read,gmdate('Y-m-d H:i:s')]);
copy($argv[1] . '/tests/fixtures/editorial-integration/application/router.php', $root . '/base-router.php');
foreach (['gfselect-preview.php' => 'docs-select.php','alerts-preview.php' => 'docs-alerts.php'] as $from => $to) copy($argv[1] . '/tests/fixtures/' . $from, $root . '/' . $to);
$file = $root . '/docs-select.php';
file_put_contents($file, str_replace(['<h1>GFSelect</h1>','>Nativo<','>País<','>Múltiple<'], ['<h1>Preferencias del equipo</h1><p>Selecciona el país de trabajo y los mercados con los que colaboras. El selector conserva las opciones del formulario y permite elegir varios valores.</p>','>Selector HTML de referencia<','>País de trabajo<','>Mercados de colaboración<'], file_get_contents($file)));
$file = $root . '/docs-alerts.php';
file_put_contents($file, str_replace('Revisión de Alerts','Confirmaciones y avisos del equipo',file_get_contents($file)));
file_put_contents($root . '/router.php', '<?php $source=' . var_export($argv[1],true) . ';' . <<<'PHP'
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/resources/')) {
    $file = realpath($source . $path); $resources = realpath($source . '/resources');
    if (!$file || !is_file($file) || !str_starts_with($file, $resources . DIRECTORY_SEPARATOR)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($file,'.js') ? 'application/javascript' : (str_ends_with($file,'.css') ? 'text/css' : mime_content_type($file)))); readfile($file); exit;
}
if (in_array($path,['/docs-select','/docs-alerts','/docs-mail','/docs-table'],true)) { require __DIR__ . $path . '.php'; exit; }
return require __DIR__ . '/base-router.php';
PHP);
file_put_contents($root . '/docs-mail.php', <<<'PHP'
<?php require __DIR__ . '/core/Load.php';
echo (new GFrame\Mail\MailTemplateRegistry(__DIR__ . '/app/views/templates/mail'))->render('mailTemplate',[
'title'=>'Bienvenida al equipo de Estudio Nexo','h1'=>'Tu espacio de trabajo está preparado','greeting'=>'Hola, Andrea.',
'p1'=>'Bienvenida a Estudio Nexo. Hemos preparado tu espacio para que puedas consultar los avisos del equipo, revisar la información de tu cuenta y organizar las tareas que requieren seguimiento. Antes de empezar, comprueba que tus datos son correctos y elige una contraseña que solo utilices en esta cuenta.',
'p2'=>'Desde el panel encontrarás las herramientas disponibles para tu rol. Si necesitas acceso a otra área, solicita únicamente los permisos necesarios para tu trabajo. El equipo de coordinación puede ayudarte a identificar la opción adecuada. Conserva este mensaje como referencia y accede siempre desde la dirección habitual de tu organización.',
'aHref'=>'https://estudionexo.example/account','aText'=>'Ir a mi espacio de trabajo','mailSiteName'=>'Estudio Nexo','mailLogo'=>rtrim(site_url,'/').'/public/img/logo.png']);
PHP);
file_put_contents($root . '/docs-table.php', <<<'PHP'
<!doctype html><html lang="es"><meta charset="utf-8"><link rel="stylesheet" href="/resources/modules/bootstrap/public/css/bootstrap.min.css"><body class="p-4"><main class="container"><h1>Solicitudes del equipo</h1><p>Busca una solicitud por su asunto o responsable. Pulsa los encabezados para ordenar las filas que ya están cargadas; este ejemplo no consulta nuevos registros al servidor.</p><div class="table-responsive"><label for="request-search" class="form-label">Buscar en las solicitudes</label><input type="search" id="request-search" class="form-control gf-search mb-3" placeholder="Asunto, responsable o estado"><table class="table table-striped gf-table"><thead><tr><th>ID</th><th class="sortable" data-type="text">Solicitud</th><th class="sortable" data-type="text">Responsable</th><th class="sortable" data-type="text">Estado</th><th class="sortable" data-type="date">Fecha</th></tr></thead><tbody><?php
foreach ([['S-104','Acceso al espacio de trabajo','Andrea','Pendiente','2026-10-20'],['S-103','Actualizar datos del equipo','Javier','En revisión','2026-10-19'],['S-102','Revisar permisos de soporte','Lucía','Resuelta','2026-10-18'],['S-101','Preparar bienvenida de nuevas cuentas','Mateo','En curso','2026-10-17'],['S-100','Consultar un aviso de la cuenta','Andrea','Resuelta','2026-10-16'],['S-099','Configurar preferencias regionales','Javier','Pendiente','2026-10-15']] as $row) { echo '<tr>'; foreach($row as $cell) echo '<td>'.htmlspecialchars($cell,ENT_QUOTES,'UTF-8').'</td>'; echo '</tr>'; }
?></tbody></table></div></main><script src="/resources/modules/jquery/public/jquery.min.js"></script><script src="/resources/modules/gf-table/public/gf-table.js"></script></body></html>
PHP);
echo "Aplicación temporal de módulos preparada.\n";
