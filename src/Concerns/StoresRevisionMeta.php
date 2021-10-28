<?php

namespace Plank\Checkpoint\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
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
     * Get the id of the previous revisioned item
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function scopeWithMetadata(Builder $builder)
    {
        $revision = config('checkpoint.models.revision');
        return $builder->addSelect([
            'metadata' => $revision::select('revisionable_id')
                ->where('revisionable_id', $this->getKey())
                ->whereType($this)
        ]);
    }

    /**
     * @return array
     */
    public function getMetadataAttribute($value)
    {
        if ($value !== null || array_key_exists('metadata', $this->attributes)) {
            return $value;
        }
        // when value isn't set by extra subselect scope, fetch from relation
        return $this->revision->metadata ?? null;
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
        $uniqueColumns = $this->getRevisionMeta();
        if (!empty($uniqueColumns) && config('checkpoint.store_unique_columns_on_revision')) {
            $unique = [];
            foreach ($uniqueColumns as $attribute) {
                $unique[$attribute] = $this->$attribute;
                // TODO: check column is nullable or put empty string
                $this->$attribute = null;
            }
            $revision = $revision ?? $this->revision;
            $revision->metadata = $unique;
            self::withoutEvents(function () use ($revision) {
                $revision->save();
                $this->save(); // modified attributes, make sure this is saved without events
            });
        //}
    }

    /**
     * Get a plain attribute (not a relationship).
     * Overrides default eloquent method by adding support for pulling data from revision metadata.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);
        if (is_null($value) && in_array($key, $this->getRevisionMeta()) && $this->revision()->exists()) {
            return $this->metadata[$key] ?? null;
        }

        return $value;
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // go over columns stored in the revision metadata and add them to the returned array
        if ($this->revision()->exists()) {
            foreach ($this->getRevisionMeta() as $key) {
                $attributes[$key] = $this->metadata[$key] ?? null;
            }
        }

        return $attributes;
    }
}
