<?php

use App\Services\Hook;
use App\Services\Plugin;
use App\Models\User;
use Blessing\Filter;
use Illuminate\Http\Request;
use Illuminate\Contracts\Events\Dispatcher;

function update() {
    return function ($event) {
        if (empty($event->type)) {
            $eduroam = Blessing\Eduroam\Eduroam::where('eduroam', User::where('uid', $event->player->uid)->first()->eduroam)->first();
            $eduroam->addName($event->player->name)->save();
        } elseif ($event->type == 'email') {
            $eduroam = Blessing\Eduroam\Eduroam::where('eduroam', $event->user->eduroam)->first();
            $eduroam->addQQ(explode('@', $event->user->email)[0])->save();
        }
    };
}

return function (Filter $filter, Request $request, Plugin $plugin, Dispatcher $events) {
    if(option('prevent_edit_email', null)) $filter->add('user_can_edit_profile', Blessing\Eduroam\UserFilter::class);
    $filter->add('auth_page_rows:register', function ($rows) {
        $rows[] = 'Blessing\Eduroam::rows.goto-eduroam';
        return $rows;
    });
    $filter->add('scripts', function ($scripts) use ($plugin, $request) {
        if ($request->is('auth/register/eduroam')) $scripts[] = ['src' => $plugin->assets('captcha.js'), 'crossorigin' => 'anonymous'];
        return $scripts;
    });
    $events->listen(App\Events\PlayerProfileUpdated::class, update());
    $events->listen(App\Events\PlayerWasAdded::class, update());
    $events->listen(App\Events\UserProfileUpdated::class, update());
    Hook::addRoute(function ($routes) {
        Route::namespace('Blessing\Eduroam')->middleware(['web','guest'])->prefix('auth/register')->group(function () {
            Route::get('eduroam', 'AuthController@eduroam');
            Route::post('eduroam', 'AuthController@handleEduroam');
            if(option('replace', null)) Route::redirect('', 'register/eduroam');
        });
    });
};