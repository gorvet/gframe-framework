<?php

use RouteBuilder as Route;

Route::get('notifications', 'notifications/NotificationController@index')
    ->template('account')->view('notificationsIndex')->middleware(['auth'])->registerFinal();
