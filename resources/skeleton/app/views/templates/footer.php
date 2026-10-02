<?php
$footerData = $data ?? [];
$footerContent = $this->renderFooterArea('content', $routeParams, $footerData);
$footerCopyright = $this->renderFooterArea('copyright', $routeParams, $footerData);
$footerCredits = $this->renderFooterArea('credits', $routeParams, $footerData);
$footerScripts = $this->metasController->getJsScripts();
$footerScripts = array_values(array_unique($footerScripts));
?>
<?php if ($footerContent !== '' || $footerCopyright !== '' || $footerCredits !== ''): ?>
<footer id="footer" class="gframe-footer">
    <?php if ($footerContent !== ''): ?><div class="gframe-footer-content"><?= $footerContent ?></div><?php endif; ?>
    <?php if ($footerCopyright !== '' || $footerCredits !== ''): ?>
    <div class="footer-credits">
        <?php if ($footerCopyright !== ''): ?><div class="copyright"><?= $footerCopyright ?></div><?php endif; ?>
        <?php if ($footerCredits !== ''): ?><div class="credits"><?= $footerCredits ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
</footer>
<?php endif; ?>
<div id="toastBox"></div>
<script>
    window.site_url = <?= json_encode(rtrim((string)site_url, '/') . '/', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.is_protected = <?= !empty($routeParams['isProtected']) ? 'true' : 'false' ?>;
</script>
<?php foreach ($footerScripts as $script): ?>
    <script src="<?= htmlspecialchars(UrlHelper::assetUrl($script), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<?php if (!empty($routeParams['expired'])): ?>
<script>
    (function () {
        var rawBase = String(window.site_url || (window.location.origin + '/'));
        var normalized = rawBase.replace(/^https?:\/\//i, '').replace(/\/+$/g, '').toLowerCase();
        var scopeId = normalized.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'default';
        try { localStorage.setItem('session_expired_' + scopeId, String(Date.now())); } catch (_) {}
    })();
</script>
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
