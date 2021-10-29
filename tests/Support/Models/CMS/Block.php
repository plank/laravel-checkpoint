<?php

namespace Plank\Checkpoint\Tests\Support\Models\CMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Checkpoint\Concerns\HasRevisions;

class Block extends Model
{
    use HasRevisions;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $ignored = [
        'updated_by',
        'created_by',
        'deleted_by',
        'published_by',
    ];

    protected $casts = [
        'span' => 'integer',
        'content' => 'json',
    ];

    public const SPAN_OPTIONS = [
        '12' => "Full-width",
        '6' => "1/2",
        '0' => "1/2 centred",
        '4' => "1/3",
        '8' => "2/3"
    ];

    public function callout()
    {
        return $this->morphTo('calloutable');
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
