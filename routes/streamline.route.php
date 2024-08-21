<?php
use Illuminate\Support\Facades\Route;
$prefix = config('streamline.route', 'streamline');
$middleware = config('streamline.middleware', []);
Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    Route::post('/',[\Iankibet\Streamline\StreamlineManager::class, 'handleRequest']);
    Route::get('/',[\Iankibet\Streamline\StreamlineManager::class, 'handleRequest']);
});
