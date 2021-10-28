<?php

namespace Plank\Checkpoint\Tests\Support\Models\Servers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Checkpoint\Concerns\HasRevisions;

class Cluster extends Model
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

    public function servers()
    {
        return $this->belongsToMany(Server::class)->withPivot(['default'])->withTimestamps();
    }
}
