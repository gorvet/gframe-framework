<?php

use RouteBuilder as Route;

Route::post('ajax/login', 'auth/AuthController@login')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/logout', 'auth/AuthController@logout')
    ->middleware(['auth'])
    ->registerFinal();

Route::post('ajax/register', 'auth/AuthController@register')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/verification', 'auth/AuthController@resendVerification')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/recovery', 'auth/AuthController@recovery')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/reset-password', 'auth/AuthController@resetPassword')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();
