<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\Support\Models\Blog\Comment;
use Plank\Checkpoint\Tests\TestCase;

class RevisionTraitTest extends TestCase
{

    /**
     * @test
     */
    public function missing_revisions_get_recreated_for_any_existing_posts(): void
    {
        $post = factory(Post::class)->create();
        $original_revision = $post->revision;
        $post->revision()->delete();

        $this->assertEquals(0, Revision::count());

        $post->title .= ' v2';
        $post->save();

        $this->assertEquals(2, Revision::count());

    }

    /**
     * @test
     */
    public function children_of_revisioned_item_are_also_revisioned(): void
    {
        $original = factory(Post::class)->create();
        $comments = factory(Comment::class, 5)->create(['post_id' => $original->id]);

        $this->assertEquals(1, Post::withoutRevisions()->count());
        $this->assertEquals(5, Comment::withoutRevisions()->count());
        $this->assertEquals(6, Revision::count());

        $post = clone $original;
        $post->title .= ' v2';
        $post->save();

        $this->assertEquals(2, Post::withoutRevisions()->count());
        $this->assertEquals(10, Comment::withoutRevisions()->count());
        $this->assertEquals(12, Revision::count());

        $new_comments = Comment::whereIn('body', $comments->pluck('body'))->get();

        $this->assertEquals($comments->count(), $new_comments->count());
        $this->assertEquals($comments->count(), $new_comments->where('post_id', $post->id)->count());
        $this->assertEquals($comments->count(), $new_comments->map->older->where('post_id', $original->id)->count());
    }

    /**
     * @test
     */
    public function revisions_history_can_be_navigated(): void
    {
        $this->markTestIncomplete();
    }

    /**
     * @test
     */
    public function revisions_history_is_linear(): void
    {
        $this->markTestIncomplete();
    }

}
