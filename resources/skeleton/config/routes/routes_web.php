<?php

use RouteBuilder as Route;

Route::get('', 'home/HomeController@index')
    ->template('home')
    ->view('homeIndex')
    ->registerFinal();
