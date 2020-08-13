<?php

namespace Plank\Checkpoint\Concerns;

use Plank\Checkpoint\Observers\RevisionableObserver;
use Plank\Checkpoint\Scopes\RevisionScope;

/**
 * Trait StoresMeta
 *
 * Allows saving specific columns onto the revision metadata field and ensures the
 * model can retrieve those columns from meta if they don't exist in the main table.
 * Mainly used for columns that cannot contain duplicate entries (ex: ordering)
 *
 * @mixin HasRevisions
 */
trait StoresRevisionMeta
{
    /**
     * Initialize the stores revision meta trait for an instance.
     *
     * @return void
     */
    public function initializeStoresRevisionMeta()
    {
        // $this->appends[] = 'metadata';
    }

    /**
     * @return array
     */
    public function getMetadataAttribute()
    {
        return json_decode($this->revision->metadata, true);
    }
    
    /**
     * Set a protected revision_meta array on your model
     * to store/access unique columns as metadata on its revision
     *
     * @return array
     */
    public function getRevisionMeta(): array
    {
        return $this->revisionMeta ?? [];
    }

    /**
     * Moves data in columns specified in $metaAttributes from the model the revision
     */
    public function moveMetaToRevision(&$revision = null)
    {
        $metaColumns = $this->getRevisionMeta();
        //if (!empty($metaColumns)) {
            $meta = collect();
            foreach ($metaColumns as $attribute) {
                $meta[$attribute] = $this->$attribute;
                // TODO: check column is nullable or put empty string
                $this->$attribute = null;
            }
            $revision = $revision ?? $this->revision;
            $revision->latest = false;
            $revision->metadata = $meta->toJson();
            $this->withoutEvents(function () use ($revision){
                $revision->save();
                $this->save(); // modified attributes, make sure this is saved without events
            });
        //}
    }

    /**
     * Get a plain attribute (not a relationship). **Override of base eloquent model**
     * This allows a user to access an attribute that is stored in the meta data instead of the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);
        if (is_null($value) && in_array($key, $this->getRevisionMeta())) {
            return $this->metadata[$key];
        }

        return $value;

    }
}
