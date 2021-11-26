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
        return $this->hasMany(
            get_class(app(Checkpoint::class)),
            app(Checkpoint::class)->getTimelineKeyName()
        );
    }

    /**
     * Get all checkpoints associated with the Timeline
     *
     * @return HasMany
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(
            get_class(app(Revision::class)),
            app(Revision::class)->getTimelineKeyName()
        );
    }
}
