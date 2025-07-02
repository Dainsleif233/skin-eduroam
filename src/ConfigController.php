<?php

namespace Blessing\Eduroam;

use App\Services\OptionForm;
use Illuminate\Routing\Controller;
use Option;

class ConfigController extends Controller {
    public function render() {
        $configForm = Option::form('config', trans('Blessing\\Eduroam::eduroam.config.config'), function (OptionForm $form) {
            $form->text('username_regex', trans('Blessing\\Eduroam::eduroam.config.username_regex'))->value('/^[0-9]+$/');
            $form->text('eduroam_domain', trans('Blessing\\Eduroam::eduroam.config.eduroam_domain'))->hint(trans('Blessing\\Eduroam::eduroam.config.eduroam_domain-hint'));
            $form->text('eduroam_api', trans('Blessing\\Eduroam::eduroam.config.eduroam_api'))->description(trans('Blessing\\Eduroam::eduroam.config.eduroam_api-description'));
            $form->text('backup_eduroam_domain', trans('Blessing\\Eduroam::eduroam.config.backup_eduroam_domain'))->hint(trans('Blessing\\Eduroam::eduroam.config.eduroam_domain-hint'));
            $form->text('backup_eduroam_api', trans('Blessing\\Eduroam::eduroam.config.backup_eduroam_api'))->description(trans('Blessing\\Eduroam::eduroam.config.backup_eduroam_api-description'));
            $form->checkbox('replace', trans('Blessing\\Eduroam::eduroam.config.replace'))->label(trans('Blessing\\Eduroam::eduroam.config.replace-label'))->value(true)->disabled();
            $form->checkbox('prevent_edit_email', trans('Blessing\\Eduroam::eduroam.config.prevent_edit_email'))->label(trans('Blessing\\Eduroam::eduroam.config.prevent_edit_email-label'))->value(false)->disabled();
        })->handle();
        return view('Blessing\Eduroam::config', ['config' => $configForm]);
    }
}