<?php

namespace Plank\Checkpoint\Tests\Unit;

use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\TestCase;

class CheckpointModelTest extends TestCase
{
    /**
     * @test
     */
    public function current_checkpoint_is_latest_before_now(): void
    {
        $checkpoints = factory(Checkpoint::class, random_int(5, 25))->create();
        $from_query = Checkpoint::latest('checkpoint_date')->first();
        $current = Checkpoint::current();

        $this->assertEquals($from_query->id, $current->id);

        $tomorrow = factory(Checkpoint::class)->create(['checkpoint_date' => strtotime('tomorrow')]);
        $current = Checkpoint::current();

        $this->assertNotEquals($tomorrow->id, $current->id);
        $this->assertGreaterThan($current->checkpoint_date, $tomorrow->checkpoint_date);
    }

    /**
     * @test
     */
    public function previous_checkpoint_older_than_current(): void
    {
        $checkpoints = factory(Checkpoint::class, random_int(5, 25))->create();
        $from_query = Checkpoint::latest('checkpoint_date')->limit(2)->get()->last();
        $previous = Checkpoint::current()->previous();

        $this->assertEquals($from_query->id, $previous->id);
    }

    /**
     * @test
     */
    public function next_checkpoint_newer_than_current(): void
    {
        $checkpoints = factory(Checkpoint::class, random_int(5, 25))->create();
        $current = Checkpoint::current();

        $this->assertNull($current->next());

        $tomorrow = factory(Checkpoint::class)->create(['checkpoint_date' => strtotime('tomorrow')]);
        $next = $current->next();

        $this->assertNotNull($next);
        $this->assertEquals($tomorrow->id, $next->id);
        $this->assertGreaterThan($current->checkpoint_date, $next->checkpoint_date);
    }

    /**
     * @test
     */
    public function current_checkpoint_is_older_or_equals_to_now(): void
    {
        factory(Checkpoint::class, random_int(1,5))->create();

        $this->assertEquals(Checkpoint::current()->id, Checkpoint::olderThanEquals(now())->first()->id);
    }

    /**
     * @test
     */
    public function current_checkpoint_date_equals_to_itself(): void
    {
        factory(Checkpoint::class, random_int(1,5))->create();

        $current = Checkpoint::current();

        $this->assertEquals($current->id, Checkpoint::newerThanEquals($current)->first()->id);
        $this->assertEquals($current->id, Checkpoint::olderThanEquals($current)->first()->id);
    }

    /**
     * @test
     */
    public function checkpoint_has_revisions(): void
    {
        $checkpoint = factory(Checkpoint::class)->create();
        $post = factory(Post::class)->create();

        $this->assertFalse($post->revision->checkpoint()->exists());
        $this->assertFalse($checkpoint->revisions()->exists());
        $this->assertFalse($checkpoint->modelsOf(Post::class)->exists());
        $this->assertEmpty($checkpoint->models());

        $post->revision->checkpoint_id = $checkpoint->id;
        $post->push();
        //$checkpoint->checkpoint_date = now();
        //$checkpoint->save();

        $this->assertTrue($post->revision->checkpoint()->exists());
        $this->assertTrue($checkpoint->revisions()->exists());
        $this->assertTrue($checkpoint->modelsOf(Post::class)->exists());
        $this->assertCount(1, $checkpoint->models());

    }

}
