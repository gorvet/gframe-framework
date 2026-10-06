<?php
$meta = require \GFrame\Modules\ModuleRuntime::file('views', 'notification-campaigns/notification-campaigns.group.meta.php', 'notification-campaigns');
$meta['css'][] = 'public/vendors/external/flatpickr/flatpickr.min.css';
$meta['css'][] = 'public/vendors/external/flatpickr/gframe-flatpickr.css';
$meta['css'][] = 'public/vendors/internal/gf-select/gf-select.css';
$meta['js'] = ['public/vendors/external/flatpickr/flatpickr.js', 'public/vendors/external/flatpickr/flatpickr_es.js', 'public/vendors/internal/gf-select/gf-select.js', 'public/js/modules/notification-campaigns/campaigns.js'];
return $meta;
