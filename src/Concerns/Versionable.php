<?php

namespace Plank\Versionable\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Plank\Versionable\Models\Version;
use Exception;
use Closure;

/**
 * Trait Versionable
 * @package Plank\Versionable\Concerns
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait Versionable
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootVersionable(): void
    {
        static::saving(function (self $model) {
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
     * Get all the linked releases for a given model instance.
     *
     * @return MorphToMany
     */
    public function releases(): MorphToMany
    {
        $release = config('versionable.release_model', Version::class);

        return $this->morphToMany($release, 'versionable');
    }

    public function scopeLatestSince(Builder $q, Version $v)
    {
        return $q
            ->whereHas('releases', function (Builder $query) use ($v) {
                $query->where('id', $v->id)
                    ->orWhere('created_at', '<', $v->created_at)
                    ->orderBy('created_at', 'desc'); // unneeded?
            });
    }

    /**
     *  each link to a release contains metadata that can be used to build a previous version of given model
     */
    public function createNewVersion()
    {
        $version = $this->replicate();
        // duplicate relationships as well - somehow...
        foreach ($this->getRelations() as $relation => $item) {
            $version->setRelation($relation, $item);
        }
        $version->save();
        $version->pivot->previousVersion()->save($this);
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

}
