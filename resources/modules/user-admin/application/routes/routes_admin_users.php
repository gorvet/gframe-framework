<?php

use RouteBuilder as Route;

Route::get('admin/users', 'user-admin/UserAdminController@index')
    ->module('user-admin')
    ->template('admin')
    ->middleware(['auth', 'can:users.view'])
    ->registerFinal();

Route::get('admin/users/create', 'user-admin/UserAdminController@create')
    ->module('user-admin')
    ->template('admin')
    ->middleware(['auth', 'can:users.create'])
    ->registerFinal();
