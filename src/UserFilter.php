<?php

namespace Blessing\Eduroam;

use Blessing\Rejection;
use Auth;

class UserFilter {
    public function filter($can, $action) {
        $user = Auth::user();
        if (option('require_verification') && $action === 'email' && !$user->verified) return new Rejection(trans('Blessing\\Eduroam::eduroam.filter.need_verified'));
        return $can;
    }
}