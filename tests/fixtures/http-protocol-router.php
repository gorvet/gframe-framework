<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/redirect-file') { header('Location: file://' . __FILE__); exit; }
if ($path === '/redirect-http') { header('Location: /ok'); exit; }
header('Content-Type: application/json');
echo '{"ok":true}';
