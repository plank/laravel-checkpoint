<?php

namespace Plank\Checkpoint\Concerns;

use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Checkpoint;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
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
     * @return hasOneThrough
     */
    public function checkpoint(): hasOneThrough
    {
        $revision = config('checkpoint.revision_model', Revision::class);
        $checkpoint = config('checkpoint.checkpoint_model', Checkpoint::class);
        return $this->hasOneThrough(
            $checkpoint,
            $revision,
            'revisionable_id',
            'id',
            'id',
            'checkpoint_id'
        )->where('revisionable_type', self::class);
    }

    /**
     * Get all revisions representing this model
     *
     * @return MorphOneOrMany
     */
    public function revisions()
    {
        //todo
        //$model = config('checkpoint.revision_model', Revision::class);
        //return $this->morphMany($model, 'revisionable', 'revisionable_type', 'original_revisionable_id');
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
     * Get the model at the previous revision
     *
     * @return MorphTo
     */
    public function previous(): MorphTo
    {
        return $this->revision->previous->revisionable();
    }

    /**
     * Get the model at the next revision
     *
     * @return MorphTo
     */
    public function next(): MorphTo
    {
        return $this->revision->next->revisionable();
    }


    /**
     * Get the model at its original revision
     *
     * @return MorphTo
     */
    public function initialRevision(): MorphTo
    {
        return $this->revision->initial->revisionable();
    }

}
