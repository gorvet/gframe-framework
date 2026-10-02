<?php

use RouteBuilder as Route;

Route::post('ajax/login', 'auth-ui/AuthController@login')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/logout', 'auth-ui/AuthController@logout')
    ->module('auth-ui')
    ->middleware(['auth'])
    ->registerFinal();

Route::post('ajax/register', 'auth-ui/AuthController@register')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/verification', 'auth-ui/AuthController@resendVerification')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/verifyacount', 'auth-ui/AuthController@resendVerification')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/validateacount', 'auth-ui/AuthController@validateAcount')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/recovery', 'auth-ui/AuthController@recovery')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/lostpassword', 'auth-ui/AuthController@recovery')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/reset-password', 'auth-ui/AuthController@resetPassword')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();

Route::post('ajax/resetpassword', 'auth-ui/AuthController@resetPassword')
    ->module('auth-ui')
    ->middleware(['honeypot', 'guest'])
    ->excludeMiddleware(['CSRF'])
    ->registerFinal();
