<?php

namespace Blessing\Eduroam;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property String $eduroam
 * @property Array  $name
 * @property Array  $qq
 */
class Eduroam extends Model
{
    protected $table = 'eduroam';
    protected $primaryKey = 'eduroam';
    protected $keyType = 'string';
    protected $fillable = ['eduroam', 'name', 'qq'];
    protected $casts = [
        'name' => 'array',
        'qq' => 'array'
    ];
    public $timestamps = false;
    public $incrementing = false;

    /**
     * Find an eduroam record by a user model, null if the user is not eduroam-based.
     */
    public static function findByUser($user) {
        if (!$user || !$user->eduroam) return null;
        return static::where('eduroam', $user->eduroam)->first();
    }

    /**
     * Find an eduroam record by user uid, null if the user is not eduroam-based.
     */
    public static function findByUserUid($uid) {
        return static::findByUser(User::where('uid', $uid)->first());
    }

    /**
     * Merge $value into a JSON array column, de-duplicated.
     * Low-level helper used by the atomic append* methods.
     * Guards against NULL (nullable columns / pre-1.4.0 rows) so that
     * array_merge() never receives null on PHP 8.1+ (would be a TypeError).
     */
    protected function addValue(string $column, $value) {
        $this->{$column} = array_unique(array_merge((array) ($this->{$column} ?? []), [$value]));
        return $this;
    }

    /**
     * Atomically append a player name. The row is re-fetched under a lock
     * inside a transaction, so concurrent appends cannot lose entries from
     * the read-modify-write race. Refreshes this instance afterwards.
     */
    public function appendName($name) {
        return $this->appendValueLocked('name', $name);
    }

    public function appendQQ($qq) {
        return $this->appendValueLocked('qq', $qq);
    }

    protected function appendValueLocked(string $column, $value) {
        DB::transaction(function () use ($column, $value) {
            static::where($this->getKeyName(), $this->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->addValue($column, $value)
                ->save();
        });

        $this->refresh();

        return $this;
    }

    /**
     * Search eduroam records by a substring of one of: eduroam, qq, name.
     * - "eduroam" uses a cross-driver LIKE query.
     * - "qq" and "name" are JSON arrays; we filter in PHP with str_contains so
     *   the query stays portable across MySQL/MariaDB/PostgreSQL/SQLite (pgsql
     *   rejects implicit text->json casts, and JSON containment syntax differs
     *   per driver).
     *
     * @return \Illuminate\Support\Collection
     */
    public static function search(string $field, string $keyword): \Illuminate\Support\Collection {
        if ($field === 'eduroam') {
            // Case-insensitive substring match. LIKE is case-sensitive on
            // PostgreSQL but not on MySQL/SQLite, so we lowercase both sides
            // with the standard SQL lower(), which works across all four
            // drivers. The placeholder is still bound, so no injection risk.
            return static::whereRaw('lower(eduroam) like ?', ['%' . mb_strtolower($keyword) . '%'])->get();
        }

        if (!in_array($field, ['qq', 'name'], true)) {
            return collect();
        }

        return static::all()->filter(function ($record) use ($field, $keyword) {
            foreach ((array) ($record->{$field} ?? []) as $value) {
                if (str_contains((string) $value, $keyword)) {
                    return true;
                }
            }
            return false;
        });
    }
}