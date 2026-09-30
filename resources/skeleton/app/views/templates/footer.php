<?php
$footerData = $data ?? [];
$footerContent = $this->renderFooterArea('content', $routeParams, $footerData);
$footerCopyright = $this->renderFooterArea('copyright', $routeParams, $footerData);
$footerCredits = $this->renderFooterArea('credits', $routeParams, $footerData);
?>
<?php if ($footerContent !== '' || $footerCopyright !== '' || $footerCredits !== ''): ?>
<footer class="gframe-footer">
    <?php if ($footerContent !== ''): ?><div class="gframe-footer-content"><?= $footerContent ?></div><?php endif; ?>
    <?php if ($footerCopyright !== '' || $footerCredits !== ''): ?>
    <div class="footer-credits">
        <?php if ($footerCopyright !== ''): ?><div class="copyright"><?= $footerCopyright ?></div><?php endif; ?>
        <?php if ($footerCredits !== ''): ?><div class="credits"><?= $footerCredits ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
</footer>
<?php endif; ?>
<?php foreach ($this->metasController->getJsScripts() as $script): ?>
    <script src="<?= htmlspecialchars(UrlHelper::assetUrl($script), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<?php if (!empty($routeParams['isProtected'])): ?>
    <?php if (is_file(ABSPATH . 'public/js/core/heartbeat.js')): ?><script src="<?= htmlspecialchars(UrlHelper::assetUrl('public/js/core/heartbeat.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
    <?php if (is_file(ABSPATH . 'public/js/core/session.js')): ?><script src="<?= htmlspecialchars(UrlHelper::assetUrl('public/js/core/session.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
<?php endif; ?>
<?php if (defined('Metricool') && Metricool && defined('METRICOOL_HASH') && METRICOOL_HASH !== ''): ?>
    <script>
        (function () {
            const script = document.createElement('script');
            script.src = 'https://tracker.metricool.com/resources/be.js';
            script.onload = () => window.beTracker?.t({hash: <?= json_encode(METRICOOL_HASH) ?>});
            document.head.appendChild(script);
        })();
    </script>
<?php endif; ?>
</body>
</html>
