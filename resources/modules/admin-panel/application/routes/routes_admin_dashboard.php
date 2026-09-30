<?php

use RouteBuilder as Route;

Route::get('admin', 'admin/AdminController@index')
    ->template('admin')
    ->view('adminIndex')
    ->middleware(['auth', 'admin'])
    ->registerFinal();
