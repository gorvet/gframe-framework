<footer class="gframe-footer">
    <div class="container py-4 text-center">
        <?= $this->metasController->getFooterCredits() ?>
    </div>
</footer>
<?php foreach ($this->metasController->getJsScripts() as $script): ?>
    <script src="<?= htmlspecialchars(UrlHelper::assetUrl($script), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
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
