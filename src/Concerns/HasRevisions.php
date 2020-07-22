<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Builder;
use Exception;
use Closure;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Ramsey\Uuid\Uuid;

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
        static::created(function (self $model) {
            $model->startRevisioning();
        });

        static::updating(function (self $model) {
            $model->makeRevision();
        });

        static::deleted(function (self $model) {
            if ($model->forceDeleting !== false) {
                $model->deleteAllVersions();
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
     * @return BelongsTo
     */
    public function checkpoint(): BelongsTo
    {
        return $this->revision->checkpoint();
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
    public function scopeLatestBefore(Builder $q, Checkpoint $c)
    {
        $previousCheckpoints = Checkpoint::where('checkpoint_date', '<=' ,$c->checkpoint_date)->pluck('id');
        return $q
            ->whereHas('revision', function (Builder $query) use ($previousCheckpoints) {
                $query->whereIn('checkpoint_id', $previousCheckpoints);
            })
//            ->whereHas('revision', function (Builder $query) {
//                $query->where('latest', 1);
//            })
            ->orderBy('created_at', 'desc');
    }

    /**
     *  each link to a release contains metadata that can be used to build a previous version of given model
     */
    public function makeRevision()
    {
        // Make sure we preserve the original
        $version = $this->replicate();

        // Duplicate relationships as well - but make sure we attach only the latest version of something
//        foreach ($this->getRelations() as $relation => $item) {
//            $version->setRelation($relation, $item/*->latestBefore()*/);
//        }
        // Store the new version
        $version->saveWithoutEvents();

        $revisonModel = config('checkpoint.revision_model', Revision::class);
        $revision = new $revisonModel;

        // Set our needed "pivot" data
        $revision->revisionable()->associate($version);
        $revision->original_revisionable_id = $this->revision->original_revisionable_id;
        $revision->previous()->associate($this);
        $this->fill($this->getOriginal());
        $this->handleMeta();

        $revision->save();


    }

    public function startRevisioning()
    {
        $revisonModel = config('checkpoint.revision_model', Revision::class);
        $revision = new $revisonModel;
        $revision->revisionable()->associate($this);
        $revision->original_revisionable_id = $this->id;

        $revision->save();
    }

    /**
     * @return void
     */
    public function rollbackToVersion(Checkpoint $v): void
    {
        // Rollback & rewrite history
        // delete all new items until you hit version $v
    }

    /**
     * @return void
     */
    public function revertToVersion(Checkpoint $v): void
    {
        // move to old version touch to restore model state to create a new copy at that moment in time
    }

    /**
     * Remove all existing revisions from the database, belonging to a model instance.
     *
     * @return void
     * @throws Exception
     */
    public function deleteRevision(): void
    {
        $this->revisions()->sync([]);
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
