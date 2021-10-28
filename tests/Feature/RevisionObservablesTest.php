<?php

namespace Plank\Checkpoint\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\TestCase;

class RevisionObservablesTest extends TestCase
{

    /**
     * @test
     */
    public function created_post_has_revision(): void
    {
        $this->assertEquals(0, Post::count());
        $this->assertEquals(0, Revision::count());

        $post = factory(Post::class)->create();

        $this->assertEquals(1, Post::count());
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

        $this->assertArrayHasKey('id', $post->getChanges());
        $this->assertArrayHasKey('title', $post->getChanges());
    }

    /**
     * @test
     */
    public function deleted_posts_are_revisioned(): void
    {
        $post = factory(Post::class)->create();
        $original = collect($post->getAttributes());
        $this->assertCount(1, Post::all());

        $post->delete();

        $this->assertCount(0, Post::all());
        $this->assertEquals(1, Post::withTrashed()->count());
        $this->assertEquals(2, Post::withTrashed()->withoutRevisions()->count());

        // new revision matches old one in everything but id and date columns
        $changed = collect($post->getAttributes());
        $this->assertNotEquals($post->deleted_at, $original->get('deleted_at'));
        $this->assertEmpty($original->diffAssoc($changed)->except('id', ...$post->getDates()));
        // checking that changed columns are listed corrected
        $this->assertEquals(['id', 'deleted_at'], array_keys($post->getChanges()));
    }

    /**
     * @test
     */
    public function restored_posts_are_revisioned(): void
    {
        $post = factory(Post::class)->create();
        $this->assertCount(1, Post::all());

        $post->delete();
        $original = collect($post->getAttributes());
        $post->restore();

        $this->assertCount(1, Post::all());
        $this->assertEquals(2, Post::withoutRevisions()->count());
        $this->assertEquals(3, Post::withTrashed()->withoutRevisions()->count());

        // new revision matches old one in everything but id and the deleted_at
        $changed = collect($post->getAttributes());
        $this->assertNotEquals($post->deleted_at, $original->get('deleted_at'));
        $this->assertEmpty($original->diffAssoc($changed)->except('id', ...$post->getDates()));
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
        // create a post and update it twice to get 3 revisions total
        $post = factory(Post::class)->create();

        $original = clone $post;
        $post->title .= ' v2';
        $post->save();
        $to_delete = clone $post;

        $post->title .= ' v3';
        $post->save();

        $this->assertCount(1, Post::all());
        $this->assertCount(3, Revision::all());

        // delete the v2 post
        $to_delete->forceDelete();
        $r1 = $original->revision()->first();
        $r3 = $post->revision()->first();
        // should link v3 back to v1 now that v2 is gone
        $this->assertCount(2, Post::withoutRevisions()->get());
        $this->assertCount(2, Revision::all());
        $this->assertEquals($r1->id, $r3->previous_revision_id);
        $this->assertEquals($r1->next->id, $r3->id);
        $this->assertEquals($r3->previous->id, $r3->previous_revision_id);
        $this->assertEquals($r3->previous->id, $r1->id);
        // delete v3, v1 should be latest and only revision
        $post->forceDelete();
        $r1->refresh();
        $this->assertEquals(0, $r1->next()->count());
        $this->assertNull($r1->previous_revision_id);
        $this->assertCount(1, Post::withoutRevisions()->get());
        $this->assertCount(1, Revision::all());
    }

    /**
     * @test
     */
    public function ignores_revisionable_events_if_checkpoint_is_disabled(): void
    {
        Config::set('checkpoint.enabled', false);
        Config::set('checkpoint.apply_global_scope', false);

        $this->assertEquals(0, Post::count());
        $this->assertEquals(0, Revision::count());

        $post = factory(Post::class)->create();
        $this->assertEquals(1, Post::count());
        $this->assertEquals(0, Revision::count());

        $post->title = 'v2';
        $post->save();
        $this->assertEquals(1, Post::count());
        $this->assertEquals(0, Revision::count());

        $post->delete();
        $this->assertEquals(0, Post::count());
        $this->assertEquals(1, Post::withTrashed()->count());
        $this->assertEquals(0, Revision::count());

        $post->restore();
        $this->assertEquals(1, Post::count());
        $this->assertEquals(0, Revision::count());

        $post->forceDelete();
        $this->assertEquals(0, Post::withTrashed()->count());
        $this->assertEquals(0, Revision::count());
    }

}
