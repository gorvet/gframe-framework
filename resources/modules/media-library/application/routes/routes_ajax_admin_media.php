<?php

use RouteBuilder as Route;

Route::post('ajax/admin/media/list', 'admin/media/MediaController@list')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/field', 'admin/media/MediaController@field')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/upload', 'admin/media/MediaController@upload')->middleware(['auth', 'can:media.add'])->registerFinal();
Route::post('ajax/admin/media/hotlink', 'admin/media/MediaController@hotlink')->middleware(['auth', 'can:media.add'])->registerFinal();
Route::post('ajax/admin/media/delete', 'admin/media/MediaController@delete')->middleware(['auth', 'can:media.delete'])->registerFinal();
Route::post('ajax/admin/media/details', 'admin/media/MediaController@details')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/save', 'admin/media/MediaController@save')->middleware(['auth', 'can:media.edit'])->registerFinal();
Route::post('ajax/admin/media/base64', 'admin/media/MediaController@base64')->middleware(['auth', 'can:media.add'])->registerFinal();
Route::post('ajax/admin/media/quota', 'admin/media/MediaController@quota')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/sync', 'admin/media/MediaController@sync')->middleware(['auth', 'can:media.sync'])->registerFinal();
