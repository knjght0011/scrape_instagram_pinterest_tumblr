<?php
Route::get('/', 'HomeController@index');
Route::get('/tumblr', 'TumblrController@index');
Route::post('/tumblr', 'TumblrController@handleSubmit');

Route::get('/pinterest', 'PinterestController@index');
Route::post('/pinterest', 'PinterestController@handleSubmit');

Route::get('/instagram', 'InstagramController@index');
Route::post('/instagram', 'InstagramController@handleSubmit');