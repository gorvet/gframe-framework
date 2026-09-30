<?php

use RouteBuilder as Route;

Route::post('ajax/notifications/inbox', 'notifications/NotificationController@inbox')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/history', 'notifications/NotificationController@history')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/mark-read', 'notifications/NotificationController@markRead')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/mark-all-read', 'notifications/NotificationController@markAllRead')->middleware(['auth'])->registerFinal();
Route::post('ajax/notifications/delete', 'notifications/NotificationController@delete')->middleware(['auth'])->registerFinal();
