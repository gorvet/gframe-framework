<?php

use RouteBuilder as Route;

Route::get('api/bearer-test', 'api/TestController@index')->context(['api_token' => 'test'])->registerFinal();
