<!doctype html>
<html lang="<?= $this->metasController->getMetaTag('oglocale') ?: 'es' ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/public/img/favicon.png', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(rtrim((string)site_url, '/') . '/public/img/apple-touch-icon.png', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $this->metasController->getMetaTag('title') ?></title>
    <meta name="description" content="<?= $this->metasController->getMetaTag('description') ?>">
    <meta name="robots" content="<?= $this->metasController->getMetaTag('robots') ?>, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="<?= $this->metasController->getMetaTag('canonical') ?>">
    <?php
    $socialTags = [
        'author' => $this->metasController->getMetaTag('author'),
        'keywords' => $this->metasController->getMetaTag('keywords'),
        'twitter:card' => $this->metasController->getMetaTag('xcard') ?: 'summary',
        'twitter:title' => $this->metasController->getMetaTag('title'),
        'twitter:description' => $this->metasController->getMetaTag('description'),
        'twitter:image' => $this->metasController->getMetaTag('ximage') ?: $this->metasController->getMetaTag('ogimage'),
        'twitter:image:alt' => $this->metasController->getMetaTag('ximagealt') ?: $this->metasController->getMetaTag('ogimagealt'),
        'twitter:site' => $this->metasController->getMetaTag('xsite'),
        'twitter:creator' => $this->metasController->getMetaTag('xcreator'),
        'og:type' => $this->metasController->getMetaTag('ogtype') ?: 'website',
        'og:site_name' => $this->metasController->getMetaTag('ogsite_name'),
        'og:url' => $this->metasController->getMetaTag('ogurl') ?: $this->metasController->getMetaTag('canonical'),
        'og:locale' => $this->metasController->getMetaTag('oglocale'),
        'og:title' => $this->metasController->getMetaTag('title'),
        'og:description' => $this->metasController->getMetaTag('description'),
        'og:image' => $this->metasController->getMetaTag('ogimage'),
        'og:image:width' => $this->metasController->getMetaTag('ogimagew'),
        'og:image:height' => $this->metasController->getMetaTag('ogimageh'),
        'og:image:alt' => $this->metasController->getMetaTag('ogimagealt') ?: $this->metasController->getMetaTag('ximagealt'),
    ];
    foreach ($socialTags as $tag => $value):
        if ($value === '') continue;
    ?>
    <meta <?= str_starts_with($tag, 'og:') ? 'property' : 'name' ?>="<?= $tag ?>" content="<?= $value ?>">
    <?php endforeach; ?>
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
    <?= \GFrame\Security\CsrfToken::fields() ?>
</form>
