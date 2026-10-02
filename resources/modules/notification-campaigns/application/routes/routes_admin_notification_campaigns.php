<?php

use RouteBuilder as Route;

Route::get('admin/notifications/campaigns/automatic', 'notification-campaigns/CampaignController@automatic')->module('notification-campaigns')->template('admin')->view('automatic')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
Route::get('admin/notifications/campaigns/automatic/history', 'notification-campaigns/CampaignController@automaticHistory')->module('notification-campaigns')->template('admin')->view('automaticHistory')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();

Route::get('admin/notifications/campaigns', 'notification-campaigns/CampaignController@index')->module('notification-campaigns')->template('admin')->view('index')->middleware(['auth', 'can:notifications.campaigns.view'])->registerFinal();
Route::get('admin/notifications/campaigns/new', 'notification-campaigns/CampaignController@new')->module('notification-campaigns')->template('admin')->view('form')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
Route::get('admin/notifications/campaigns/edit', 'notification-campaigns/CampaignController@edit')->module('notification-campaigns')->template('admin')->view('form')->middleware(['auth', 'can:notifications.campaigns.manage'])->registerFinal();
