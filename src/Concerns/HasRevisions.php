<?php

namespace Plank\Checkpoint\Concerns;

use Closure;
use Exception;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Scopes\RevisionScope;

/**
 * @package Plank\Versionable\Concerns
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasRevisions
{
    use HasCheckpointRelations;
    use StoresMeta;

    public $unwatched = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->unwatched = $this->registerUnwatchedColumns();
    }

    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasRevisions(): void
    {
        static::addGlobalScope(new RevisionScope());

        // hook newVersion onto all relevant events
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

    /**
     *  each link to a release contains metadata that can be used to build a previous version of given model
     */
    public function makeRevision()
    {
        // If only unwatched columns are dirty, then don't do any versioning
        if (array_diff(array_keys($this->getDirty()), $this->unwatched) !== []) {
            // Make sure we preserve the original
            $newItem = $this->replicate();

            // Duplicate relationships as well - but make sure we attach only the latest version of something
//            foreach ($this->getRelations() as $relation => $item) {
//                $version->setRelation($relation, $item/*->latestBefore()*/);
//            }

            // Store the new version
            $newItem->save();
            $this->fill($this->getOriginal());

            // Set our needed "pivot" data
            $model = config('checkpoint.revision_model', Revision::class);
            $newRevision = $newItem->revision;
            $newRevision->revisionable()->associate($newItem);
            $newRevision->original_revisionable_id = $this->revision->original_revisionable_id;
            $newRevision->previous_revision_id = $this->revision->id;

            $this->fill($this->getOriginal());
            $this->handleMeta();

            $newRevision->save();
        }

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

    public function registerUnwatchedColumns(): array
    {
        return [];
    }
}
