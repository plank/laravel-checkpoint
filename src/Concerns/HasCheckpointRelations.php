<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Checkpoint;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;

/**
 * @package Plank\Versionable\Concerns
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasCheckpointRelations
{
    /**
     * Return the checkpoint this model belongs to
     * @return HasOneThrough
     */
    public function checkpoint(): HasOneThrough
    {
        $revision = config('checkpoint.revision_model', Revision::class);
        $checkpoint = config('checkpoint.checkpoint_model', Checkpoint::class);
        return $this->hasOneThrough(
            $checkpoint,
            $revision,
            'revisionable_id',
            (new $checkpoint)->getKeyName(),
            (new $revision)->getKeyName(),
            $revision::CHECKPOINT_ID
        )->where('revisionable_type', self::class);
    }

    /**
     * Return the checkpoints associated with all the revisions of this model
     * @return HasManyThrough
     */
    public function checkpoints()
    {
        $revision = config('checkpoint.revision_model', Revision::class);
        $checkpoint = config('checkpoint.checkpoint_model', Checkpoint::class);
        return $this->hasManyThrough(
            $checkpoint,
            $revision,
            'original_revisionable_id',
            (new $checkpoint)->getKeyName(),
            'first_revision_id',
            $revision::CHECKPOINT_ID
        )->where('revisionable_type', self::class);
    }

    /**
     * Get the revision representing this model
     *
     * @return MorphOne
     */
    public function revision(): MorphOne
    {
        $model = config('checkpoint.revision_model', Revision::class);
        return $this->morphOne($model, 'revisionable');
    }

    /**
     * Get all revisions representing this model
     *
     * @return MorphOneOrMany
     */
    public function revisions()
    {
        return $this->morphMany(config('checkpoint.revision_model', Revision::class),
            'revisionable', 'revisionable_type', 'original_revisionable_id', 'first_revision_id');
    }

    /**
     * Get the model at its first revision
     *
     * @return HasOne
     */
    public function initial(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'first_revision_id')->withoutRevisions();
    }

    /**
     * Get the model at the previous revision
     *
     * @return HasOne
     */
    public function older(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'previous_revision_id')->withoutRevisions();
    }

    /**
     * Get the model at the next revision
     *
     * @return MorphTo
     */
    public function newer(): MorphTo
    {
        return $this->revision->next->revisionable();
    }

}
