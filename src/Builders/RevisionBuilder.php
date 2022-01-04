<?php

namespace Plank\Checkpoint\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Timeline;

class RevisionBuilder extends Builder
{

    /**
     * @param string|null $column
     * @return self
     */
    public function selectTop(string $column = null): self
    {
        $column = $column ?? $this->defaultKeyName();

        return $this->selectRaw("max($column)")
            ->groupBy(['original_revisionable_id', 'revisionable_type'])
            ->orderByDesc('previous_revision_id');
    }

    /**
     * @param Checkpoint $moment
     * @return self
     */
    public function newerThanCheckpoint(Checkpoint $moment): self
    {
        return $this->whereIn(
            $this->getModel()->getCheckpointKeyName(),
            app(Checkpoint::class)::newerThan($moment)->selectKey()
        );
    }

    /**
     * @param Checkpoint $moment
     * @return self
     */
    public function olderThanEqualsCheckpoint(Checkpoint $moment): self
    {
        return $this->whereIn(
            $this->getModel()->getCheckpointKeyName(),
            app(Checkpoint::class)::olderThanEquals($moment)->selectKey()
        );
    }

    /**
     * @param Checkpoint|Carbon|string $moment
     * @return self
     */
    public function newerThan($moment): self
    {
        if ($moment instanceof Checkpoint) {
            return $this->newerThanCheckpoint($moment);
        }
        return $this->where($this->getModel()->getQualifiedCreatedAtColumn(), '>', $moment);
    }

    /**
     * @param Checkpoint|Carbon|string $moment
     * @return self
     */
    public function olderThanEquals($moment): self
    {
        if ($moment instanceof Checkpoint) {
            return $this->olderThanEqualsCheckpoint($moment);
        }
        return $this->where($this->getModel()->getQualifiedCreatedAtColumn(), '<=', $moment);
    }

    /**
     * @param Timeline|string $timeline
     * @return self
     */
    public function whereTimeline($timeline): self
    {
        if ($timeline instanceof Timeline) {
            $timeline = $timeline->getKey();
        }
        return $this->where($this->getModel()->getTimelineKeyName(), $timeline);
    }

    /**
     * Retrieve the latest revision ids within the boundary window given valid checkpoints, carbon or datetime strings
     *
     * @param Checkpoint|Carbon|string|null $until valid checkpoint, carbon or datetime string
     * @param Checkpoint|Carbon|string|null $since valid checkpoint, carbon or datetime string
     * @param Timeline|string|null $timeline valid timeline or its key
     * @return self
     */
    public function latestIds($until = null, $since = null, $timeline = null): self
    {
        // select max(id) grouped by revision lineage aka latest revision ids
        $this->withoutGlobalScopes()->selectTop();
        // set a lower bound to the revisions
        if ($since) {
            $this->newerThan($since);
        }
        // set an upper bound to the revisions
        if ($until) {
            $this->olderThanEquals($until);
        }
        // when timeline is passed restrict revisions to only the ones from this timeline
        if (is_array($timeline)) {
            $this->whereInTimelines($timeline);
        } elseif ($timeline !== null) {
            $this->whereTimeline($timeline);
        }

        return $this;
    }

    /**
     * @param Model|string|null $type
     * @return self
     */
    public function whereType($type = null): self
    {
        if (is_string($type)) {
            $this->where('revisionable_type', $type);
        } elseif ($type instanceof Model) {
            $this->where('revisionable_type', get_class($type));
        }
        return $this;
    }

    /**
     * @param $type
     * @param Checkpoint|Carbon|string|null $until valid checkpoint, carbon or datetime string
     * @param Checkpoint|Carbon|string|null $since valid checkpoint, carbon or datetime string
     * @param Timeline|string|null $timeline valid timeline or its key
     * @return self
     */
    public function closestTo($type, $until = null, $since = null, $timeline = null): self
    {
        // somewhat of a hack to alias the revisions table and correct the qualified columns
        $alias = '_r';
        $sub = $this->newModelInstance()->setTable($alias)->from($this->getModel()->getTable(), $alias);

        // improve query execution plan by matching on the columns of the parent query
        $original_column = 'original_revisionable_id';
        $sub->whereColumn($this->qualifyColumn($original_column), "$alias.$original_column")->whereType($type);

        // select revisions where the ids match the latest revision ids given the passed restrictions
        return $this->whereIn($this->defaultKeyName(), $sub->latestIds($until, $since, $timeline));
    }

}
