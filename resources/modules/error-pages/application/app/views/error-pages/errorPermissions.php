<?php
$params = (array)($routeParams['params'] ?? []);
$errorCode = '403';
$errorTitle = 'Permisos insuficientes';
$errorMessage = trim((string)($params['infoMsg'] ?? '')) ?: 'Tu cuenta no tiene permisos para modificar este elemento.';
$errorHelpMessage = trim((string)($params['helpMsg'] ?? ''));
$errorHelpURL = trim((string)($params['helpUrl'] ?? ''));
$errorHelpLabel = trim((string)($params['helpLabel'] ?? ''));
$errorHelpEnabled = ($params['helpEnabled'] ?? null) !== false;
$errorActionURL = trim((string)($params['tolink'] ?? '')) ?: site_url;
$errorActionLabel = 'Regresar';
require \GFrame\Modules\ModuleRuntime::file('views', 'error-pages/_errorCard.php', 'error-pages') ?? realpath(__DIR__ . '/_errorCard.php');
