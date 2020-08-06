<?php

namespace Plank\Checkpoint\Concerns;

/**
 * Trait StoresMeta
 *
 * Allows saving specific columns onto the revision metadata field and ensures the
 * model can retrieve those columns from meta if they don't exist in the main table.
 * Mainly used for columns that cannot contain duplicate entries (ex: ordering)
 *
 * @package Plank\Checkpoint\Concerns
 */
trait StoresMeta
{

    /**
     * Moves data in columns specified in $metaAttributes from the model the revision
     */
    private function handleMeta(&$revision = null)
    {
        $meta = collect();
        foreach ($this->getMeta() as $attribute) {
            $meta[$attribute] = $this->$attribute;
            $this->$attribute = null;
        }
        $revision = $revision ?? $this->revision;
        $revision->latest = false;
        $revision->metadata = $meta->toJson();
        $revision->save();
    }

    /**
     * Override of getAttribute from base eloquent model. This allows a user to access an attribute that is stored
     * in the meta data instead of the model.
     * @param $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (!$value && in_array($key, $this->getMeta())) {
            return json_decode($this->revision->metadata)->$key;
        }

        return $value;

    }

    public abstract function getMeta(): array;
}
