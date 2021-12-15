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
        if ($checkpoint->isDirty($checkpoint->getTimelineKeyName())) {

            $checkpoint->revisions()->update([
                app(Revision::class)->getTimelineKeyName() => $checkpoint->getTimelineKey()
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

        $revision = app(Revision::class);

        $checkpoint->revisions()->update([
            $revision->getCheckpointKeyName() => null,
            $revision->getTimelineKeyName() => null,
        ]);

        $active = $checkpointClass::active();
        if ($active && $active->getKey() === $checkpoint->getKey()) {
            $checkpointClass::clearActive();
        }
    }
}
