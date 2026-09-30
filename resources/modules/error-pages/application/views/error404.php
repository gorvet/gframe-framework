<?php
$params = (array)($routeParams['params'] ?? []);
$errorCode = '404';
$errorTitle = 'Página no encontrada';
$errorMessage = trim((string)($params['infoMsg'] ?? '')) ?: 'La dirección puede ser incorrecta o el contenido ya no está disponible.';
$errorHelpMessage = trim((string)($params['helpMsg'] ?? ''));
$errorHelpURL = trim((string)($params['helpUrl'] ?? ''));
$errorHelpLabel = trim((string)($params['helpLabel'] ?? ''));
$errorHelpEnabled = ($params['helpEnabled'] ?? null) !== false;
$errorActionURL = trim((string)($params['tolink'] ?? '')) ?: site_url;
$errorActionLabel = 'Regresar';
require __DIR__ . '/_errorCard.php';
