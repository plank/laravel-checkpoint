<?php

namespace Plank\Checkpoint\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Plank\Checkpoint\Contracts\CheckpointStore;
use Plank\Checkpoint\Models\Checkpoint;

class RevisionScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var array
     */
    protected $extensions = [
        'at',
        'since',
        'temporal',
        'withoutRevisions',
    ];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (config('checkpoint.apply_global_scope', true)) {
            $builder->at(app(CheckpointStore::class)->retrieve());
        }
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }


    /**
     * Add the temporal extension to the builder
     *
     * worst case execution plan : #reads = all rows in your table * all checkpoints * all revisions
     * avg case execution plan: all rows in your table * 1 revision * 1 revision * 0..max amount of checkpoints
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addTemporal(Builder $builder)
    {
        // Constrain the scope to a specific window of time using valid checkpoints, carbon objects or datetime strings
        $builder->macro('temporal', function (Builder $builder, $until = null, $since = null, $timeline = null) {
            $type = $builder->getModel();

            $builder->withoutGlobalScope($this);
            // METHOD 1: Join current table on revisions, join the result on closest subquery

            // METHOD 2 : Join current table on revisions, filter out by original and type index, use a where in for the closest ids subquery

            // METHOD 3 : Uses a where exists wrapper on a where in subquery for closest ids
            $builder->whereHas('revision', function (\Plank\Checkpoint\Builders\RevisionBuilder $sub) use ($type, $until, $since, $timeline) {
                $sub->closestTo($type, $until, $since, $timeline);
            });

            return $builder;
        });
    }

    /**
     * Add the at extension to the builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addInTimeline(Builder $builder)
    {
        $builder->macro('inTimeline', function (Builder $builder, $timeline = null) {
            return $builder->temporal(null, null, $timeline);
        });
    }

    /**
     * Add the at extension to the builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addAt(Builder $builder)
    {
        $builder->macro('at', function (Builder $builder, $moment = null) {
            $timeline = $moment instanceof Checkpoint ? $moment->timeline : null;

            return $builder->temporal($moment, null, $timeline);
        });
    }

    /**
     * Add the since extension to the builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addSince(Builder $builder)
    {
        $builder->macro('since', function (Builder $builder, $moment = null) {
            $timeline = $moment instanceof Checkpoint ? $moment->timeline : null;

            return $builder->temporal(null, $moment, $timeline);
        });
    }

    /**
     * Shortcut to clearing scope from query
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addWithoutRevisions(Builder $builder)
    {
        $builder->macro('withoutRevisions', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
