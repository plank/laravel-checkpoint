<?php
namespace Plank\Versionable\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Versionable extends MorphPivot
{
    protected $table = 'versionables';

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo('versionable', 'previous_version_id', 'id');
    }

}
