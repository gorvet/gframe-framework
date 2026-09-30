<?php

use RouteBuilder as Route;

Route::post('ajax/account/password', 'account/SelfAccountController@changePassword')
    ->middleware(['auth'])
    ->registerFinal();

Route::post('ajax/account/deactivate', 'account/SelfAccountController@deactivate')
    ->middleware(['auth'])
    ->registerFinal();
