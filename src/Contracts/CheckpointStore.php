<?php

namespace Plank\Checkpoint\Contracts;

use Plank\Checkpoint\Models\Checkpoint;

interface CheckpointStore {
    /**
     * Store the active checkpoint for the current request
     * 
     * @param Checkpoint $checkpoint
     * @return void
     */
    public function store(Checkpoint $checkpoint): void;

    /**
     * Retrieve the active checkpoint for the current request
     *
     * @return null|Checkpoint
     */
    public function retrieve(): ?Checkpoint;

    /**
     * Clear the active checkpoint for the current request
     *
     * @return void
     */
    public function clear(): void;
}