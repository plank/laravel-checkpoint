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
    use StoresRevisionMeta;
    use HasDuplicates;

    /**
     * Make sure that revisioning should be done before proceeding.
     * Override and add any conditions your use cases may require.
     *
     * @return bool
     */
    public function shouldRevision(): bool
    {
        return true;
    }

    /**
     * Set a protected unwatched array on your model
     * to skip revisioning on specific columns.
     *
     * @return array
     */
    public function getRevisionUnwatched(): array
    {
        return $this->unwatched ?? [];
    }

    /**
     * Set the relationships to be ignored during duplication.
     * Supply an array of relation method names.
     *
     * @return array
     */
    public function getExcludedRelations(): array
    {
        return $this->exludedRelations ?? [];
    }

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
            // Check if any column is dirty and filter out the unwatched fields
            if(!empty(array_diff(array_keys($model->getDirty()), $model->getRevisionUnwatched()))) {
                $model->saveAsRevision();
            }
        });

         static::deleting(function (self $model) {
            if (method_exists($model, 'bootSoftDeletes')) {
                $model->saveAsRevision();
            }
        });

        static::deleted(function (self $model) {
            if (method_exists($model, 'bootSoftDeletes')) {
                if ($model->forceDeleting === true) {
                    $model->revision()->delete();
                }
            } else {
                $model->revision()->delete();
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
        $default = collect($reflection->getMethods())->pluck('name');
        return DuplicateOptions::instance()->excludeRelations($default->merge($this->getExcludedRelations()));
    }

    /**
     * Update or Create the revision for this model.
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
     * Each link to a release contains metadata that can be used to build a previous version of given model
     *
     * @return bool
     * @throws \Throwable
     */
    public function saveAsRevision()
    {
        if ($this->shouldRevision()) {
            try {
                if ($this->fireModelEvent('revisioning') === false) {
                    return false;
                }

                $this->getConnection()->transaction(function () {

                    // Ensure that the original model has a revision
                    if ($this->revision()->doesntExist()) {
                        $this->updateOrCreateRevision();
                    }

                    // Deep duplicate using neurony/laravel-duplicate
                    // NOTE: some unwanted relations could be duplicated, configurable with getDuplicateOptions()
                    $copy = $this->saveAsDuplicate();

                    // Reset the current model instance to original data
                    $this->fill($this->getOriginal());

                    //  Handle unique columns by storing them as meta on the revision itself
                    $this->moveMetaToRevision();

                    // Update the revision of the duplicate with the correct data.
                    $copy->updateOrCreateRevision([
                        'original_revisionable_id' => $this->revision->original_revisionable_id,
                        'previous_revision_id' => $this->revision->id,
                        //'created_at' => $this->freshTimestampString(),
                    ]);

                    // Point $this to the duplicate, unload its relations and refresh the object
                    $this->setRawAttributes($copy->getAttributes());
                    $this->relations = [];
                    $this->refresh();
                });

                $this->fireModelEvent('revisioned', false);

                return true;
            } catch (Exception $e) {
                throw $e;
            }
        }
        return false;
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
}
