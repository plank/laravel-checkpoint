<?php

namespace Plank\Versionable\Concerns;

trait StoresMeta
{
    public $metaAttributes = [];

    /**
     * Moves data in columns specified in $metaAttributes from the model the revision
     */
    private function handleMeta()
    {
        $meta = collect();
        foreach ($this->metaAttributes as $attribute) {
            $meta->push($this->$attribute);
            $this->$attribute = null;
        }
        $revision = $this->revision;
        $revision->meta = $meta->toJson();
        $revision->save();
    }
}
