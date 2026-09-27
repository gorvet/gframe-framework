<!doctype html>
<html lang="<?= $this->metasController->getMetaTag('oglocale') ?: 'es' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->metasController->getMetaTag('title') ?></title>
    <meta name="description" content="<?= $this->metasController->getMetaTag('description') ?>">
    <meta name="robots" content="<?= $this->metasController->getMetaTag('robots') ?>">
    <link rel="canonical" href="<?= $this->metasController->getMetaTag('canonical') ?>">
    <?php foreach ($this->metasController->getCssLinks() as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(UrlHelper::assetUrl($css), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <?php foreach ($this->metasController->getHeaderJsScripts() as $script): ?>
        <script src="<?= htmlspecialchars(UrlHelper::assetUrl($script), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; ?>
    <?= $this->metasController->renderSchema() ?>
</head>
<body class="<?= htmlspecialchars((string)$bodyClass, ENT_QUOTES, 'UTF-8') ?>">
