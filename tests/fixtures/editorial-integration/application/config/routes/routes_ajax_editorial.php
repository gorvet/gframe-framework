<?php
RouteBuilder::post('ajax/editorial', 'editorial/EditorialController@update')->middleware(['auth', 'admin'])->registerFinal();
