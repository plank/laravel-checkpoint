<?php

namespace Plank\Checkpoint\Concerns;

use Closure;
use Exception;
use ReflectionClass;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Checkpoint;
use Illuminate\Database\Eloquent\Builder;
use Plank\Checkpoint\Scopes\RevisionScope;
use Plank\Checkpoint\Helpers\RelationHelper;
use Plank\Checkpoint\Observers\RevisionableObserver;

/**
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder at($until)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder since($since)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder temporal($until, $since)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withoutRevisions()
 *
 * @mixin \Eloquent
 */
trait HasRevisions
{
    use HasCheckpointRelations;
    use StoresRevisionMeta;

    /**
     * Boot has revisions trait for a model.
     *
     * @return void
     */
    public static function bootHasRevisions(): void
    {
        static::addGlobalScope(new RevisionScope);
        // hook onto all relevant events: On Create, Update, Delete, Restore : make new revisions...
        static::observe(RevisionableObserver::class);
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
        return $builder->addSelect([
            'initial_id' => Revision::select('original_revisionable_id')
                ->whereColumn('revisionable_id', $this->getQualifiedKeyName())
                ->whereType(self::class)
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
        return $builder->addSelect([
            'previous_id' => Revision::select('revisionable_id')
                ->whereIn('id', Revision::select('previous_revision_id')
                    ->whereColumn('revisionable_id', $this->getQualifiedKeyName())
                    ->whereType($this))
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
        return $builder->addSelect([
            'next_id' => Revision::from('revisions as r')
                ->whereColumn('revisionable_id', $this->getQualifiedKeyName())
                ->whereType($this)
                ->selectSub(
                    Revision::select('id')
                        ->whereColumn('previous_revision_id', 'r.id')
                        ->whereType($this), 'sub'
                )
        ])->with('newer');
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
        return $this->revision->next->revisionable_id ?? null;
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
     * Return the columns to ignore when creating a copy of a model.
     * Gets passed to replicate() in saveAsRevision().
     *
     * @return array
     */
    public function getExcludedColumns(): array
    {
        return $this->getRevisionUnwatched();
    }

    /**
     * Set a protected unwatched array on your model to skip revisioning on specific columns
     *
     * @return array
     */
    public function getRevisionUnwatched(): array
    {
        return $this->unwatched ?? [];
    }

    /**
     * Modify the contents of the unwatched property.
     * Useful for adjusting what columns should be default when creating a new revision on a child relationship.
     *
     * @param  null  $unwatched
     */
    public function setRevisionUnwatched($unwatched = null): void
    {
        $this->unwatched = $unwatched ?? $this->getRevisionUnwatched();
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
    public function shouldRevision(): bool
    {
        return true;
    }

    /**
     * Update or Create the revision for this model.
     *
     * @param  array  $values
     *
     * @return bool|\Illuminate\Database\Eloquent\Model
     */
    public function startRevision($values = [])
    {
        if (!$this->shouldRevision() || $this->revision()->exists()) {
            return false;
        }

        return $this->revision()->create(array_merge([
            'revisionable_id' => $this->id,
            'revisionable_type' => static::class,
            'original_revisionable_id' => $this->id,
        ], $values));
    }

    /**
     * Each link to a release contains metadata that can be used to build a previous version of given model
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
            $copy = $this->withoutRelations()->replicate($this->getExcludedColumns());
            $copy->save();
            $copy->refresh();

            // Reattach relations to the copied object
            $excludedRelations = $this->getExcludedRelations();
            foreach (RelationHelper::getModelRelations($this) as $relation => $attributes) {
                if (!in_array($relation, $excludedRelations, true)) {
                    if (RelationHelper::isChild($attributes['type'])) {
                        logger(self::class . " {$this->getKey()}: duplicating children via $relation ({$attributes['type']})");
                        foreach ($this->$relation()->get() as $child) {
                            if (method_exists($child, 'bootHasRevisions')) {
                                // Revision the child model by attaching it to our new copy
                                $child->setRawAttributes(array_merge($child->getOriginal(), [
                                    $this->$relation()->getForeignKeyName() => $copy->getKey()
                                ]));
                                $child->setRevisionUnwatched();
                                $child->save();
                            } else {
                                $copy->$relation()->save($child->replicate());
                            }
                        }
                    } elseif (RelationHelper::isPivoted($attributes['type'])) {
                        logger(self::class . " {$this->getKey()}: duplicating pivots via $relation ({$attributes['type']})");
                        $copy->$relation()->syncWithoutDetaching($this->$relation()->get());
                    } else {
                        logger(self::class . " {$this->getKey()}: skipping duplication of $relation ({$attributes['type']})");
                    }
                }
            }

            // Reset the current model instance to original data
            $this->setRawAttributes($this->getOriginal());

            //  Handle unique columns by storing them as meta on the revision itself
            $this->moveMetaToRevision();

            // Update the revision of the original item
            $this->revision()->update([ 'latest' => false ]);

            // Update the revision of the duplicate with the correct data.
            $copy->revision()->update([
                'original_revisionable_id' => $this->revision->original_revisionable_id,
                'previous_revision_id' => $this->revision->id,
            ]);

            // Point $this to the duplicate, unload its relations and refresh the object
            $this->setRawAttributes($copy->getAttributes());
            $this->unsetRelations();
            $this->refresh();
        });

        $this->fireModelEvent('revisioned', false);

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
}
