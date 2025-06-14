<?php

use App\Services\Hook;

return function () {
    Hook::addRoute(function ($routes) {
        Route::namespace('Blessing\Eduroam')->middleware(['web','guest'])->prefix('auth/register')->group(function () {
            Route::get('', 'AuthController@eduroam');
            Route::post('', 'AuthController@handleEduroam');
        });
    });
};