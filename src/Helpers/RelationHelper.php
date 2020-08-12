<?php

namespace Plank\Checkpoint\Helpers;

use Neurony\Duplicate\Helpers\RelationHelper as BaseRelationHelper;
use Illuminate\Database\Eloquent\Model;
use ReflectionException;

class RelationHelper extends BaseRelationHelper
{

    /**
     * Get all the defined model class relations.
     * Not just the eager loaded ones present in the $relations Eloquent property.
     *
     * @param Model $model
     * @return array
     * @throws ReflectionException
     */
    public static function getModelRelations(Model $model): array
    {
        static::$relations = [];

        return parent::getModelRelations($model);
    }
}
