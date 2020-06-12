<?php

namespace Plank\Versionable;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Plank\Versionable\Skeleton\SkeletonClass
 */
class VersionableFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'versionable';
    }
}
