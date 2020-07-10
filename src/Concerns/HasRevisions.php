<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * @param Checkpoint $v
     * @return Builder
     */
    public function scopeLatestSince(Builder $q, Checkpoint $v)
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

        // Duplicate relationships as well - replicate doesn't do this
        foreach ($this->getRelations() as $relation => $item) {
            $version->setRelation($relation, $item);
        }
        // Store the new version
        $version->saveWithoutEvents();

        $revisonModel = config('checkpoint.revision_model', Revision::class);
        $revision = new $revisonModel;

        // Set our needed "pivot" data
        $revision->revisionable()->associate($version);
        $revision->original_revisionable_id = $this->revision->original_revisionable_id;
        $revision->previous()->associate($this);
        $this->handleMeta();
        $revision->save();

        $this->fill($this->getOriginal());

    }

    public function startVersioning()
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

    // TODO: Dynamic mutator to resolve a field from the meta column if you pull an older revisions and it is null

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
