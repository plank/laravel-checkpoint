<?php

namespace Plank\Checkpoint\Observers;

use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;

class CheckpointObserver
{
    /**
     * Handle the parent model "updating" event.
     *
     * @param Checkpoint $checkpoint
     * @return void
     */
    public function updated(Checkpoint $checkpoint)
    {
        /** @var Checkpoint $revision */
        $checkpointClass = config('checkpoint.models.checkpoint');

        if ($checkpoint->isDirty($checkpointClass::TIMELINE_ID)) {
            /** @var Revision $revision */
            $revision = config('checkpoint.models.revision');

            $checkpoint->revisions()->update([
                $revision::TIMELINE_ID => $checkpoint->{$checkpointClass::TIMELINE_ID}
            ]);

            // Ensure the model has the proper timeline loaded
            $checkpoint->unsetRelation('timeline');
            $checkpoint->load('timeline');
        }
    }

    /**
     * Handle the parent model "deleting" event.
     *
     * @param Checkpoint $checkpoint
     * @return void
     */
    public function deleting(Checkpoint $checkpoint)
    {
        /** @var Checkpoint $checkpointClass */
        $checkpointClass = config('checkpoint.models.checkpoint');

        /** @var Revision $revision */
        $revision = config('checkpoint.models.revision');

        $checkpoint->revisions()->update([
            $revision::CHECKPOINT_ID => null,
            $revision::TIMELINE_ID => null
        ]);

        $active = $checkpointClass::active();
        if ($active && $active->getKey() === $checkpoint->getKey()) {
            $checkpointClass::clearActive();
        }
    }
}
