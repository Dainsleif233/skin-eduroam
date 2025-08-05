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
            $eduroam = Blessing\Eduroam\Eduroam::where('eduroam', User::where('uid', $event->user->uid)->first()->eduroam)->first();
            $eduroam->addQQ(explode('@', $event->user->email)[0])->save();
        }
    };
}

return function (Filter $filter, Request $request, Plugin $plugin, Dispatcher $events) {
    $filter->add('scripts', function ($scripts) use ($plugin, $request) {
        if ($request->is('auth/register/eduroam')) $scripts[] = ['src' => $plugin->assets('captcha.js'), 'crossorigin' => 'anonymous'];
        return $scripts;
    });
    $events->listen(App\Events\PlayerProfileUpdated::class, update());  // player
    $events->listen(App\Events\PlayerWasAdded::class, update());        // player
    $events->listen(App\Events\UserProfileUpdated::class, update());    // type: email, user
    Hook::addRoute(function ($_) {
        Route::namespace('Blessing\Eduroam')->middleware(['web','guest'])->prefix('auth/register')->group(function () {
            Route::get('eduroam', 'AuthController@eduroam');
            Route::post('eduroam', 'AuthController@handleEduroam');
            Route::redirect('', 'register/eduroam');
        });
    });
};