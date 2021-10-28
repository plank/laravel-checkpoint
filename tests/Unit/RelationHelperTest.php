<?php

namespace Plank\Checkpoint\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Plank\Checkpoint\Helpers\RelationHelper;
use Plank\Checkpoint\Tests\TestCase;

class RelationHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        RelationHelper::resetRelationTypes();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function can_add_to_relation_types(): void
    {
        // access protected static properties, kind of like reflection but quicker & concise
        $helper = resolve(RelationHelper::class);
        $helper = \Closure::bind(function($prop){return static::$$prop;}, $helper, $helper);

        $add = 'fancyNewRelationType';
        $this->assertNotContains($add, $helper('relationTypes'));
        RelationHelper::addRelationType($add);
        $this->assertContains($add, $helper('relationTypes'));
    }

    /**
     * @test
     */
    public function can_add_multiple_relation_types(): void
    {
        // access protected static properties, kind of like reflection but quicker & concise
        $helper = resolve(RelationHelper::class);
        $helper = \Closure::bind(function($prop){return static::$$prop;}, $helper, $helper);
        $originalTypes = $helper('relationTypes');
        $types = ['a','b','c'];

        $this->assertNotEquals($types, $originalTypes);
        RelationHelper::addRelationTypes(['a','b','c']);
        $this->assertEquals(array_merge($originalTypes, $types), $helper('relationTypes'));
    }

    /**
     * @test
     */
    public function can_reset_relation_types_to_laravel_default(): void
    {
        // access protected static properties, kind of like reflection but quicker & concise
        $helper = resolve(RelationHelper::class);
        $helper = \Closure::bind(function($prop){return static::$$prop;}, $helper, $helper);
        $originalTypes = $helper('relationTypes');
        $types = ['a','b','c'];

        $this->assertNotEquals($types, $originalTypes);
        RelationHelper::addRelationTypes(['a','b','c']);
        $this->assertNotEquals($originalTypes, $helper('relationTypes'));
        RelationHelper::resetRelationTypes();
        $this->assertEquals($originalTypes, $helper('relationTypes'));
    }

}
