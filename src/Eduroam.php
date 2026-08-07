<?php

namespace Blessing\Eduroam;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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

    public function addName($name) {
        $newNames = array_unique(array_merge($this->name, [$name]));
        $this->name = $newNames;
        return $this;
    }

    public function addQQ($qq) {
        $newQQs = array_unique(array_merge($this->qq, [$qq]));
        $this->qq = $newQQs;
        return $this;
    }
}