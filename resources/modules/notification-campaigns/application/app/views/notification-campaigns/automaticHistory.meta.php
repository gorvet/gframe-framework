<?php
$meta = require \GFrame\Modules\ModuleRuntime::file('views', 'notification-campaigns/notification-campaigns.group.meta.php', 'notification-campaigns');
$meta['js'] = ['public/js/modules/notification-campaigns/campaigns.js'];
return $meta;
