<?php

namespace Plank\Checkpoint\Stores;

use Plank\Checkpoint\Contracts\CheckpointStore;
use Plank\Checkpoint\Models\Checkpoint;

class BasicCheckpointStore implements CheckpointStore
{
    /**
     * A variable to store the active Checkpoint
     *
     * @var null|Checkpoint
     */
    protected $checkpoint = null;

    /**
     * Store the active checkpoint for the current request
     *
     * @param Checkpoint $checkpoint
     * @return void
     */
    public function store(Checkpoint $checkpoint): void
    {
        $this->checkpoint = $checkpoint;
    }

    /**
     * Retrieve the active checkpoint for the current request
     *
     * @return null|Checkpoint
     */
    public function retrieve(): ?Checkpoint
    {
        return $this->checkpoint;
    }

    /**
     * Clear the active checkpoint for the current request
     *
     * @return void
     */
    public function clear(): void
    {
        $this->checkpoint = null;
    }
}
