<?php

namespace Plank\Checkpoint\Concerns;

trait StoresMeta
{
    public $metaAttributes = [];

    /**
     * Override model constructor to register media attributes, but make sure to call the Model constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->metaAttributes = $this->registerMetaAttributes();
    }

    /**
     * Moves data in columns specified in $metaAttributes from the model the revision
     */
    private function handleMeta(&$revision = null)
    {
        $meta = collect();
        foreach ($this->metaAttributes as $attribute) {
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
        if (!$value && in_array($key, $this->metaAttributes)) {
            return json_decode($this->revision->metadata)->$key;
        }

        return $value;

    }

    /**
     * @return array
     */
    public function registerMetaAttributes(): array
    {
        return [];
    }
}
