<?php

use RouteBuilder as Route;

Route::get('login', 'auth/AuthController@login')
    ->template('auth')
    ->view('authLogin')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/register', 'auth/AuthController@registerPage')
    ->template('auth')
    ->view('authRegister')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/lostpassword', 'auth/AuthController@recoveryPage')
    ->template('auth')
    ->view('authRecovery')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/resetpassword', 'auth/AuthController@resetPage')
    ->template('auth')
    ->view('authReset')
    ->middleware(['guest'])
    ->noAction()
    ->registerFinal();

Route::get('login/verify', 'auth/AuthController@verify')
    ->template('auth')
    ->view('authVerify')
    ->middleware(['guest'])
    ->registerFinal();
