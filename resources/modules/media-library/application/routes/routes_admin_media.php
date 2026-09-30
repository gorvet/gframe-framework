<?php

use RouteBuilder as Route;

Route::get('admin/media', 'admin/media/MediaController@index')
    ->template('account')
    ->view('mediaIndex')
    ->middleware(['auth', 'can:media.view'])
    ->registerFinal();
