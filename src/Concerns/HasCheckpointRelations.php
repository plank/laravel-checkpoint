<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;

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
        $revision = config('checkpoint.revision_model');
        $checkpoint = config('checkpoint.checkpoint_model');
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
     * Return the checkpoints associated with all the revisions of this model
     * @return HasManyThrough
     */
    public function checkpoints(): HasManyThrough
    {
        $revision = config('checkpoint.revision_model');
        $checkpoint = config('checkpoint.checkpoint_model');
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
        return $this->morphOne(config('checkpoint.revision_model'), 'revisionable');
    }

    /**
     * Get all revisions representing this model
     *
     * @return MorphOneOrMany
     */
    public function revisions()
    {
        return $this->morphMany(
            config('checkpoint.revision_model'),
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
        return $this->hasOne(static::class, 'id', 'initial_id')->withoutRevisions();
    }

    /**
     * Get the model at the previous revision
     *
     * @return HasOne
     */
    public function older(): HasOne
    {
        return $this->hasOne(static::class, 'id', 'previous_id')->withoutRevisions();
    }

    /**
     * Get the model at the next revision
     *
     * @return HasOne
     */
    public function newer(): HasOne
    {
        return $this->hasOne(static::class, 'id', 'next_id')->withoutRevisions();
    }

}
