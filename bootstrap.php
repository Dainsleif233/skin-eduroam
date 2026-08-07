<?php

use App\Services\Hook;
use App\Services\Plugin;
use App\Models\User;
use Blessing\Filter;
use Illuminate\Http\Request;
use Illuminate\Contracts\Events\Dispatcher;

// 为 eduroam 用户记录 player 名和 QQ 的变更。
// 本插件设计为全员走 eduroam 注册，非 eduroam 用户直接跳过。
function update() {
    return function ($event) {
        if (empty($event->type)) {
            $eduroam = Blessing\Eduroam\Eduroam::findByUserUid($event->player->uid);
            if (!$eduroam) return;
            $eduroam->addName($event->player->name)->save();
        } elseif ($event->type === 'email') {
            $user = $event->user ?? User::where('uid', $event->player->uid)->first();
            $eduroam = Blessing\Eduroam\Eduroam::findByUser($user);
            if (!$eduroam) return;
            $eduroam->addQQ(explode('@', $user->email)[0])->save();
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