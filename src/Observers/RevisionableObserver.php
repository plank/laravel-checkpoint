<?php

namespace Plank\Checkpoint\Observers;

use Illuminate\Database\Eloquent\Model;

class RevisionableObserver
{
    /**
     * Handle the parent model "replicating" event.
     * Followed by saving, creating, created, saved events
     *
     * @param  $model
     * @return void
     */
    public function replicating($model) {
        //
    }

    /**
     * Handle the parent model "restoring" event.
     * Followed by saving & updating events
     *
     * @param  $model
     * @return void|bool
     */
    public function restoring($model)
    {
        $model->clearExcluded();
    }

    /**
     * Handle the parent model "restored" event.
     * Preceded by updated & saved events
     *
     * @param  $model
     * @return void
     */
    public function restored($model)
    {
        //
    }

    /**
     * Handle the parent model "saving" event.
     * Happens before either a creating or updating event
     *
     * @param  $model
     * @return void
     */
    public function saving(Model $model) {
        //
    }

    /**
     * Handle the parent model "saved" event.
     * Happens after either a created or updated event
     *
     * @param  $model
     * @return void
     */
    public function saved($model) {
        //
    }

    /**
     * Handle the parent model "creating" event.
     *
     * @param  $model
     * @return void
     */
    public function creating($model)
    {
        //
    }

    /**
     * Handle the parent model "created" event.
     *
     * @param  $model
     * @return void
     */
    public function created($model)
    {
        $model->startRevision();
    }

    /**
     * Handle the parent model "updating" event.
     *
     * @param  $model
     * @return void
     */
    public function updating($model)
    {
        // Check if any column is dirty and filter out the unwatched fields
        if(!empty(array_diff(array_keys($model->getDirty()), $model->getIgnored()))) {
            $model->performRevision();
        }
    }

    /**
     * Handle the parent model "updated" event.
     *
     * @param  $model
     * @return void
     */
    public function updated($model)
    {
        //
    }

    /**
     * Handle the parent model "deleting" event.
     *
     * @param  $model
     * @return void
     */
    public function deleting($model)
    {
        if (method_exists($model, 'bootSoftDeletes') && !$model->isForceDeleting()) {
            $model->clearExcluded();
            $model->performRevision();
            $model->syncChanges(); // copy over dirty values to changes, mimics natural laravel update
            $model->syncOriginal(); // clears dirty without triggering a db update, values are already up-to-date
        }
    }

    /**
     * Handle the parent model "deleted" event.
     *
     * @param  $model
     * @return void
     */
    public function deleted($model)
    {
        $revision = $model->revision;
        // skip cascading revision delete if we're soft deleting or the revision is missing
        if ($revision === null || (method_exists($model, 'bootSoftDeletes') && !$model->isForceDeleting())) {
            $model->syncChangedAttributes([$model->getDeletedAtColumn()]);
            return;
        }

        // cascade delete to revision
        $revision->delete();
        $model->unsetRelation('revision');
    }

    /**
     * Handle the parent model "forceDeleted" event.
     * Only fired if the model is using SoftDeletes
     *
     * @param  $model
     * @return void|bool
     */
    public function forceDeleted($model)
    {
        //
    }
}
