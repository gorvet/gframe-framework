<?php
$notificationBell = \GFrame\Modules\ModuleRuntime::file('views', 'notifications/parts/bell.php', 'notifications');
if ($notificationBell !== null) include $notificationBell;
