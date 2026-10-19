<?php
RouteBuilder::get('editorial', 'editorial/EditorialController@edit')->middleware(['auth', 'admin'])->template('home')->view('editorialEdit')->registerFinal();
RouteBuilder::get('article', 'editorial/EditorialController@published')->noSession()->template('home')->view('editorialPublic')->registerFinal();
RouteBuilder::get('preview', 'editorial/EditorialController@preview')->middleware(['auth', 'admin'])->template('home')->view('editorialPublic')->registerFinal();
RouteBuilder::get('preview-state', 'editorial/EditorialController@state')->middleware(['auth', 'admin'])->registerFinal();
