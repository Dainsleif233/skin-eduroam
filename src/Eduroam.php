<?php

namespace Blessing\Eduroam;

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