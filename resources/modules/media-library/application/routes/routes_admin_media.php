<?php

use RouteBuilder as Route;

Route::get('admin/media', 'media-library/MediaController@index')
    ->module('media-library')
    ->template('admin')
    ->view('mediaIndex')
    ->middleware(['auth', 'can:media.view'])
    ->registerFinal();
