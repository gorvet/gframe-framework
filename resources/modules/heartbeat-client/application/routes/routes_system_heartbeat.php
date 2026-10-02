<?php

use RouteBuilder as Route;

Route::post('ajax/heartbeat', 'heartbeat-client/HeartbeatController@dispatch')
    ->module('heartbeat-client')
    ->middleware(['auth'])
    ->excludeMiddleware(['CSRF'])
    ->noRefreshSession()
    ->registerFinal();
