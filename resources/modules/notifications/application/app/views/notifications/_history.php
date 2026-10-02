<div class="card"><div class="card-body">
    <?php include \GFrame\Modules\ModuleRuntime::file('views', 'notifications/_inbox.php', 'notifications'); ?>
</div></div>
<?php $page = max(1, (int)($meta['page'] ?? 1)); $pages = max(1, (int)($meta['total_pages'] ?? 1)); ?>
<?php if ($pages > 1) PaginationHelper::render($pages, $page); ?>
