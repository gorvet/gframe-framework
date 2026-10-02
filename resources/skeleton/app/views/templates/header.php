<!doctype html>
<html lang="<?= $this->metasController->getMetaTag('oglocale') ?: 'es' ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/public/img/favicon.png', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/public/img/apple-touch-icon.png', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $this->metasController->getMetaTag('title') ?></title>
    <meta name="description" content="<?= $this->metasController->getMetaTag('description') ?>">
    <meta name="robots" content="<?= $this->metasController->getMetaTag('robots') ?>">
    <link rel="canonical" href="<?= $this->metasController->getMetaTag('canonical') ?>">
    <?php foreach ($this->metasController->getHeaderJsScripts() as $script): ?>
        <script src="<?= htmlspecialchars(UrlHelper::assetUrl($script), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; ?>
    <?php foreach ($this->metasController->getCssLinks() as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(UrlHelper::assetUrl($css), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <?= $this->metasController->renderSchema() ?>
</head>
<body class="<?= htmlspecialchars((string)$bodyClass, ENT_QUOTES, 'UTF-8') ?>">
<form id="tokens" hidden>
    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars((string)($_SESSION['csrfToken'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrfTimestamp" value="<?= htmlspecialchars((string)($_SESSION['csrfTimestamp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
</form>
