<?php

namespace Plank\Checkpoint\Tests\Feature;

use Carbon\Carbon;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Timeline;
use Plank\Checkpoint\Tests\Support\Post;
use Plank\Checkpoint\Tests\TestCase;

class CheckpointObserverTest extends TestCase
{
    /**
     * @test
     */
    public function it_changes_revision_timelines_when_checkpoint_timeline_changes(): void
    {
        $timelineA = factory(Timeline::class)->create(['title' => 'A']);
        $checkpoint1 = factory(Checkpoint::class)->create([
            'title' => '1.0',
            'checkpoint_date' => Carbon::now()->subDay(),
            'timeline_id' => $timelineA->id
        ]);

        Checkpoint::setActive($checkpoint1);
        factory(Post::class, 3)->create();

        $timelineB = factory(Timeline::class)->create(['title' => 'B']);
        $checkpoint2 = factory(Checkpoint::class)->create([
            'title' => '2.0',
            'checkpoint_date' => Carbon::now(),
            'timeline_id' => $timelineB->id
        ]);

        Checkpoint::setActive($checkpoint2);
        factory(Post::class, 3)->create();

        $this->assertEquals(3, Post::query()->count());
        
        // Changing the checkpoints timeline should cascade to the associated revisions
        $checkpoint2->timeline_id = $timelineA->id;
        $checkpoint2->save();
        $this->assertEquals(0, Revision::query()->where('timeline_id', $timelineB->id)->count());
        $this->assertEquals(6, Post::query()->count());

        Checkpoint::setActive($checkpoint1);
        $this->assertEquals(3, Post::query()->count());
    }

    /**
     * @test
     */
    public function when_checkpoint_is_deleted_it_removes_associated_revisions_checkpoint_and_timeline(): void
    {
        $timelineA = factory(Timeline::class)->create(['title' => 'A']);
        $checkpoint1 = factory(Checkpoint::class)->create([
            'title' => '1.0',
            'checkpoint_date' => Carbon::now()->subDay(),
            'timeline_id' => $timelineA->id
        ]);

        Checkpoint::setActive($checkpoint1);
        factory(Post::class, 3)->create();

        $timelineB = factory(Timeline::class)->create(['title' => 'B']);
        $checkpoint2 = factory(Checkpoint::class)->create([
            'title' => '2.0',
            'checkpoint_date' => Carbon::now(),
            'timeline_id' => $timelineB->id
        ]);

        Checkpoint::setActive($checkpoint2);
        factory(Post::class, 3)->create();
        $this->assertEquals(3, Post::query()->count());

        // Changing the checkpoints timeline should cascade to the associated revisions and unset the 
        $checkpoint2->delete();
        $this->assertNull(Checkpoint::active());
        $this->assertEquals(0, Post::at($checkpoint2)->count());
        $this->assertEquals(3, Post::at($checkpoint1)->count());
        $this->assertEquals(3, Post::at(null)->count());
    }
}
