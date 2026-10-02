<?php

use RouteBuilder as Route;

Route::post('ajax/admin/media/list', 'media-library/MediaController@list')->module('media-library')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/field', 'media-library/MediaController@field')->module('media-library')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/upload', 'media-library/MediaController@upload')->module('media-library')->middleware(['auth', 'can:media.add'])->registerFinal();
Route::post('ajax/admin/media/hotlink', 'media-library/MediaController@hotlink')->module('media-library')->middleware(['auth', 'can:media.add'])->registerFinal();
Route::post('ajax/admin/media/delete', 'media-library/MediaController@delete')->module('media-library')->middleware(['auth', 'can:media.delete'])->registerFinal();
Route::post('ajax/admin/media/details', 'media-library/MediaController@details')->module('media-library')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/save', 'media-library/MediaController@save')->module('media-library')->middleware(['auth', 'can:media.edit'])->registerFinal();
Route::post('ajax/admin/media/base64', 'media-library/MediaController@base64')->module('media-library')->middleware(['auth', 'can:media.add'])->registerFinal();
Route::post('ajax/admin/media/quota', 'media-library/MediaController@quota')->module('media-library')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::post('ajax/admin/media/sync', 'media-library/MediaController@sync')->module('media-library')->middleware(['auth', 'can:media.sync'])->registerFinal();
