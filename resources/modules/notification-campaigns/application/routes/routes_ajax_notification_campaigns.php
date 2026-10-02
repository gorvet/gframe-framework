<?php

use RouteBuilder as Route;
Route::post('ajax/admin/notifications/campaigns/automatic/history', 'notification-campaigns/CampaignController@automaticHistory')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();

Route::post('ajax/admin/notifications/campaigns/preview', 'notification-campaigns/CampaignController@previewAudience')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
Route::post('ajax/admin/notifications/campaigns/test', 'notification-campaigns/CampaignController@testSend')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();

Route::post('ajax/admin/notifications/campaigns/automatic/save', 'notification-campaigns/CampaignController@saveAutomatic')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
Route::post('ajax/admin/notifications/campaigns/automatic/send', 'notification-campaigns/CampaignController@sendAutomatic')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();

Route::post('ajax/admin/notifications/campaigns/list', 'notification-campaigns/CampaignController@list')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.view'])->registerFinal();
Route::post('ajax/admin/notifications/campaigns/create', 'notification-campaigns/CampaignController@create')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
Route::post('ajax/admin/notifications/campaigns/update', 'notification-campaigns/CampaignController@update')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
Route::post('ajax/admin/notifications/campaigns/action', 'notification-campaigns/CampaignController@action')->module('notification-campaigns')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
