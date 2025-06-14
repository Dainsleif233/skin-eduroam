<?php

use Illuminate\Database\Schema\Blueprint;

return [
    App\Events\PluginWasEnabled::class => function () {
        option(['restricted-email-domains.allow' => '["qq.com"]']);
        option(['register_with_player_name' => 'true']);
        if (!Schema::hasColumn('users', 'eduroam')) Schema::table('users', function (Blueprint $table) {
            $table->varchar('eduroam', 255)->nullable();
        });
    }
];