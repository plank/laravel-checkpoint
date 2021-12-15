<?php

namespace Plank\Checkpoint\Concerns;

use Closure;
use Exception;
use ReflectionClass;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Checkpoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Plank\Checkpoint\Scopes\RevisionScope;
use Plank\Checkpoint\Helpers\RelationHelper;
use Plank\Checkpoint\Models\Timeline;
use Plank\Checkpoint\Observers\RevisionableObserver;

/**
 * @property-read null|Checkpoint $checkpoint
 * @property-read null|Timeline $timeline
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder at($until)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder since($since)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder temporal($until, $since)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withNewestAt($until, $since)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withNewest()
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withInitial()
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withPrevious()
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withNext()
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withoutRevisions()
 *
 * @mixin Model
 */
trait HasRevisions
{
    use HasCheckpointRelations;
    use StoresRevisionMeta;
    use IgnoresAttributes;
    use ExcludesAttributes;

    /**
     * Boot has revisions trait for a model.
     *
     * @return void
     */
    public static function bootHasRevisions(): void
    {
        static::addGlobalScope(new RevisionScope);
        if (config('checkpoint.enabled')) {
            // hook onto all relevant events: On Create, Update, Delete, Restore : make new revisions...
            static::observe(RevisionableObserver::class);
        }
    }

    /**
     * Initialize the has revisions trait for an instance.
     *
     * @return void
     */
    public function initializeHasRevisions(): void
    {
        $this->addObservableEvents('revisioning', 'revisioned');
    }

    /**
     * Register a revisioning model event with the dispatcher.
     *
     * @param  Closure|string  $callback
     * @return void
     */
    public static function revisioning($callback): void
    {
        static::registerModelEvent('revisioning', $callback);
    }

    /**
     * Register a revisioned model event with the dispatcher.
     *
     * @param  Closure|string  $callback
     * @return void
     */
    public static function revisioned($callback): void
    {
        static::registerModelEvent('revisioned', $callback);
    }

    /**
     * Add the previous and next scopes loading their respective relations
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function scopeWithRevisions(Builder $builder)
    {
        return $builder->withPrevious()->withNext();
    }

    /**
     * Get the id of the initial revisioned item
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function scopeWithInitial(Builder $builder)
    {
        $revision = config('checkpoint.models.revision');
        return $builder->addSelect([
            'initial_id' => $revision::select('original_revisionable_id')
                ->whereColumn('revisionable_id', $this->getQualifiedKeyName())
                ->whereType($this)
        ])->with('initial');
    }

    /**
     * Get the id of the previous revisioned item
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function scopeWithPrevious(Builder $builder)
    {
        $revision = config('checkpoint.models.revision');
        return $builder->addSelect([
            'previous_id' => $revision::select('revisionable_id')
                ->whereIn('id', $revision::select('previous_revision_id')
                    ->whereColumn('revisionable_id', $this->getQualifiedKeyName())
                    ->whereType($this)
                )
        ])->with('older');
    }

    /**
     * Get the id of the next revisioned item
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function scopeWithNext(Builder $builder)
    {
        $revision = config('checkpoint.models.revision');
        return $builder->addSelect([
            'next_id' => $revision::selectSub($revision::select('id')
                    ->whereColumn('previous_revision_id', 'r.id')
                    ->whereType($this), 'sub'
                )->from('revisions as r')
                ->whereColumn('revisionable_id', $this->getQualifiedKeyName())
                ->whereType($this)
        ])->with('newer');
    }

    /**
     * Get the id of the most recent revisioned item
     *
     * @param Builder|self $builder
     * @return mixed
     */
    public function scopeWithNewestAt($builder, $until = null, $since = null)
    {
        /**
         * @var Revision $revision
         */
        $revision = config('checkpoint.models.revision');

        $newestIdQuery = $revision::select('revisionable_id')
            ->whereColumn('original_revisionable_id', 'initial_id')
            ->whereType($this)
            ->whereIn((new $revision)->getKeyName(), $revision::latestIds($until, $since)
                ->whereColumn('original_revisionable_id', 'initial_id')
                ->whereType($this)
            );

        // The SQL Standard does not allow referencing aliases in the SELECT clause,
        // Postgres and SQLite follow this convention, mysql does not. When the
        // driver is mysql, prefer 2 SELECT sub-queries over a nested FROM sub-query
        // TODO: confirm this works on other db drivers (postgres, mssql, mongo?)
        if($builder->getConnection()->getDriverName() === 'mysql') {
            $withNewest = $builder->withInitial()->addSelect(['newest_id' => $newestIdQuery]);
        } else {
            $model = $builder->getModel();
            $withNewest = $model->newQueryWithoutScopes()
                ->addSelect(['newest_id' => $newestIdQuery])
                ->from($builder->withInitial(), $model->getTable());
        }
        return $withNewest->with('newest', 'initial');
    }

