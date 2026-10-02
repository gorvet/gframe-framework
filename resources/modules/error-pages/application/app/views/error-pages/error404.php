<?php
$params = (array)($routeParams['params'] ?? []);
$errorCode = '404';
$errorTitle = 'Página no encontrada';
$errorMessage = trim((string)($params['infoMsg'] ?? '')) ?: 'La dirección puede ser incorrecta o el contenido ya no está disponible.';
$errorHelpMessage = trim((string)($params['helpMsg'] ?? ''));
$errorHelpURL = trim((string)($params['helpUrl'] ?? ''));
$errorHelpLabel = trim((string)($params['helpLabel'] ?? ''));
$errorHelpEnabled = ($params['helpEnabled'] ?? null) !== false;
if (!$errorHelpEnabled) {
    $errorHelpMessage = $errorHelpURL = $errorHelpLabel = '';
}
$errorActionURL = trim((string)($params['tolink'] ?? '')) ?: site_url;
$errorActionLabel = rtrim($errorActionURL, '/') === rtrim(site_url, '/') ? 'Volver al inicio' : 'Regresar';
require \GFrame\Modules\ModuleRuntime::file('views', 'error-pages/_errorCard.php', 'error-pages') ?? realpath(__DIR__ . '/_errorCard.php');
