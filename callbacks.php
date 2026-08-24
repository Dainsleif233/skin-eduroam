<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return [
    App\Events\PluginWasEnabled::class => function () {
        option(['restricted-email-domains.allow' => '["qq.com"]']);

        // ---------------------------------------------------------------------
        // Extend the core `users` table with an eduroam identity column and a
        // unique index so the database itself prevents duplicate registrations
        // (closing the concurrency race in AuthController::register).
        // ---------------------------------------------------------------------
        if (!Schema::hasColumn('users', 'eduroam')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('eduroam', 255)->nullable();
            });
        }

        // De-duplicate any pre-existing non-null eduroam values before adding
        // the unique index. Keep the earliest record (lowest uid) and null the
        // rest, so the index can be created without a "duplicate entry" error.
        // Only matters for installs hit by the old race condition.
        $duplicates = DB::table('users')
            ->whereNotNull('eduroam')
            ->select('eduroam', DB::raw('COUNT(*) as cnt'))
            ->groupBy('eduroam')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('eduroam');

        foreach ($duplicates as $value) {
            $keepUid = DB::table('users')
                ->where('eduroam', $value)
                ->orderBy('uid')
                ->limit(1)
                ->value('uid');
            DB::table('users')
                ->where('eduroam', $value)
                ->where('uid', '<>', $keepUid)
                ->update(['eduroam' => null]);
            Log::warning("Eduroam: de-duplicated users.eduroam value '{$value}' before adding unique index.");
        }

        // Add the unique index idempotently. If it already exists the database
        // reports "already exists" / "duplicate key name", which we ignore.
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('eduroam', 'users_eduroam_unique');
            });
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'already exists') || str_contains($message, 'duplicate key name')) {
                // Index already present — nothing to do.
            } else {
                throw $e;
            }
        }

        // ---------------------------------------------------------------------
        // Eduroam table: store the player names / QQ numbers associated with an
        // eduroam identity. These columns hold JSON-encoded arrays, so use the
        // driver-native JSON type via Laravel's schema builder:
        //   MySQL >= 5.7   -> native JSON
        //   MariaDB >=10.2 -> JSON (LONGTEXT alias + json_valid CHECK)
        //   PostgreSQL     -> native json
        //   SQLite         -> TEXT (mapped internally by Laravel)
        // ---------------------------------------------------------------------
        if (!Schema::hasTable('eduroam')) {
            Schema::create('eduroam', function (Blueprint $table) {
                $table->string('eduroam', 255)->primary();
                $table->json('name')->nullable();
                $table->json('qq')->nullable();
            });
        } else {
            // Migrate the old VARCHAR(255)/TEXT columns to the native JSON type
            // for existing installs. Existing data is already valid JSON text.
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                // PostgreSQL refuses an implicit varchar/text -> json cast and
                // aborts the ALTER, so it needs an explicit USING clause.
                // Column names below are a fixed whitelist, never user input.
                foreach (['name', 'qq'] as $column) {
                    $current = DB::selectOne(
                        'SELECT data_type FROM information_schema.columns
                         WHERE table_name = ? AND column_name = ?',
                        ['eduroam', $column]
                    );
                    $type = $current ? strtolower($current->data_type) : '';

                    // Already migrated (re-enabling the plugin) — skip, since the
                    // blank-value fix-up below cannot run against a json column.
                    if (in_array($type, ['json', 'jsonb'], true)) {
                        continue;
                    }

                    // Empty/whitespace strings are not valid JSON and would abort
                    // the cast for the whole table. NULLs cast fine, so leave them.
                    DB::table('eduroam')
                        ->whereNotNull($column)
                        ->whereRaw("btrim({$column}) = ''")
                        ->update([$column => '[]']);

                    DB::statement(
                        "ALTER TABLE eduroam ALTER COLUMN {$column} TYPE json USING {$column}::json"
                    );
                }
            } else {
                // MySQL / MariaDB / SQLite handle this through dbal's change().
                Schema::table('eduroam', function (Blueprint $table) {
                    $table->json('name')->nullable()->change();
                    $table->json('qq')->nullable()->change();
                });
            }
        }
    }
];
