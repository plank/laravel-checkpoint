<?php

namespace Plank\Checkpoint\Observers;

use Illuminate\Database\Eloquent\Model;

class RevisionableObserver
{

    /**
     * Handle the parent model "saving" event.
     * Happens on both creating & updating
     *
     * @param  $model
     * @return void
     */
    public function saving(Model $model) {
        //
    }

    /**
     * Handle the parent model "saved" event.
     * Happens on both created & updated
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
        $model->updateOrCreateRevision();
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
        if(!empty(array_diff(array_keys($model->getDirty()), $model->getRevisionUnwatched()))) {
            $model->saveAsRevision();
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
        if (method_exists($model, 'bootSoftDeletes')) {
            $model->saveAsRevision();
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
        if (!method_exists($model, 'bootSoftDeletes') || $model->forceDeleting === true) {
            $model->revision()->delete();
        }
    }
    
    /**
     * Handle the parent model "restoring" event.
     *
     * @param  $model
     * @return void|bool
     */
    public function restoring($model)
    {
        //
    }

    /**
     * Handle the parent model "restored" event.
     *
     * @param  $model
     * @return void
     */
    public function restored($model)
    {
        //
    }

    /**
     * Handle the parent model "force deleted" event.
     *
     * @param  $model
     * @return void
     */
    public function forceDeleted($model)
    {
        //
    }
}
