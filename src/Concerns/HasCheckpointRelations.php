<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Timeline;

/**
 * @mixin HasRevisions
 */
trait HasCheckpointRelations
{
    /**
     * Return the checkpoint this model belongs to
     * @return HasOneThrough
     */
    public function checkpoint(): HasOneThrough
    {
        /** @var Revision $revision */
        $revision = config('checkpoint.models.revision');

        /** @var Checkpoint $checkpoint */
        $checkpoint = config('checkpoint.models.checkpoint');

        return $this->hasOneThrough(
            $checkpoint,
            $revision,
            'revisionable_id',
            $checkpoint::getModel()->getKeyName(),
            $this->getKeyName(),
            $revision::CHECKPOINT_ID
        )->where('revisionable_type', static::class);
    }

    /**
     * Return the checkpoint this model belongs to
     * @return HasOneThrough
     */
    public function timeline(): HasOneThrough
    {
        /** @var Revision $revision */
        $revision = config('checkpoint.models.revision');

        /** @var Timeline $timeline */
        $timeline = config('checkpoint.models.timeline');

        return $this->hasOneThrough(
            $timeline,
            $revision,
            'revisionable_id',
            $timeline::getModel()->getKeyName(),
            $this->getKeyName(),
            $revision::TIMELINE_ID
        )->where('revisionable_type', static::class);
    }

    /**
     * Return the checkpoints associated with all the revisions of this model
     * @return HasManyThrough
     */
    public function checkpoints(): HasManyThrough
    {
        $revision = config('checkpoint.models.revision');
        $checkpoint = config('checkpoint.models.checkpoint');
        return $this->hasManyThrough(
            $checkpoint,
            $revision,
            'original_revisionable_id',
            $checkpoint::getModel()->getKeyName(),
            'initial_id',
            $revision::CHECKPOINT_ID
        )->where('revisionable_type', static::class);
    }

    /**
     * Get the revision representing this model
     *
     * @return MorphOne
     */
    public function revision(): MorphOne
    {
        return $this->morphOne(config('checkpoint.models.revision'), 'revisionable');
    }

    /**
     * Get all revisions representing this model
     *
     * @return MorphOneOrMany
     */
    public function revisions()
    {
        return $this->morphMany(
            config('checkpoint.models.revision'),
            'revisionable',
            'revisionable_type',
            'original_revisionable_id',
            'initial_id'
        );
    }

    /**
     * Get the model at its initial revision
     *
     * @return HasOne
     */
    public function initial(): HasOne
    {
        return $this->hasOne(static::class, $this->getKeyName(), 'initial_id')->withoutRevisions();
    }

    /**
     * Get the model at the previous revision
     *
     * @return HasOne
     */
    public function older(): HasOne
    {
        return $this->hasOne(static::class, $this->getKeyName(), 'previous_id')->withoutRevisions();
    }

    /**
     * Get the model at the next revision
     *
     * @return HasOne
     */
    public function newer(): HasOne
    {
        return $this->hasOne(static::class, $this->getKeyName(), 'next_id')->withoutRevisions();
    }

    /**
     * Get the model at its recent revision
     *
     * @return HasOne
     */
    public function newest(): HasOne
    {
        return $this->hasOne(static::class, $this->getKeyName(), 'newest_id')->withoutRevisions();
    }

}
