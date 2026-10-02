<?php

use RouteBuilder as Route;

Route::get('login', 'auth-ui/AuthController@login')
    ->module('auth-ui')
    ->template('auth')
    ->view('authLogin')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/register', 'auth-ui/AuthController@registerPage')
    ->module('auth-ui')
    ->template('auth')
    ->view('authRegister')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/lostpassword', 'auth-ui/AuthController@recoveryPage')
    ->module('auth-ui')
    ->template('auth')
    ->view('authRecovery')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/resetpassword', 'auth-ui/AuthController@resetPage')
    ->module('auth-ui')
    ->template('auth')
    ->view('authReset')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/verify', 'auth-ui/AuthController@verify')
    ->module('auth-ui')
    ->template('auth')
    ->view('authVerify')
    ->middleware(['guest'])
    ->registerFinal();
