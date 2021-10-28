<?php

namespace Plank\Checkpoint\Tests\Support\Models\Servers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Checkpoint\Concerns\HasRevisions;

class Group extends Model
{
    use HasRevisions;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function parent()
    {
        return $this->belongsTo(self::class);
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function environments()
    {
        return $this->hasMany(Environment::class, 'group_id');
    }

    public function subEnvironments()
    {
        return $this->hasMany(Environment::class, 'subgroup_id');
    }

    public function servers()
    {
        return $this->hasManyThrough(
            Server::class,
            Environment::class,
            'group_id',
            'environment_id'
        );
    }

    public function subservers()
    {
        return $this->hasManyThrough(
            Server::class,
            Environment::class,
            'subgroup_id',
            'environment_id'
        );
    }
}
