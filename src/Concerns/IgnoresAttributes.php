<?php

namespace Plank\Checkpoint\Concerns;

/**
 * Specify attributes of your model that need to be ignored when deciding to perform a revision or not.
 * When only these ignored attributes are changed on a model, checkpoint will update the existing record
 * instead of creating a new revision.
 *
 * IMPORTANT: To use, set a new protected $ignored array on your model.
 * Properties set on traits can't be redefined in the classes directly using that trait.
 */
trait IgnoresAttributes
{

    /**
     * Set a protected ignored array on your model to skip revisioning on specific columns
     * Backwards compatible with unwatched
     *
     * @return array
     */
    public function getIgnored(): array
    {
        return $this->ignored ?? $this->getRevisionUnwatched() ?? [];
    }

    /**
     * Set the ignored attributes for the model.
     *
     * @param array|string $ignored
     * @return $this
     */
    public function ignore($ignored): self
    {
        if (is_array($ignored)) {
            $this->ignored = $ignored;
        } elseif (is_string($ignored)) {
            $this->mergeIgnored([$ignored]);
        }

        return $this;
    }

    /**
     * Merge new ignored attributes with existing fillable attributes on the model.
     *
     * @param array $ignored
     * @return $this
     */
    public function mergeIgnored(array $ignored): self
    {
        $this->ignored = array_merge($this->ignored, $ignored);

        return $this;
    }

    // DEPRECATED

    /**
     * Set a protected unwatched array on your model to skip revisioning on specific columns
     *
     * @deprecated
     * @return array
     */
    public function getRevisionUnwatched(): array
    {
        return $this->unwatched ?? [];
    }

    /**
     * Modify the contents of the unwatched property.
     * Useful for adjusting what columns should be default when creating a new revision on a child relationship.
     *
     * @deprecated
     * @param  null  $unwatched
     */
    public function setRevisionUnwatched($unwatched = null): void
    {
        $this->unwatched = $unwatched ?? $this->getRevisionUnwatched();
    }

}
