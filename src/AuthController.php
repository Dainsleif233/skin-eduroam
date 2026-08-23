<?php

namespace Blessing\Eduroam;

use App\Events;
use App\Models\Player;
use App\Models\User;
use App\Rules;
use Blessing\Filter;
use Blessing\Rejection;
use Carbon\Carbon;
use Exception;
use Http;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Vectorface\Whip\Whip;

class AuthController extends Controller {
    public function eduroam() {
        $register_with = option('register_with_player_name', null) ? 'Blessing\Eduroam::rows.player_name' : 'Blessing\Eduroam::rows.nickname';
        $value = [
            'site_name' => option_localized('site_name'),
            'register_with' => $register_with,
            // 与核心约定一致：经 #blessing-extra 传给前端，读作 blessing.extra.*
            'extra' => [
                'recaptcha' => option('recaptcha_sitekey'),
                'invisible' => (bool) option('recaptcha_invisible'),
            ],
        ];
        return view('Blessing\Eduroam::eduroam', $value);
    }
    public function handleEduroam(Request $request, Rules\Captcha $captcha, Dispatcher $dispatcher, Filter $filter) {
        $can = $filter->apply('can_register', null);
        if ($can instanceof Rejection) return json($can->getReason(), 1);
        $rule = option('register_with_player_name') ?
            ['player_name' => [
                'required',
                new Rules\PlayerName(),
                'min:'.option('player_name_length_min'),
                'max:'.option('player_name_length_max')
            ]] :
            ['nickname' => 'required|max:255'];
        $data = $request->validate(array_merge([
            'user' => ['required', 'regex:'.option('username_regex', '//')],
            'password' => ['required'],
            'qq' => ['required', 'Numeric'],
            'captcha' => ['required', $captcha]
        ], $rule));
        $playerName = $request->input('player_name');
        $dispatcher->dispatch('auth.registration.attempt', [$data]);
        if (
            option('register_with_player_name') &&
            Player::where('name', $playerName)->exists()
        ) return json(trans('user.player.add.repeated'), 1);
        $whip = new Whip();
        $ip = $whip->getValidIpAddress();
        $ip = $filter->apply('client_ip', $ip);
        if (User::where('ip', $ip)->count() >= option('regs_per_ip')) return json(trans('auth.register.max', ['regs' => option('regs_per_ip')]), 1);
        if (User::where('email', $data['qq'] . '@qq.com')->first())
            return json(trans('Blessing\\Eduroam::eduroam.auth.qq_repeated'), 1);

        // 验证用户名和密码
        $username = $data['user'];
        $auth_result = $this->auth($username, $data['password']);
        if ($auth_result['code'] == 0)
            return $this->register($dispatcher, $data, $ip, $auth_result['eduroam_user'], $playerName, $request, $filter);
        elseif ($auth_result['code'] == 1 || !option('backup_eduroam_api'))
            return json($auth_result['message'], 1);

        // 备份验证
        $backup_auth_result = $this->auth_backup($username, $data['password']);
        if ($backup_auth_result['code'] == 0)
            return $this->register($dispatcher, $data, $ip, $auth_result['eduroam_user'], $playerName, $request, $filter);
        return json($backup_auth_result['message'], 1);
    }

    private function auth($username, $password) {
        $login =  $username . option('eduroam_suffix', '');
        if (User::where('eduroam', $login)->first())
            return [
                'eduroam_user' => $login,
                'message' => trans('Blessing\\Eduroam::eduroam.auth.user_repeated'),
                'code' => 1
            ];
        try {
            $response = Http::acceptJson()->asJson()->post(option('eduroam_api', 'https://eduroam.seesea.site/api/auth/test'), [
                'login' => $login,
                'password' => $password
            ]);
            $result = $response->json();
        } catch (Exception $e) {
            Log::error('Eduroam API failed: '.$e->getMessage());
            return [
                'eduroam_user' => $login,
                'message' => trans('Blessing\\Eduroam::eduroam.auth.api_failure'),
                'code' => 3
            ];
        }
        return $this->parseAuthResponse($result, $login);
    }

    private function auth_backup($username, $password) {
        $login = $username . option('backup_eduroam_suffix', '');
        try {
            $response = Http::acceptJson()->asJson()->post(option('backup_eduroam_api'), [
                'login' => $login,
                'password' => $password
            ]);
            $result = $response->json();
        } catch (Exception $e) {
            Log::error('Backup Eduroam API failed: '.$e->getMessage());
            return [
                'eduroam_user' => $login,
                'message' => trans('Blessing\\Eduroam::eduroam.auth.api_failure'),
                'code' => 3
            ];
        }
        return $this->parseAuthResponse($result, $login);
    }

    private function parseAuthResponse($result, $login) {
        $results = $result['results'] ?? [];
        // 有一个方法成功即认证成功
        foreach ($results as $item) {
            if (($item['success'] ?? false) === true)
                return [
                    'eduroam_user' => $login,
                    'code' => 0
                ];
        }
        // 全部失败，拼接 output 检测关键词判断错误类型
        $output = '';
        foreach ($results as $item)
            $output .= ($item['output'] ?? '') . "\n";
        if (strpos($output, 'EAP Failure') !== false)
            return [
                'eduroam_user' => $login,
                'message' => trans('Blessing\\Eduroam::eduroam.auth.failure'),
                'code' => 1
            ];
        elseif (strpos($output, 'illegal') !== false)
            return [
                'eduroam_user' => $login,
                'message' => trans('Blessing\\Eduroam::eduroam.auth.illegal'),
                'code' => 2
            ];
        else
            return [
                'eduroam_user' => $login,
                'message' => trans('Blessing\\Eduroam::eduroam.auth.unknown'),
                'code' => 3
            ];
    }

    private function register($dispatcher, $data, $ip, $eduroam_user, $playerName, $request, $filter) {
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
        $user->eduroam = $eduroam_user;
        $eduroam = Eduroam::firstOrCreate(
            ['eduroam' => $eduroam_user],
            ['name' => [], 'qq' => []]
        );
        $eduroam->addQQ($data['qq'])->save();
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
        return json(trans('auth.login.success'), 0, ['redirectTo' => $request->session()->pull('last_requested_path', url('/user'))]);
    }
}