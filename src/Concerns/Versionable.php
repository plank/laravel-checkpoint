<?php

namespace Plank\Versionable\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Versionable\Models\Version;

trait Versionable
{
    public function bootVersionable() : void
    {
        static::saving(function (self $model) {
            // duplicate original, attach to version?
        });
    }

    public function versions() : MorphToMany
    {
        return $this->morphToMany(Version::class, 'versionable');
    }
}
