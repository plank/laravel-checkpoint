<?php

namespace Plank\Checkpoint\Helpers;

use SplFileObject;
use ReflectionMethod;
use ReflectionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Helper Class to reliably get all existing relations on a model, not just eager-loaded ones
 *
 * Originally from neurony/laravel-duplicate package, but that package is now abandoned
 * Improved to cache a model's relations in-memory to speed up recurring calls
 */
class RelationHelper
{
    /**
     * List of all relations defined on each parsed model class.
     *
     * @var array
     */
    protected static $relations = [];

    /**
     * Laravel's available relation types (classes|methods).
     *
     * @var array
     */
    protected static $relationTypes = [
        'hasOne',
        'hasMany',
        'hasManyThrough',
        'belongsTo',
        'belongsToMany',
        'morphOne',
        'morphMany',
        'morphTo',
        'morphToMany',
    ];

    /**
     * All available Laravel's pivoted relations.
     *
     * @var array
     */
    protected static $pivotedRelations = [
        BelongsToMany::class,
        MorphToMany::class,
    ];

    /**
     * All available Laravel's direct parent relations.
     *
     * @var array
     */
    protected static $parentRelations = [
        BelongsTo::class,
        MorphTo::class,
    ];
    /**
     * All available Laravel's direct single child relations.
     *
     * @var array
     */
    protected static $childRelationsSingle = [
        HasOne::class,
        MorphOne::class,
    ];

    /**
     * All available Laravel's direct multiple children relations.
     *
     * @var array
     */
    protected static $childRelationsMultiple = [
        HasMany::class,
        MorphMany::class,
    ];

    /**
     * Verify if a given relation is direct or not.
     *
     * @param string $relation
     * @return bool
     */
    public static function isDirect(string $relation): bool
    {
        return self::isChild($relation) || self::isParent($relation);
    }

    /**
     * Verify if a given relation is pivoted or not.
     *
     * @param string $relation
     * @return bool
     */
    public static function isPivoted(string $relation): bool
    {
        return in_array($relation, static::$pivotedRelations);
    }

    /**
     * Verify if a given direct relation is of type parent.
     *
     * @param string $relation
     * @return bool
     */
    public static function isParent(string $relation): bool
    {
        return in_array($relation, static::$parentRelations);
    }

    /**
     * Verify if a given direct relation is of type child.
     *
     * @param string $relation
     * @return bool
     */
    public static function isChild(string $relation): bool
    {
        return self::isChildSingle($relation) || self::isChildMultiple($relation);
    }

    /**
     * Verify if a given direct relation is of type single child.
     * Ex: hasOne, morphOne.
     *
     * @param string $relation
     * @return bool
     */
    public static function isChildSingle(string $relation): bool
    {
        return in_array($relation, static::$childRelationsSingle);
    }

    /**
     * Verify if a given direct relation is of type single child.
     * Ex: hasMany, morphMany.
     *
     * @param string $relation
     * @return bool
     */
    public static function isChildMultiple(string $relation): bool
    {
        return in_array($relation, static::$childRelationsMultiple);
    }

    /**
     * Get all the defined model class relations.
     * Not just the eager loaded ones present in the $relations Eloquent property.
     *
     * @param Model $model
     * @param bool $refresh
     * @return array
     * @throws ReflectionException
     */
    public static function getModelRelations(Model $model, $refresh = false): array
    {
        $class = get_class($model);
        // Check if the relations on this model were already parsed
        if (!$refresh && array_key_exists($class, static::$relations)) {
            return static::$relations[$class];
        }

        static::$relations[$class] = [];

        foreach (get_class_methods($model) as $method) {
            if (method_exists(Model::class, $method)) {
                continue; // when a method exists it can't be a relation
            }

            $reflection = new ReflectionMethod($model, $method);
            $file = new SplFileObject($reflection->getFileName());
            $code = '';

            $file->seek($reflection->getStartLine() - 1);

            while ($file->key() < $reflection->getEndLine()) {
                $code .= $file->current();
                $file->next();
            }

            $code = trim(preg_replace('/\s\s+/', '', $code));
            $begin = strpos($code, 'function(');
            $code = substr($code, $begin, strrpos($code, '}') - $begin + 1);

            foreach (static::$relationTypes as $type) {
                if (stripos($code, '$this->'.$type.'(')) {
                    $relation = $model->$method();

                    if ($relation instanceof Relation) {
                        static::$relations[$class][$method] = [
                            'type' => get_class($relation),
                            'model' => $relation->getRelated(),
                            'original' => $relation->getParent(),
                        ];
                    }
                }
            }
        }

        return static::$relations[$class];
    }
}