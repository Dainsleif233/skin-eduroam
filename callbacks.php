<?php

use Illuminate\Database\Schema\Blueprint;

return [
    App\Events\PluginWasEnabled::class => function () {
        if (!Schema::hasColumn('users', 'eduroam')) Schema::table('users', function (Blueprint $table) {
            $table->varchar('eduroam', 255)->nullable();
        });
        if (!Schema::hasColumn('users', 'qq')) Schema::table('users', function (Blueprint $table) {
            $table->varchar('qq', 255)->nullable();
        });
    }
];