<?php

namespace Plank\Checkpoint\Observers;

use Illuminate\Database\Eloquent\Model;

class RevisionableObserver
{
    /**
     * Handle the parent model "created" event.
     *
     * @param Model $model
     * @return void
     */
    public function created(Model $model)
    {
        $model->startRevision();
    }

    /**
     * Handle the parent model "updating" event.
     *
     * @param Model $model
     * @return void
     */
    public function updating(Model $model)
    {
        // Check if any column is dirty and filter out the unwatched fields
        if(!empty(array_diff(array_keys($model->getDirty()), $model->getRevisionUnwatched()))) {
            $model->performRevision();
        }
    }

    /**
     * Handle the parent model "deleting" event.
     *
     * @param Model $model
     * @return void
     */
    public function deleting(Model $model)
    {
        if (method_exists($model, 'bootSoftDeletes') && !$model->isForceDeleting()) {
            $model->performRevision();
        }
    }

    /**
     * Handle the parent model "deleted" event.
     *
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model)
    {
        if (!method_exists($model, 'bootSoftDeletes') || $model->isForceDeleting()) {
            $revision = $model->revision;
            // if newer revision exists, point its previous_revision_id to the previous revision of this item
            if ($revision !== null && $revision->next()->exists()) {
                $revision->next->previous_revision_id = $revision->previous_revision_id;
                $revision->next->save();
            }
            $model->revision()->delete();
        }
    }
}
