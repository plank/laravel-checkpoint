<?php

namespace Plank\Checkpoint\Tests\Support\Models\Servers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Checkpoint\Concerns\HasRevisions;

class Environment extends Model
{
    use HasRevisions;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    public function subgroup()
    {
        return $this->belongsTo(Group::class, 'subgroup_id', 'id');
    }

    public function servers()
    {
        return $this->hasMany(Server::class, 'environment_id', 'id');
    }
}
