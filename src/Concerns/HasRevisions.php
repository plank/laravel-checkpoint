<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Builder;
use Exception;
use Closure;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
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

    public $metaColumns = [];

    public $unwatchedColumns = [];

    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootVersionable(): void
    {
        static::created(function (self $model) {
            $model->startVersioning();
        });

        static::updating(function (self $model) {
            $model->createNewVersion();
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


    public function checkpoint(): BelongsTo
    {
        return $this->revision()->firstOrFail()->checkpoint();
    }

    /**
     * Get all revision representing this model
     *
     * @return MorphOneOrMany
     */
    public function revisions(): MorphOneOrMany
    {
        //todo
        $model = config('checkpoint.revision_model', Revision::class);
        return $this->morphOne($model, 'revisionable', 'revisionable_type', 'original_revisionable_id');
    }

    /**
     * Filter by a release; gets all the versions of a model from a given release or earlier.
     * @param Builder $q
     * @param Version $v
     * @return Builder
     */
    public function scopeLatestSince(Builder $q, Version $v)
    {
        return $q
            ->whereHas('releases', function (Builder $query) use ($v) {
                $query->where('id', $v->id);
            })
            ->orWhere('created_at', '<=', $v->created_at)
            ->orderBy('created_at', 'desc');
    }

    /**
     *  each link to a release contains metadata that can be used to build a previous version of given model
     */
    public function createNewVersion()
    {
        // Make sure we preserve the original
        $version = $this->replicate();

        // TODO: Get latest target release
        $targetRelease = '';

        // Duplicate relationships as well - replicated doesn't do this
        foreach ($this->getRelations() as $relation => $item) {
            $version->setRelation($relation, $item);
        }
        // Store the new version
        $version->saveWithoutEvents();
        // Set our needed pivot data
        // TODO: get the pivot we are targeting, like in startVersion
        $pivot = '';
        $version->releases->pivot->shared_key = $this->pivot->shared_key;
        $version->releases->pivot->previousVersion()->associate($this);
        $version->releases->pivot->save();

        $this->fill($this->getOriginal());
        // Clear meta stored columns on original
        foreach ($this->metaColumns as $column) {
            $this->$column = null;
        }

    }

    public function startVersioning()
    {
        // Get latest version / release, attach this model to it
        $release = config('checkpoint.release_model', Version::class);
        $targetRelease = $release::orderBy('created_at', 'desc')->firstOrFail();
        $this->releases()->attach($targetRelease);
        // Init the shared key that will be pass through the generations of this model instance
        $pivot = $this->releases()->first()->pivot;
        $pivot->shared_key = Uuid::uuid4();
        $pivot->save();
    }

    /**
     * @return void
     */
    public function rollbackToVersion(Version $v): void
    {
        // Rollback & rewrite history
        // delete all new items until you hit version $v
    }

    /**
     * @return void
     */
    public function revertToVersion(Version $v): void
    {
        // move to old version touch to restore model state to create a new copy at that moment in time
    }

    /**
     * Remove all existing revisions from the database, belonging to a model instance.
     *
     * @return void
     * @throws Exception
     */
    public function deleteAllVersions(): void
    {
        $this->releases()->sync([]);
    }

    private function saveWithoutEvents(array $options = [])
    {
        return static::withoutEvents(function() use ($options) {
            return $this->save($options);
        });
    }

}
