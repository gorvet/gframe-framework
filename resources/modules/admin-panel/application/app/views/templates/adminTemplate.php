<div class="container-fluid p-0">
    <div class="row g-0">
        <aside id="sidebar" class="sidebar col" aria-label="Navegación principal">
            <?php include \GFrame\Modules\ModuleRuntime::file('views', 'admin-panel/parts/aside.php', 'admin-panel'); ?>
        </aside>
        <main id="main" class="main col admin" tabindex="-1">
            <?php include \GFrame\Modules\ModuleRuntime::file('views', 'admin-panel/parts/navbar.php', 'admin-panel'); ?>
            <div class="section dashboard pt-3">
                <div class="row">
                    <?= $content ?>
                </div>
            </div>
        </main>
    </div>
</div>
<div id="toastBox" aria-live="polite"></div>
