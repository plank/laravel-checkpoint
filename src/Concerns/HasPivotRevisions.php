<?php

namespace Plank\Checkpoint\Concerns;

trait HasPivotRevisions {

    protected $owner;

    public static function bootHasPivotRevisions(): void
    {
        static::saving(function ($model) {
            $model->revisionOwner();
        });
    }

    // Potentially not needed?
    public function getOwnerRelation() {
        return $this->owner ?? 'owner';
    }

    public function getOwner()
    {
        $ownerRelation = $this->getOwnerRelation();
        return $this->$ownerRelation()->first();
    }

    public function revisionOwner()
    {
        $owner = $this->getOwner();
        if (method_exists($owner, 'bootHasRevisions')) {
            $relation = $this->getOwnerRelation();
            $this->load($relation);
            $this->$relation = null;
            logger("Owner Before: {$owner->id}");
            $owner->saveAsRevision();
            logger("Owner After: {$owner->id}");
            $owner->refresh();
            $this->$relation = $owner;
        }
    }
}
