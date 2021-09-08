<?php

namespace Plank\Checkpoint\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection|Checkpoint[] $checkpoints
 */
class Timeline extends Model
{
    /**
     * Get all checkpoints associated with the Timeline
     *
     * @return HasMany
     */
    public function checkpoints(): HasMany
    {
        /** @var Checkpoint|string $checkpointClass */
        $checkpointClass = config('checkpoint.models.checkpoint');

        return $this->hasMany($checkpointClass, $checkpointClass::TIMELINE_ID);
    }
}