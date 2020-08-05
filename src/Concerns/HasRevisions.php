<?php

namespace Plank\Checkpoint\Concerns;

use Closure;
use Exception;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Scopes\RevisionScope;
use Neurony\Duplicate\Traits\HasDuplicates;
use Neurony\Duplicate\Options\DuplicateOptions;
use ReflectionClass;

/**
 * @package Plank\Versionable\Concerns
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasRevisions
{
    use HasCheckpointRelations;
    use HasDuplicates;
    use StoresMeta;

    /**
     * Get the options for duplicating the model.
     *
     * @return DuplicateOptions
     */
    public function getDuplicateOptions(): DuplicateOptions
    {
        $reflection = new ReflectionClass(HasCheckpointRelations::class);
        return DuplicateOptions::instance()->excludeRelations(collect($reflection->getMethods())->pluck('name'));
    }

    public $unwatched = [];

    /**
     * Override model constructor to register meta attributes,
     * but make sure to call the Model constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->unwatched = $this->registerUnwatchedColumns();
        $this->meta = $this->registerMetaAttributes();
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
     * Make sure that revisioning should be done before proceeding
     * Override and add any conditions your use cases may require
     *
     * @return bool
     */
    public function shouldRevision(): bool
    {
        // Are only unwatched columns are dirty?
        if (empty(array_diff(array_keys($this->getDirty()), $this->unwatched))) {
            return false;
        }


        return true;
    }

    /**
     *  each link to a release contains metadata that can be used to build a previous version of given model
     *
     * @throws Exception
     */
    public function makeRevision()
    {
        if ($this->shouldRevision()) {

            try {
                $this->getConnection()->transaction(function () {
                    // Deep duplicate using neurony/laravel-duplicate
                    $duplicate = $this->saveAsDuplicate();

                    // Update the revision of the duplicate with the correct data.
                    $revision = $duplicate->revision;
                    $revision->revisionable()->associate($duplicate);
                    $revision->original_revisionable_id = $this->revision->original_revisionable_id;
                    $revision->previous_revision_id = $this->revision->id;
                    $revision->saveOrFail();

                    // Reset the original model to original data
                    $this->fill($this->getOriginal());

                    // Handle unique columns
                    $this->handleMeta();
                });
            } catch (Exception $e) {
                throw $e;
            }
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
