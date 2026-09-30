<?php
$params = (array)($routeParams['params'] ?? []);
$errorCode = '500';
$errorTitle = 'No pudimos procesar la solicitud';
$errorMessage = trim((string)($params['infoMsg'] ?? '')) ?: 'Actualiza la página o inténtalo nuevamente dentro de unos minutos.';
$errorHelpMessage = trim((string)($params['helpMsg'] ?? ''));
$errorHelpURL = trim((string)($params['helpUrl'] ?? ''));
$errorHelpLabel = trim((string)($params['helpLabel'] ?? ''));
$errorHelpEnabled = ($params['helpEnabled'] ?? null) !== false;
$errorActionURL = trim((string)($params['tolink'] ?? '')) ?: site_url;
$errorActionLabel = 'Regresar';
require __DIR__ . '/_errorCard.php';
