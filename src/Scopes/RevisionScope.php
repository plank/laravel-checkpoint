<?php

namespace Plank\Checkpoint\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;

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
        'withoutCheckpoint',
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
        $builder->at();
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
     * Allows to scope the query using dates or
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addTemporal(Builder $builder)
    {
        $builder->macro('temporal', function (Builder $builder, $upper = null, $lower = null) {
            $model = $builder->getModel();
            $revision = config('checkpoint.revision_model', Revision::class);

            $builder->withoutGlobalScope($this);
            // worst case execution plan : #reads = all rows in your table * all checkpoints * all revisions
            // the timestamps subquery is the most expensive, reading all revisions from disk, try to build an index to fit it.

            //TODO: watch out for hardcoded columns names and relation names

            // METHOD 1: Join current table on revisions, join the result on timestamps subquery
            /* $builder->join('revisions', $model->getQualifiedKeyName(), '=', 'revisionable_id')
                 ->joinSub($revision::timestamps($upper, $lower), 'temporal', 'temporal.closest', '=', 'revisions.created_at')
                 ->where('revisionable_type', '=', get_class($model));*/

            // METHOD 2 : Join current table on revisions, filter out by revisionable_type, use a whereIn subquery for timestamps
            /* $builder->join('revisions', $model->getQualifiedKeyName(), '=', 'revisionable_id')
                ->whereIn('revisions.created_at', $revision::timestamps($upper, $lower))
                ->where('revisionable_type', '=', get_class($model));*/
            
            // METHOD 3 : Uses a where exists wrapper, laravel handles the revisionable id/type, use a whereIn subquery for timestamps
            $builder->whereHas('revision', function (Builder $query) use ($revision, $upper, $lower) {
               $query->whereIn((new $revision)->getQualifiedKeyName(), $revision::latestIds($upper, $lower));
            });

            return $builder;
        });
    }

    /**
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addAt(Builder $builder)
    {
        $builder->macro('at', function (Builder $builder, $moment = null) {
            return $builder->temporal($moment);
        });
    }

    /**
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addSince(Builder $builder)
    {
        $builder->macro('since', function (Builder $builder, $moment = null) {
            return $builder->temporal(null, $moment);
        });
    }

    /**
     * Shortcut to clearing scope from query
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addWithoutRevisions(Builder $builder)
    {
        $builder->macro('withoutRevisions', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Shortcut to clearing scope from query
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function addWithoutCheckpoint(Builder $builder)
    {
        $builder->macro('withoutCheckpoint', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
