<?php

use RouteBuilder as Route;

Route::post('ajax/notifications/detail', 'notifications/NotificationController@detail')->module('notifications')->middleware(['auth'])->registerFinal();

Route::post('ajax/notifications/inbox', 'notifications/NotificationController@inbox')->module('notifications')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/history', 'notifications/NotificationController@history')->module('notifications')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/mark-read', 'notifications/NotificationController@markRead')->module('notifications')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/mark-unread', 'notifications/NotificationController@markUnread')->module('notifications')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/mark-all-read', 'notifications/NotificationController@markAllRead')->module('notifications')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/delete', 'notifications/NotificationController@delete')->module('notifications')->middleware(['auth'])->registerFinal();
