<?php

use App\Services\Hook;
use Blessing\Filter;

return function (Filter $filter) {
    $filter->add('user_can_edit_profile', Blessing\Eduroam\UserFilter::class);
    $filter->add('auth_page_rows:register', function ($rows) {
        $rows[] = 'Blessing\Eduroam::rows.goto-eduroam';
        return $rows;
    });
    Hook::addRoute(function ($routes) {
        Route::namespace('Blessing\Eduroam')->middleware(['web','guest'])->prefix('auth/register')->group(function () {
            Route::get('eduroam', 'AuthController@eduroam');
            Route::post('eduroam', 'AuthController@handleEduroam');
            if(option('replace', null)) Route::redirect('', 'register/eduroam');
        });
    });
};