<?php

use RouteBuilder as Route;

Route::get('notifications', 'notifications/NotificationController@index')
    ->module('notifications')->template('admin')->view('notificationsIndex')->middleware(['auth'])->registerFinal();
Route::get('notifications/view', 'notifications/NotificationController@view')
    ->module('notifications')->template('admin')->view('notificationsView')->middleware(['auth'])->registerFinal();
