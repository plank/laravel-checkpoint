<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Plank\Checkpoint\Scopes\CheckpointScope;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Exception;
use Closure;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Illuminate\Database\Eloquent\Builder;

/**
 * @package Plank\Versionable\Concerns
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasRevisions
{
    use StoresMeta;

    public $unwatchedColumns = [];

    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasRevisions(): void
    {
        static::addGlobalScope(new CheckpointScope());

        // hook newVersion onto all relevent events
        // On Create, Update, Delete, Restore : make new revisions...

        static::created(function (self $model) {
            $model->startRevisioning();
        });

        static::updating(function (self $model) {
            $model->makeRevision();
        });

        static::deleted(function (self $model) {
            if ($model->forceDeleting !== false) {
                $model->deleteAllVersions();
            } else {
                $model->makeRevision();
            }
        });
    }

    /**
     * Register a versioning model event with the dispatcher.
     *
     * @param Closure|string $callback
     * @return void
     */
    public static function versioning($callback): void
    {
        static::registerModelEvent('versioning', $callback);
    }

    /**
     * Register a versioned model event with the dispatcher.
     *
     * @param Closure|string $callback
     * @return void
     */
    public static function versioned($callback): void
    {
        static::registerModelEvent('versioned', $callback);
    }

    //////// START RELATIONS

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
    public function revisions(): MorphOneOrMany
    {
        //todo
        $model = config('checkpoint.revision_model', Revision::class);
        return $this->morphMany($model, 'revisionable', 'revisionable_type', 'original_revisionable_id');
    }


    /**
     * Return the checkpoint that this model belongs to
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
        )
            ->where('revisionable_type', self::class);
    }


    /**
     * Get the previous instance of this model
     *
     * @return MorphTo
     */
    public function previous(): MorphTo
    {
        return $this->revision->previous->revisionable();
    }

    /**
     * Get the next instance of this model
     *
     * @return MorphTo
     */
    public function next(): MorphTo
    {
        return $this->revision->next->revisionable();
    }

    public function getOriginalIdAttribute()
    {
        return $this->revision->original_revisionable_id;
    }

    /**
     * Filter by a release; gets all the versions of a model from a given release or earlier.
     * @param Builder $q
     * @param Checkpoint $v
     * @return Builder
     */
    public function scopeLatestBefore(Builder $q, Checkpoint $c = null)
    {
        if ($c) {
            $previousCheckpoints = Checkpoint::where('checkpoint_date', '<=' ,$c->checkpoint_date)->pluck('id');
            return $q
                ->whereHas('revision', function (Builder $query) use ($previousCheckpoints) {
                    $query
                        // or do a select before instead of whereRaw?
                        ->whereRaw('`revisions`.`created_at` in (SELECT DISTINCT max(created_at) FROM revisions GROUP BY original_revisionable_id)')
                        ->whereIn('checkpoint_id', $previousCheckpoints);
                });
        } else {
            return $q
                ->whereHas('revision', function (Builder $query) {
                    $query->where('latest', true);
                });
        }
    }

    /**
     *  each link to a release contains metadata that can be used to build a previous version of given model
     */
    public function makeRevision()
    {
        // Make sure we preserve the original
        $newItem = $this->replicate();

        // Duplicate relationships as well - but make sure we attach only the latest version of something
//        foreach ($this->getRelations() as $relation => $item) {
//            $version->setRelation($relation, $item/*->latestBefore()*/);
//        }

        // Store the new version
        $newItem->saveWithoutEvents();
        $this->fill($this->getOriginal());



        // Set our needed "pivot" data
        $model = config('checkpoint.revision_model', Revision::class);
        $newRevision = new $model;
        $newRevision->revisionable()->associate($newItem);
        $newRevision->original_revisionable_id = $this->revision->original_revisionable_id;
        if ($this->revision()->exists()) {
            $newRevision->previous()->associate($this->revision()->get());
        }
        $this->fill($this->getOriginal());
        $this->handleMeta();

        $newRevision->save();


    }

    public function startRevisioning()
    {
        $model = config('checkpoint.revision_model', Revision::class);
        $revision = new $model;
        $revision->revisionable()->associate($this);
        $revision->original_revisionable_id = $this->id;

        $revision->save();
    }

    /**
     * @return void
     */
    public function rollbackToRevision(Revision $revision): void
    {
        // Rollback & rewrite history
        // delete all revisions & items until you hit version $revision
    }

    /**
     * @return void
     */
    public function revertToRevision(Revision $revision): void
    {
        // move to old revision touch to restore model state to create a new copy at that moment in time
    }

    /**
     * Remove all existing revisions from the database, belonging to a model instance.
     *
     * @return void
     * @throws Exception
     */
    public function deleteAllRevisions(): void
    {

    }

    /**
     * Fire a save event for model, without triggering observers / events
     * @param array $options
     * @return mixed
     */
    private function saveWithoutEvents(array $options = [])
    {
        return static::withoutEvents(function() use ($options) {
            return $this->save($options);
        });
    }

}
