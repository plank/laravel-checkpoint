<?php

namespace Plank\Checkpoint\Tests\Unit;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    public function can_merge_relation_types_with_laravel_default(): void
    {
        // access protected static properties, kind of like reflection but quicker & concise
        $helper = resolve(RelationHelper::class);
        $helper = \Closure::bind(function($prop){return static::$$prop;}, $helper, $helper);
        $originalTypes = $helper('relationTypes');
        $types = ['a','b','c'];

        $this->assertNotEquals($types, $originalTypes);
        RelationHelper::mergeRelationTypes(['a','b','c']);
        $this->assertEquals(array_merge($originalTypes, ['a','b','c']), $helper('relationTypes'));
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

    /**
     * @test
     */
    public function can_verify_direct_relations(): void
    {
        $this->assertFalse(RelationHelper::isChild(BelongsTo::class));
        $this->assertTrue(RelationHelper::isChild(HasOne::class));

        $this->assertTrue(RelationHelper::isDirect(BelongsTo::class));
        $this->assertTrue(RelationHelper::isDirect(HasOne::class));
        $this->assertFalse(RelationHelper::isDirect(BelongsToMany::class));
    }

    /**
     * @test
     */
    public function can_verify_child_relations(): void
    {
        $this->assertTrue(RelationHelper::isChildSingle(HasOne::class));
        $this->assertFalse(RelationHelper::isChildMultiple(HasOne::class));

        $this->assertTrue(RelationHelper::isChildMultiple(HasMany::class));
        $this->assertFalse(RelationHelper::isChildSingle(HasMany::class));

        $this->assertTrue(RelationHelper::isChild(HasOne::class));
        $this->assertTrue(RelationHelper::isChild(HasMany::class));
        $this->assertFalse(RelationHelper::isChild(BelongsTo::class));
        $this->assertFalse(RelationHelper::isChild(BelongsToMany::class));
    }

    /**
     * @test
     */
    public function can_retrieve_all_pivot_relations(): void
    {
        $this->assertTrue(RelationHelper::isPivoted(BelongsToMany::class));
        $this->assertFalse(RelationHelper::isPivoted(HasOne::class));
        $this->assertFalse(RelationHelper::isPivoted(HasMany::class));
        $this->assertFalse(RelationHelper::isPivoted(BelongsTo::class));
    }

}
