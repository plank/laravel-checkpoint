<?php

namespace Plank\Checkpoint\Tests\Support\Models\Servers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Checkpoint\Concerns\HasRevisions;
use Plank\Checkpoint\Tests\Support\Models\CMS\Block;

class Server extends Model
{
    use HasRevisions;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }

    public function clusters()
    {
        return $this->belongsToMany(Cluster::class)->withPivot(['default'])->withTimestamps();
    }

    public function group()
    {
        return $this->hasOneThrough(
            Group::class,
            Environment::class,
            'id',
            'id',
            'environment_id',
            'group_id'
        );
    }

    public function subgroup()
    {
        return $this->hasOneThrough(
            Group::class,
            Environment::class,
            'id',
            'id',
            'environment_id',
            'subgroup_id'
        );
    }

    public function calledOutBy()
    {
        return $this->morphOne(Block::class, 'calloutable');
    }
}
