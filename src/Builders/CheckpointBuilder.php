<?php

namespace Plank\Checkpoint\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Timeline;

class CheckpointBuilder extends Builder
{

    public function selectKey(): self
    {
        return $this->select($this->defaultKeyName());
    }

    /**
     * Apply a scope to filter for checkpoints closest to the one provided based on an operator
     * when less than, orders by checkpoint_date desc
     * when greater than, orders checkpoint_date asc
     *
     * @param Checkpoint|Carbon|string $moment
     * @param string $operator
     * @return self
     */
    public function closestTo($moment, string $operator = '<='): self
    {
        $column = $this->getModel()->getCheckpointDateColumn();
        if ($moment instanceof Checkpoint) {
            $moment = $moment->getCheckpointDate();
        }
        $this->where($column, $operator, $moment);
        if ($operator === '<' || $operator === '<=') {
            $this->latest($column);
        } elseif ($operator === '>' || $operator === '>=') {
            $this->oldest($column);
        }
        return $this;
    }

    /**
     * Apply a scope to filter for checkpoints older than the one provided ordered by checkpoint date desc
     *
     * @param Checkpoint|Carbon|string $moment
     * @param bool $strict
     * @return self
     */
    public function olderThan($moment, bool $strict = true): self
    {
        return $this->closestTo($moment, $strict ? '<' : '<=');
    }

    /**
     * Apply a scope to filter for checkpoints older or equal to the one provided ordered by checkpoint date desc
     *
     * @param Checkpoint|Carbon|string $moment
     * @return self
     */
    public function olderThanEquals($moment): self
    {
        return $this->olderThan($moment, false);
    }

    /**
     * Apply a scope to filter for checkpoints newer than the one provided ordered by checkpoint date asc
     *
     * @param Checkpoint|Carbon|string $moment
     * @param bool $strict
     * @return self
     */
    public function newerThan($moment, bool $strict = true): self
    {
        return $this->closestTo($moment, $strict ? '>' : '>=');
    }

    /**
     * Apply a scope to filter for checkpoints newer or equal to the one provided ordered by checkpoint date asc
     *
     * @param Checkpoint|Carbon|string $moment
     * @return self
     */
    public function newerThanEquals($moment): self
    {
        return $this->newerThan($moment, false);
    }
}