    /**
     * add a sub-select column linking this model to its most recent revision
     *
     * @return mixed
     */
    public function scopeWithNewest($builder)
    {
        return $builder->withNewestAt();
    }

    /**
     * Get the id of the initial revisionable
     *
     * @param  $value
     * @return int
     */
    public function getInitialIdAttribute($value)
    {
        if ($value !== null || array_key_exists('initial_id', $this->attributes)) {
            return $value;
        }
        // when value isn't set by extra subselect scope, fetch from relation
        return $this->revision->original_revisionable_id ?? null;
    }

    /**
     * Get the id of the previous revisionable
     *
     * @param  $value
     * @return int
     */
    public function getPreviousIdAttribute($value)
    {
        if ($value !== null || array_key_exists('previous_id', $this->attributes)) {
            return $value;
        }
        // when value isn't set by extra subselect scope, fetch from relation
        return $this->revision->previous->revisionable_id ?? null;
    }

    /**
     * Get the id of the next revisionable
     *
     * @param  $value
     * @return int
     */
    public function getNextIdAttribute($value)
    {
        if ($value !== null || array_key_exists('next_id', $this->attributes)) {
            return $value;
        }
        // when value isn't set by extra subselect scope, fetch from relation
        return $this->revision->next->revisionable_id ?? null;
    }

    /**
     * Get the newest revision in the lineage
     *
     * @return static
     */
    public function getNewestIdAttribute($value)
    {
        if ($value !== null || array_key_exists('newest_id', $this->attributes)) {
            return $value;
        }
        // dependency on latest boolean column, alternative to using max id
        return $this->revisions()->where('latest', true)->first()->revisionable_id;
    }


    /**
     * Is this model the first revision
     *
     * @return bool
     */
    public function getNewAttribute(): bool
    {
        return $this->revision->isNew();
    }

    /**
     * Is this model the latest revision
     *
     * @return bool
     */
    public function getLatestAttribute(): bool
    {
        return $this->revision->isLatest();
    }


    /**
     * Is this model updated on the given checkpoint moment
     *
     * @param  Checkpoint  $moment
     * @return bool
     */
    public function isUpdatedAt(Checkpoint $moment): bool
    {
        return $this->revision->isUpdatedAt($moment);
    }

    /**
     * Is this model new on the given checkpoint moment
     *
     * @param  Checkpoint  $moment
     * @return bool
     */
    public function isNewAt(Checkpoint $moment): bool
    {
        return $this->revision->isNewAt($moment);
    }

    /**
     * Get an array of the relationships to be ignored during duplication
     *
     * @return array
     */
    public function getExcludedRelations(): array
    {
        // todo: this runs on every save, is there a way to run once per instance??
        $reflection = new ReflectionClass(HasCheckpointRelations::class);
        $default = collect($reflection->getMethods())->pluck('name')->toArray();
        return array_merge($default, $this->excludedRelations ?? []);
    }

    /**
     * Make sure that revisioning should be done before proceeding.
     * Override and add any conditions your use cases may require.
     *
     * @return bool
     */
    protected function shouldRevision(): bool
    {
        return true;
    }

    /**
     * Create the revision for this model.
     *
     * @param  array  $values
     * @return bool|\Illuminate\Database\Eloquent\Model
     */
    public function startRevision($values = [])
    {
        if (!$this->shouldRevision() || $this->revision()->exists()) {
            return false;
        }

        $checkpoint = Checkpoint::active();
        $timeline = $checkpoint ? $checkpoint->timeline : null;

        return $this->revision()->create(array_merge([
            Revision::CHECKPOINT_ID => $checkpoint ? $checkpoint->getKey() : null,
            Revision::TIMELINE_ID => $timeline ? $timeline->getKey() : null,
            'revisionable_id' => $this->id,
            'revisionable_type' => static::class,
            'original_revisionable_id' => $this->id,
        ], $values));
    }

