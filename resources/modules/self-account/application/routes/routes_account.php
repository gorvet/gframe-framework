<?php

use RouteBuilder as Route;

Route::get('account', 'self-account/SelfAccountController@index')
    ->module('self-account')
    ->template('admin')
    ->middleware(['auth'])
    ->registerFinal();
