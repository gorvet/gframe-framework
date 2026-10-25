<?php

use RouteBuilder as Route;

Route::post('ajax/admin/users/create', 'user-admin/UserAdminController@store')
    ->module('user-admin')
    ->middleware(['auth', 'can:users.create'])
    ->registerFinal();

Route::post('ajax/admin/users/list', 'user-admin/UserAdminController@list')
    ->module('user-admin')
    ->middleware(['auth', 'can:users.view'])
    ->registerFinal();

Route::post('ajax/admin/users/update', 'user-admin/UserAdminController@update')
    ->module('user-admin')
    ->middleware(['auth', 'can:users.manage'])
    ->registerFinal();
