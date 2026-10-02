<?php

use RouteBuilder as Route;

Route::post('ajax/account/password', 'self-account/SelfAccountController@changePassword')
    ->module('self-account')
    ->middleware(['auth'])
    ->registerFinal();

Route::post('ajax/account/deactivate', 'self-account/SelfAccountController@deactivate')
    ->module('self-account')
    ->middleware(['auth'])
    ->registerFinal();