    /**
     * Perform a revision on this model that will attempt to duplicate itself & all of its relations.
     *
     * @return bool
     * @throws \Throwable
     */
    public function performRevision()
    {
        if (!$this->shouldRevision() || $this->fireModelEvent('revisioning') === false) {
            return false;
        }

        $this->getConnection()->transaction(function () {

            // Ensure that the original model has a revision
            $this->startRevision();

            // Replicate the current object
            $copy = $this->withoutRelations()->replicate($this->getExcluded());
            $copy->save();
            $copy->refresh();

            // Reattach relations to the copied object
            $this->replicateRelationsTo($copy);

            // Retrieve dirty columns on the current model instance before resetting it
            $dirty = array_diff(array_keys($this->getDirty()), $this->getExcluded());
            // Reset the current model instance to original data
            $this->setRawAttributes($this->original);

            //  Handle unique columns by storing them as meta on the revision itself
            $this->moveMetaToRevision();

            // Update the revision of the original item
            $this->revision()->update([ 'latest' => false ]);

            // Update the revision of the duplicate with the correct data.
            $copy->revision()->update([
                'original_revisionable_id' => $this->revision->original_revisionable_id,
                'previous_revision_id' => $this->revision->id,
            ]);

            // Return dirty values back onto $this model instance
            $this->setRawAttributes($copy->getAttributes());
            // force using new revision key for any future queries
            unset($this->original[$this->getKeyName()]);
            // unset any loaded relations, their data is no longer valid
            $this->unsetRelations();
        });

        $this->fireModelEvent('revisioned', false);

        return true;
    }

    /**
     * Iterate over all possible relations on this model and decide how to duplicate them to the given $copy model.
     * Only children and pivots are replicated by default, parents should never be duplicated. This method support
     * standards laravel relations defined in RelationHelper.
     *
     * Custom handlers for relations and relation types can be registered as a function on your model or in a trait,
     * like you would with model scopes. syntax: replicate<Relation>Relation() or replicate<RelationType>Relations()
     *
     * @param Model $copy
     * @throws \ReflectionException
     * @throws \Throwable
     */
    protected function replicateRelationsTo(Model $copy)
    {
        $relationHelper = resolve(RelationHelper::class);

        $excluded = $this->getExcludedRelations();
        $relations = collect($relationHelper::getModelRelations($this))->map->type->except($excluded);

        foreach ($relations as $relation => $type) {
            $shortType = substr($type, strrpos($type, '\\') + 1);
            if (method_exists($this, 'replicate' . ucfirst($relation) . 'Relation')) {
                $this->{'replicate' . ucfirst($relation) . 'Relation'}($copy, $relation, $type); // replicateModulesRelation()
            } elseif (method_exists($this, "replicate{$shortType}Relations" )) {
                $this->{"replicate{$shortType}Relations"}($copy, $relation, $type); // replicateHasManyRelations()
            } elseif ($relationHelper::isChild($type)) {
                $this->replicateChildrenTo($copy, $relation);
            } elseif ($relationHelper::isPivoted($type)) { // default is pivot
                $changes = $copy->$relation()->syncWithoutDetaching($this->$relation()->get());
            } else {
                // skipped relation
            }
        }
    }

    /**
     * Replicate the children available through a given relationship.
     * Revisionable models will be modified and prompted to create a new revision, other will simply be replicated.
     *
     * @param Model $copy
     * @param string $relation
     */
    protected function replicateChildrenTo($copy, $relation)
    {
        foreach ($this->$relation()->get() as $child) {
            /**
             * @var Model|HasRevisions $child
             */
            if (method_exists($child, 'bootHasRevisions')) {
                // Revision the child model by attaching it to our new copy
                $child->setAttribute($this->$relation()->getForeignKeyName(), $copy->getKey());
                $child->clearExcluded();
                $child->save();
            } else {
                $copy->$relation()->save($child->replicate());
            }
        }
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
     * Sync the changed attributes.
     *
     * @param  array|string|null
     * @return $this
     */
    public function syncChangedAttributes($changes = null): self
    {
        if (empty($changes)) {
            return $this->syncChanges();
        }

        $changes = is_array($changes) ? $changes : func_get_args();

        foreach ($changes as $key) {
            $this->changes[$key] = $this->attributes[$key];
        }

        return $this;
    }
}
