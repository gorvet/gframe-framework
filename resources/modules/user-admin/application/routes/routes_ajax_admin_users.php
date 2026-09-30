<?php

use RouteBuilder as Route;

Route::post('ajax/admin/users/list', 'admin/users/UserAdminController@list')
    ->middleware(['auth', 'can:users.view'])
    ->registerFinal();

Route::post('ajax/admin/users/update', 'admin/users/UserAdminController@update')
    ->middleware(['auth', 'can:users.manage'])
    ->registerFinal();
