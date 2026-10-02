<?php
$history = (array)($data['data']['history'] ?? []);
$items = (array)($history['data']['items'] ?? []);
$meta = (array)($history['meta'] ?? []);
?>
<?php $filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all'; ?>
<div class="col-12" data-notifications-history>
    <div class="pagetitle"><h1 class="me-3">Notificaciones</h1></div>
    <form class="mb-4" data-notifications-filter><input type="hidden" name="filter" value="<?= $filter ?>"><div class="nav nav-pills gap-2" aria-label="Filtrar notificaciones"><button class="nav-link<?= $filter === 'all' ? ' active' : '' ?>" type="button" data-notifications-filter="all" aria-pressed="<?= $filter === 'all' ? 'true' : 'false' ?>">Todas</button><button class="nav-link<?= $filter === 'unread' ? ' active' : '' ?>" type="button" data-notifications-filter="unread" aria-pressed="<?= $filter === 'unread' ? 'true' : 'false' ?>">No leídas</button></div></form>
    <div data-notifications-results><?php include \GFrame\Modules\ModuleRuntime::file('views', 'notifications/_history.php', 'notifications'); ?></div>
</div>
