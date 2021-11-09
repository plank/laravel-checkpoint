<?php

namespace Plank\Checkpoint\Tests\Feature;

use Carbon\Carbon;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Timeline;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\TestCase;

class TimelinesTest extends TestCase
{
    /**
     * @test
     */
    public function it_only_shows_items_from_a_given_timeline()
    {
        $timelineB = factory(Timeline::class)->create(['title' => 'B']);
        $checkpoint2 = factory(Checkpoint::class)->create([
            'title' => '2.0',
            'checkpoint_date' => Carbon::now(),
            'timeline_id' => $timelineB->id
        ]);

        Checkpoint::setActive($checkpoint2);
        $b = factory(Post::class)->create();

        Carbon::setTestNow(Carbon::now()->subYear());
        $timelineA = factory(Timeline::class)->create(['title' => 'A']);
        $checkpoint1 = factory(Checkpoint::class)->create([
            'title' => '1.0',
            'checkpoint_date' => Carbon::now(),
            'timeline_id' => $timelineA->id
        ]);

        Checkpoint::setActive($checkpoint1);
        $a = factory(Post::class)->create();

        Carbon::setTestNow(Carbon::now()->addDay());
        $timelineC = factory(Timeline::class)->create(['title' => 'C']);
        $checkpoint3 = factory(Checkpoint::class)->create([
            'title' => '3.0',
            'checkpoint_date' => Carbon::now(),
            'timeline_id' => $timelineC->id
        ]);

        Checkpoint::setActive($checkpoint3);
        $c = factory(Post::class)->create();

        $this->assertEquals($a->getKey(), Post::at($checkpoint1)->first()->getKey());
        $this->assertEquals(1, Post::at($checkpoint1)->count());

        $this->assertEquals($b->getKey(), Post::at($checkpoint2)->first()->getKey());
        $this->assertEquals(1, Post::at($checkpoint2)->count());

        $this->assertEquals($c->getKey(), Post::at($checkpoint3)->first()->getKey());
        $this->assertEquals(1, Post::at($checkpoint3)->count());
    }

    /**
     * @test
     */
    public function timelines_can_list_their_related_checkpoints()
    {
        $timelineA = factory(Timeline::class)->create(['title' => 'A']);
        factory(Checkpoint::class, 3)->create([
            'timeline_id' => $timelineA->id
        ]);

        $this->assertEquals(3, $timelineA->checkpoints()->count());
    }
}
