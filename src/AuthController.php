<?php

namespace Blessing\Eduroam;

use App\Events;
use App\Models\Player;
use App\Models\User;
use App\Rules;
use Blessing\Filter;
use Blessing\Rejection;
use Carbon\Carbon;
use Http;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Vectorface\Whip\Whip;

class AuthController extends Controller {
    public function eduroam(Filter $filter) {
        $register_with = option('register_with_player_name', null) ? 'Blessing\Eduroam::rows.player_name' : 'Blessing\Eduroam::rows.nickname';
        $return_register = option('replace', null) ? null : 'Blessing\Eduroam::rows.return-register';
        $value = [
            'site_name' => option_localized('site_name'),
            'register_with' => $register_with,
            'return_register' => $return_register
        ];
        return view('Blessing\Eduroam::eduroam', $value);
    }
    public function handleEduroam(Request $request, Dispatcher $dispatcher, Filter $filter) {
        $can = $filter->apply('can_register', null);
        if ($can instanceof Rejection) return back()->withErrors(['login' => $can->getReason()]);
        $rule = option('register_with_player_name') ?
            ['player_name' => [
                'required',
                new Rules\PlayerName(),
                'min:'.option('player_name_length_min'),
                'max:'.option('player_name_length_max')
            ]] :
            ['nickname' => 'required|max:255'];
        $data = $request->validate(array_merge([
            'user' => 'required',
            'password' => 'required',
            'qq' => 'required|Numeric|unique:users'
        ], $rule));
        $playerName = $request->input('player_name');
        $dispatcher->dispatch('auth.registration.attempt', [$data]);
        if (
            option('register_with_player_name') &&
            Player::where('name', $playerName)->count() > 0
        ) return back()->withErrors(['login' => trans('user.player.add.repeated')]);
        $whip = new Whip();
        $ip = $whip->getValidIpAddress();
        $ip = $filter->apply('client_ip', $ip);
        if (User::where('ip', $ip)->count() >= option('regs_per_ip')) return back()->withErrors(['login' => trans('auth.register.max', ['regs' => option('regs_per_ip')])]);
        $username = $data['user'];
        $eduroam = option('eduroam_domain', null) ? $username . '@' . option('eduroam_domain') : $username;
        if (User::where('eduroam', $eduroam)->first()) return back()->withErrors(['login' => trans('Blessing\\Eduroam::eduroam.auth.user_repeated')]);
        $response = Http::asForm()->post('https://eduroam.ustc.edu.cn/cgi-bin/eduroam-test.cgi', [
            'login' => $eduroam,
            'password' => $data['password']
        ]);
        if (strpos($response->body(), 'EAP Failure')) return back()->withErrors(['login' => trans('Blessing\\Eduroam::eduroam.auth.failure')]);
        elseif (strpos($response->body(), 'illegal')) return back()->withErrors(['login' => trans('Blessing\\Eduroam::eduroam.auth.illegal')]);
        elseif (strpos($response->body(), 'EAP Success')) {
            $dispatcher->dispatch('auth.registration.ready', [$data]);
            $user = new User();
            $user->email = $data['qq'] . '@qq.com';
            $user->nickname = $data[option('register_with_player_name') ? 'player_name' : 'nickname'];
            $user->score = option('user_initial_score');
            $user->avatar = 0;
            $password = app('cipher')->hash($data['password'], config('secure.salt'));
            $user->password = $filter->apply('user_password', $password);
            $user->ip = $ip;
            $user->permission = User::NORMAL;
            $user->register_at = Carbon::now();
            $user->last_sign_at = Carbon::now()->subDay();
            $user->eduroam = $eduroam;
            $user->qq = $data['qq'];
            $user->save();
            $dispatcher->dispatch('auth.registration.completed', [$user]);
            event(new Events\UserRegistered($user));
            if (option('register_with_player_name')) {
                $dispatcher->dispatch('player.adding', [$playerName, $user]);
                $player = new Player();
                $player->uid = $user->uid;
                $player->name = $playerName;
                $player->tid_skin = 0;
                $player->save();
                $dispatcher->dispatch('player.added', [$player, $user]);
                event(new Events\PlayerWasAdded($player));
            }
            $dispatcher->dispatch('auth.login.ready', [$user]);
            auth()->login($user);
            $dispatcher->dispatch('auth.login.succeeded', [$user]);
            return redirect('/user')->with('status', trans('auth.register.success'));
        } else return back()->withErrors(['login' => trans('Blessing\\Eduroam::eduroam.auth.unknown')]);
    }
}