<?php
$errorCode = trim((string)($errorCode ?? 'Error'));
$errorTitle = trim((string)($errorTitle ?? 'No pudimos completar la solicitud'));
$errorMessage = trim((string)($errorMessage ?? 'Inténtalo nuevamente o vuelve al inicio.'));
$errorHelpMessage = trim((string)($errorHelpMessage ?? ''));
$errorHelpURL = trim((string)($errorHelpURL ?? ''));
$errorHelpLabel = trim((string)($errorHelpLabel ?? ''));
$errorHelpEnabled = ($errorHelpEnabled ?? true) !== false;
$errorActionURL = trim((string)($errorActionURL ?? '')) ?: site_url;
$errorActionLabel = trim((string)($errorActionLabel ?? 'Volver al inicio'));
?>
<article class="error-card" aria-labelledby="errorTitle">
    <p class="error-code mb-3" aria-hidden="true"><?= htmlspecialchars($errorCode, ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="error-title" id="errorTitle"><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="error-message"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($errorHelpEnabled && ($errorHelpMessage !== '' || ($errorHelpURL !== '' && $errorHelpLabel !== ''))): ?>
        <p class="error-help mb-0">
            <?php if ($errorHelpMessage !== ''): ?><span><?= htmlspecialchars($errorHelpMessage, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <?php if ($errorHelpURL !== '' && $errorHelpLabel !== ''): ?> <a href="<?= htmlspecialchars($errorHelpURL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($errorHelpLabel, ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
        </p>
    <?php endif; ?>
    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mt-4">
        <a class="btn btn-primary px-4" href="<?= htmlspecialchars($errorActionURL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($errorActionLabel, ENT_QUOTES, 'UTF-8') ?></a>
        <?php if (rtrim($errorActionURL, '/') !== rtrim((string)site_url, '/')): ?>
            <a class="btn btn-outline-primary px-4" href="<?= htmlspecialchars((string)site_url, ENT_QUOTES, 'UTF-8') ?>">Ir al inicio</a>
        <?php endif; ?>
    </div>
</article>
