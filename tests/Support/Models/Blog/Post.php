<?php

namespace Plank\Checkpoint\Tests\Support\Models\Blog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Checkpoint\Concerns\HasRevisions;

class Post extends Model
{
    use HasRevisions, SoftDeletes;

    protected $guarded = ['id'];

    protected $revisionMeta = ['excerpt'];

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
