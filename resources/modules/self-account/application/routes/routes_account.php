<?php

use RouteBuilder as Route;

Route::get('account', 'account/SelfAccountController@index')
    ->template('account')
    ->view('accountIndex')
    ->middleware(['auth'])
    ->registerFinal();
