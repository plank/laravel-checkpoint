<?php

namespace Plank\Checkpoint\Concerns;

/**
 * Specify attributes of your model that need to be excluded from the revisioning process.
 * These attributes will never be replicated between models, and you will need to set up defaults
 * or fill them some other way before they can be inserted into the database.
 *
 * IMPORTANT: To use, set a new protected $excluded array on your model.
 * Properties set on traits can't be redefined in the classes directly using that trait.
 */
trait ExcludesAttributes
{

    /**
     * Work around for a PHP limitation where properties set on traits
     * can't be redefined in the classes directly using that trait
     * @var array
     */
    private $internalExcluded = [];

    /**
     * Return the column that will be excluded when replicating your model in performRevision()
     *
     * @return array
     */
    public function getExcluded(): array
    {
        return $this->excluded ?? $this->internalExcluded;
    }

    /**
     * Set the excluded attributes for the model.
     *
     * @param array|string $excluded
     * @return $this
     */
    public function exclude($excluded): self
    {
        if (is_array($excluded)) {
            if (isset($this->excluded)) {
                $this->excluded = $excluded;
            } else {
                $this->internalExcluded = $excluded;
            }
        } elseif (is_string($excluded)) {
            $this->mergeExcluded([$excluded]);
        }

        return $this;
    }

    /**
     * Merge new excluded attributes with existing excluded attributes on the model.
     *
     * @param array $excluded
     * @return $this
     */
    public function mergeExcluded(array $excluded): self
    {
        $this->exclude(array_merge($this->getExcluded(), $excluded));

        return $this;
    }

    /**
     * Clears the excluded attributes for the model.
     *
     * @param array $excluded
     * @return $this
     */
    public function clearExcluded(array $excluded = ['*']): self
    {
        if ($excluded === ['*']) {
            $this->exclude([]);
        } elseif (is_array($excluded)) {
            foreach ($excluded as $key) {
                if (isset($this->excluded)) {
                    unset($this->excluded[$key], $this->internalExcluded[$key]);
                } else {
                    unset($this->internal_excluded[$key]);
                }
            }
        }

        return $this;
    }

}
