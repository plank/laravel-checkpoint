<?php

namespace Plank\Checkpoint\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;

class CheckpointScope implements Scope
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

        // creating new drafts can be done here instead of observer.

        /*        $builder->onDelete(function (Builder $builder) {
                    $column = $this->getDeletedAtColumn($builder);

                    return $builder->update([
                        $column => $builder->getModel()->freshTimestampString(),
                    ]);
                });*/
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

            $builder->join('revisions', $model->getQualifiedKeyName(), '=', 'revisionable_id')
                ->joinSub($revision::timestamps($upper, $lower), 'temporal', 'temporal.closest', '=', 'revisions.created_at')
                ->where('revisionable_type', '=', get_class($model));

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
