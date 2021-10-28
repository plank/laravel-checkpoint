<?php

namespace Plank\Checkpoint\Tests\Support\Models\Blog;

use Illuminate\Database\Eloquent\Model;
use Plank\Checkpoint\Concerns\HasRevisions;

class Comment extends Model
{
    use HasRevisions;

    protected $guarded = ['id'];

    public function comments()
    {
        return $this->belongsTo(Post::class);
    }
}
