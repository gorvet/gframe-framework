<?php

use RouteBuilder as Route;

Route::get('admin/users', 'admin/users/UserAdminController@index')
    ->template('admin')
    ->view('usersIndex')
    ->middleware(['auth', 'can:users.view'])
    ->registerFinal();
