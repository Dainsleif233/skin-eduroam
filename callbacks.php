<?php

use Illuminate\Database\Schema\Blueprint;

return [
    App\Events\PluginWasEnabled::class => function () {
        option(['restricted-email-domains.allow' => '["qq.com"]']);
        if (!Schema::hasColumn('users', 'eduroam')) Schema::table('users', function (Blueprint $table) {
            $table->string('eduroam', 255)->nullable();
        });
        if (!Schema::hasTable('eduroam')) Schema::create('eduroam', function (Blueprint $table) {
            $table->string('eduroam', 255)->primary();
            $table->string('name', 255)->nullable();
            $table->string('qq')->nullable();
        });
    }
];