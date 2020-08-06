<?php

namespace Plank\Checkpoint\Concerns;

use Closure;
use Exception;
use ReflectionClass;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Scopes\RevisionScope;
use Neurony\Duplicate\Traits\HasDuplicates;
use Neurony\Duplicate\Options\DuplicateOptions;

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
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasRevisions(): void
    {
        static::addGlobalScope(new RevisionScope);

        // hook newVersion onto all relevant events
        // On Create, Update, Delete, Restore : make new revisions...

        static::created(function (self $model) {
            $model->updateOrCreateRevision();
        });

        static::updating(function (self $model) {
            if(!empty(array_diff(array_keys($model->getDirty()), $model->getUnwatched()))) {
                $model->saveAsRevision();
            }
        });

         static::deleting(function (self $model) {
            if (method_exists($model, 'bootSoftDeletes')) {
                $model->saveAsRevision();
            }
        });


        static::deleted(function (self $model) {
            if ($model->forceDeleting !== false) {
                //$model->deleteAllVersions();
            }
        });
    }

    /**
     * Register a revisioning model event with the dispatcher.
     *
     * @param Closure|string $callback
     * @return void
     */
    public static function revisioning($callback): void
    {
        static::registerModelEvent('revisioning', $callback);
    }

    /**
     * Register a revisioned model event with the dispatcher.
     *
     * @param Closure|string $callback
     * @return void
     */
    public static function revisioned($callback): void
    {
        static::registerModelEvent('revisioned', $callback);
    }

    /**
     * Override the duplicate package options to ignore certain relations.
     *
     * @return DuplicateOptions
     */
    public function getDuplicateOptions(): DuplicateOptions
    {
        $reflection = new ReflectionClass(HasCheckpointRelations::class);
        return DuplicateOptions::instance()->excludeRelations(collect($reflection->getMethods())->pluck('name'));
    }

    /**
     * Make sure that revisioning should be done before proceeding
     * Override and add any conditions your use cases may require
     *
     * @return bool
     */
    public function shouldRevision(): bool
    {
        return true;
    }

    /**
     * Update or Create the revision for this model
     *
     * @param  array  $values
     *
     * @return bool|\Illuminate\Database\Eloquent\Model
     */
    public function updateOrCreateRevision($values = [])
    {
        if ($this->shouldRevision()) {
            if ($this->revision()->exists()) {
                $search = $this->revision->toArray();
            } else {
                $search = [
                    'revisionable_id' => $this->id,
                    'revisionable_type' => self::class,
                    'original_revisionable_id' => $this->id,
                ];
            }
            // when values is empty, we want to write in the data as used for lookup
            $values = empty($values) ? $search : $values;

            return $this->revision()->updateOrCreate($search, $values);
        }

        return false;
    }

    /**
     *  each link to a release contains metadata that can be used to build a previous version of given model
     *
     * @throws Exception
     *
     * @return bool
     */
    public function saveAsRevision()
    {
        if ($this->shouldRevision() && !empty(array_diff(array_keys($this->getDirty()), $this->getUnwatched()))) {
            try {
                if ($this->fireModelEvent('revisioning') === false) {
                    return false;
                }

                $model = $this->getConnection()->transaction(function () {

                    // Ensure that the original model has a revision
                    if ($this->revision()->doesntExist()) {
                        $this->updateOrCreateRevision();
                    }


                    // Deep duplicate using neurony/laravel-duplicate
                    $copy = $this->saveAsDuplicate();

                    // Reset the original model to original data
                    $original = clone $this;
                    $original->fill($this->getOriginal());

                    // Handle unique columns by storing them as meta on the revision itself
                    $original->handleMeta();

                    // Update the revision of the duplicate with the correct data.
                    $copy->updateOrCreateRevision([
                        'original_revisionable_id' => $this->revision->original_revisionable_id,
                        'previous_revision_id' => $this->revision->id,
                    ]);

                    // Update returning object to use the keys of the duplicate
                    self::unguard();
                    $this->fill($copy->getAttributes());
                    $this->syncOriginal();
                    self::reguard();

                    // Clear any other remaining attributes and cached relations from the original model
                    return $this->refresh();
                });

                $this->fireModelEvent('revisioned', false);

                return $model;
            } catch (Exception $e) {
                throw $e;
            }
        }
        return true;
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

    public abstract function getUnwatched(): array;
}
