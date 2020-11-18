<?php

namespace Plank\Checkpoint\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
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
        // Constrain the scope to a specific window of time using valid checkpoints, carbon objects or datetime strings
        $builder->macro('temporal', function (Builder $builder, $until = null, $since = null) {
            $model = $builder->getModel();
            $revision = new Revision();

            $builder->withoutGlobalScope($this);
            // worst case execution plan : #reads = all rows in your table * all checkpoints * all revisions
            // avg case execution plan: all rows in your table * 1 revision * 1 revision * 0..max amount of checkpoints

            // METHOD 1: Join current table on revisions, join the result on closest subquery

            // METHOD 2 : Join current table on revisions, filter out by original and type index, use a where in for the closest ids subquery
/*            $builder->join("{$revision->getTable()} as _r", $model->getQualifiedKeyName(), '=', 'revisionable_id')
                ->whereIn("_r.{$revision->getKeyName()}",
                    $revision::latestIds($until, $since)
                        ->whereColumn('original_revisionable_id', '_r.original_revisionable_id')
                        ->whereType($model)
                )->where('revisionable_type', '=', get_class($model));

            // when using a join, you need to specific the select manually to avoid grabbing all the columns
            // but when using addSelect and giving it a subquery, laravel automatically selects the main table
            // causing duplicate columns
            $select = $model->getTable().'.*';
            $columns = $builder->getQuery()->columns;
            if ($columns === null || !in_array($select, $columns, true)) {
                $builder->addSelect($select);
            }
            $builder->addSelect(['_r.metadata']);*/
            
            // METHOD 3 : Uses a where exists wrapper on a where in subquery for closest timestamps
            $builder->whereHas('revision', function (Builder $query) use ($model, $until, $since) {
                $query->whereIn($query->getModel()->getQualifiedKeyName(),
                    $query->newModelInstance()->setTable('_r')->from($query->getModel()->getTable(), '_r')
                        ->latestIds($until, $since)
                        ->whereColumn($query->qualifyColumn('original_revisionable_id'), '_r.original_revisionable_id')
                        ->whereType($model));
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
}
