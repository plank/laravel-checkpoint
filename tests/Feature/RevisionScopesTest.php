<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Tests\Support\Post;
use Plank\Checkpoint\Tests\TestCase;

class RevisionScopesTest extends TestCase
{

    /**
     * @test
     */
    public function lookup_visible_posts_at_a_date(): void
    {
        $before = now()->startOfMinute();
        $post = factory(Post::class)->create();
        $after = now()->endOfMinute();

        $this->assertEquals(0, Post::at($before)->count());
        $this->assertCount(1, Post::all());
        $this->assertEquals(1, Post::at($after)->count());
    }

    /**
     * @test
     */
    public function lookup_visible_posts_since_a_date(): void
    {
        $now = now()->startOfMinute();
        $post = factory(Post::class)->create();
        $after = now()->endOfMinute();

        $this->assertEquals(1, Post::since($now)->count());
        $this->assertEquals(0, Post::since($after)->count());
    }

    /**
     * @test
     */
    public function lookup_visible_posts_at_a_checkpoint(): void
    {
        $before = factory(Checkpoint::class)->create(['checkpoint_date' => now()->startOfDay()]);
        $post = factory(Post::class)->create();
        $now = factory(Checkpoint::class)->create(['checkpoint_date' => now()]);

        $this->assertEquals(0, Post::at($before)->count());
        $this->assertCount(1, Post::all()); // post is available as it is the latest revision
        $this->assertEquals(0, Post::at($now)->count()); // post isn't linked to checkpoint "now"

        $post->revision->checkpoint_id = $now->id;
        $post->push();
        $this->assertEquals(1, Post::at($now)->count());
    }

    /**
     * @test
     */
    public function lookup_visible_posts_since_a_checkpoint(): void
    {
        $before = factory(Checkpoint::class)->create(['checkpoint_date' => now()->startOfDay()]);
        $post = factory(Post::class)->create();
        $after = factory(Checkpoint::class)->create(['checkpoint_date' => now()->endOfMinute()]);

        $this->assertEquals(0, Post::since($before)->count());
        $this->assertEquals(0, Post::since($after)->count());

        $post->revision->checkpoint_id = $after->id;
        $post->push();

        $this->assertEquals(1, Post::since($before)->count());
        $this->assertEquals(0, Post::since($after)->count());
    }
}
