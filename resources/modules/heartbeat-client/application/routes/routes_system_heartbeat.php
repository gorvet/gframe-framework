<?php

use RouteBuilder as Route;

Route::post('ajax/heartbeat', 'system/heartbeat/HeartbeatController@dispatch')
    ->middleware(['auth'])
    ->excludeMiddleware(['CSRF'])
    ->noRefreshSession()
    ->registerFinal();
