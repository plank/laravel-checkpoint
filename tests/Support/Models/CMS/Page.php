<?php

namespace Plank\Checkpoint\Tests\Support\Models\CMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Checkpoint\Concerns\HasRevisions;

class Page extends Model
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

    public function blocks()
    {
        return $this->hasMany(Block::class)->orderBy('position');
    }

    public function calledOutBy()
    {
        return $this->morphOne(Block::class, 'calloutable');
    }
}
