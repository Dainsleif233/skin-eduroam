<?php

namespace Blessing\Eduroam;

use App\Services\OptionForm;
use Illuminate\Routing\Controller;
use Option;

class ConfigController extends Controller {
    public function render() {
        $configForm = Option::form('config', trans('Blessing\\Eduroam::eduroam.config.config'), function (OptionForm $form) {
            $form->text('eduroam_domain', trans('Blessing\\Eduroam::eduroam.config.eduroam_domain'))->hint(trans('Blessing\\Eduroam::eduroam.config.eduroam_domain-hint'));
        })->handle();
        return view('Blessing\Eduroam::config', ['config' => $configForm]);
    }
}