<?php

use RouteBuilder as Route;

Route::get('admin/media-file', 'media-library/MediaController@download')->module('media-library')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::get('media-access', 'media-library/MediaController@externalDownload')->module('media-library')->registerFinal();
Route::head('admin/media-file', 'media-library/MediaController@download')->module('media-library')->middleware(['auth', 'can:media.view'])->registerFinal();
Route::head('media-access', 'media-library/MediaController@externalDownload')->module('media-library')->registerFinal();

Route::get('admin/media', 'media-library/MediaController@index')
    ->module('media-library')
    ->template('admin')
    ->view('mediaIndex')
    ->middleware(['auth', 'can:media.view'])
    ->registerFinal();
