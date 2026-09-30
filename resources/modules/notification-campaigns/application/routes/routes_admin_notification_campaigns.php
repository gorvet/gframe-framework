<?php

use RouteBuilder as Route;

Route::get('admin/notifications/campaigns', 'admin/notifications/CampaignController@index')->template('account')->view('campaigns/index')->middleware(['auth', 'can:notifications.campaigns.view'])->registerFinal();
