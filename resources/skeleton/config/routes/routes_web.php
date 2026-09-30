<?php

use RouteBuilder as Route;

$home = Route::get('', 'home/HomeController@index')
    ->template('home')
    ->view('homeIndex');
if (!(bool)config('app.public', true)) {
    $home->middleware(['auth']);
}
$home->registerFinal();
