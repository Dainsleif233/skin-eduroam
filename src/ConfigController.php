<?php

namespace Blessing\Eduroam;

use App\Models\User;
use App\Services\OptionForm;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Option;

class ConfigController extends Controller {
    public function render(Request $request) {
        $results = null;
        $query = null;

        if ($request->filled('q_field') && $request->filled('q_keyword')) {
            $field = $request->input('q_field');
            $keyword = trim((string) $request->input('q_keyword'));
            $query = ['field' => $field, 'keyword' => $keyword];

            // Batch-fetch emails to avoid an N+1 query per result row.
            $records = Eduroam::search($field, $keyword);
            $emails = User::whereIn('eduroam', $records->pluck('eduroam')->all())
                ->pluck('email', 'eduroam')
                ->all();

            $results = $records->map(function ($record) use ($emails) {
                return [
                    'eduroam' => $record->eduroam,
                    'qq' => (array) ($record->qq ?? []),
                    'name' => (array) ($record->name ?? []),
                    'email' => $emails[$record->eduroam] ?? null,
                ];
            });
        }

        $configForm = Option::form('config', trans('Blessing\\Eduroam::eduroam.config.config'), function (OptionForm $form) {
            $form->text('username_regex', trans('Blessing\\Eduroam::eduroam.config.username_regex'))->description('/^[0-9]+$/');
            $form->text('eduroam_suffix', trans('Blessing\\Eduroam::eduroam.config.eduroam_suffix'))->hint(trans('Blessing\\Eduroam::eduroam.config.eduroam_suffix-hint'));
            $form->text('eduroam_api', trans('Blessing\\Eduroam::eduroam.config.eduroam_api'))->description(trans('Blessing\\Eduroam::eduroam.config.eduroam_api-description'));
            $form->text('backup_eduroam_suffix', trans('Blessing\\Eduroam::eduroam.config.backup_eduroam_suffix'))->hint(trans('Blessing\\Eduroam::eduroam.config.backup_eduroam_suffix-hint'));
            $form->text('backup_eduroam_api', trans('Blessing\\Eduroam::eduroam.config.backup_eduroam_api'))->description(trans('Blessing\\Eduroam::eduroam.config.backup_eduroam_api-description'));
        })->handle();
        return view('Blessing\Eduroam::config', [
            'config' => $configForm,
            'results' => $results,
            'query' => $query,
        ]);
    }
}