<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Tests\Support\Post;
use Plank\Checkpoint\Tests\TestCase;

class RevisionObservablesTest extends TestCase
{

    /**
     * @test
     */
    public function created_post_has_revision(): void
    {
        $post = factory(Post::class)->create();
        $this->assertCount(1, Post::all());

        $revisions = Revision::latest()->get();
        $this->assertCount(1, $revisions);
        $this->assertEquals($post->id, $revisions->first()->revisionable_id);
        $this->assertEquals($post->id, $post->revision->revisionable_id);
    }

    /**
     * @test
     */
    public function updated_posts_are_revisioned(): void
    {
        $post = factory(Post::class)->create();
        $this->assertCount(1, Post::all());

        $post->title .= ' v2';
        $post->save();

        $this->assertCount(1, Post::all());
        $this->assertEquals(2, Post::withoutRevisions()->count());
    }

    /**
     * @test
     */
    public function deleted_posts_are_revisioned(): void
    {
        $post = factory(Post::class)->create();
        $this->assertCount(1, Post::all());

        $post->delete();

        $this->assertCount(0, Post::all());
        $this->assertEquals(1, Post::withTrashed()->count());
        $this->assertEquals(2, Post::withTrashed()->withoutRevisions()->count());
    }

    /**
     * @test
     */
    public function restored_posts_are_revisioned(): void
    {
        $post = factory(Post::class)->create();
        $this->assertCount(1, Post::all());

        $post->delete();
        $post->restore();

        $this->assertCount(1, Post::all());
        $this->assertEquals(2, Post::withoutRevisions()->count());
        $this->assertEquals(3, Post::withTrashed()->withoutRevisions()->count());
    }

    /**
     * @test
     */
    public function force_deleted_posts_remove_item_and_revision(): void
    {
        $post = factory(Post::class)->create();
        $this->assertCount(1, Post::all());
        $this->assertCount(1, Revision::all());

        $post->forceDelete();

        $this->assertCount(0, Post::all());
        $this->assertCount(0, Revision::all());
        $this->assertEquals(0, Post::withoutGlobalScopes()->count());
    }

    /**
     * @test
     */
    public function force_deleted_posts_preserves_revision_history(): void
    {
        $post = factory(Post::class)->create();

        $original = clone $post;
        $post->title .= ' v2';
        $post->save();
        $to_delete = clone $post;

        $post->title .= ' v3';
        $post->save();

        $this->assertCount(1, Post::all());
        $this->assertCount(3, Revision::all());

        $to_delete->forceDelete();
        $r1 = $original->revision()->first();
        $r3 = $post->revision()->first();

        $this->assertCount(2, Post::withoutRevisions()->get());
        $this->assertCount(2, Revision::all());
        $this->assertEquals($r1->id, $r3->previous_revision_id);
    }

}
