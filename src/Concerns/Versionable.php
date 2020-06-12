<?php

namespace Plank\Versionable\Concerns;

use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function previousVersion() : HasOne
    {
        // TODO: This is probably wrong, revise this.
        return $this->hasOne('versionable', 'previous_version_id', 'id');
    }
}
