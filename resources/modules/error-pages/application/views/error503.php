<?php
$params = (array)($routeParams['params'] ?? []);
$errorCode = '503';
$errorTitle = 'Servicio temporalmente no disponible';
$errorMessage = trim((string)($params['infoMsg'] ?? '')) ?: 'Estamos trabajando para restablecer el servicio. Inténtalo nuevamente dentro de unos minutos.';
$errorHelpMessage = trim((string)($params['helpMsg'] ?? ''));
$errorHelpURL = trim((string)($params['helpUrl'] ?? ''));
$errorHelpLabel = trim((string)($params['helpLabel'] ?? ''));
$errorHelpEnabled = ($params['helpEnabled'] ?? null) !== false;
$errorActionURL = trim((string)($params['tolink'] ?? '')) ?: site_url;
$errorActionLabel = 'Regresar';
require __DIR__ . '/_errorCard.php';
