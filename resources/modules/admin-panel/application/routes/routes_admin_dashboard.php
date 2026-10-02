<?php

use RouteBuilder as Route;

Route::get('admin', 'admin-panel/AdminController@index')
    ->module('admin-panel')
    ->template('admin')
    ->view('adminIndex')
    ->middleware(['auth', 'admin'])
    ->registerFinal();
