<?php

use RouteBuilder as Route;

Route::post('ajax/admin/notifications/campaigns/list', 'admin/notifications/CampaignController@list')->middleware(['auth', 'can:notifications.campaigns.view'])->registerFinal();
Route::post('ajax/admin/notifications/campaigns/create', 'admin/notifications/CampaignController@create')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
Route::post('ajax/admin/notifications/campaigns/action', 'admin/notifications/CampaignController@action')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
