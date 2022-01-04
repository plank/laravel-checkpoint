<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\TestCase;

class RevisionableRelationsTest extends TestCase
{
    /**
     * @test
     */
    public function new_revisionable_has_revision(): void
    {
        $post = factory(Post::class)->create();
        $revision = Revision::first();

        $this->assertEquals($revision->id, $post->revision->id);
        $this->assertEquals($revision->revisionable_id, $post->id);
        $this->assertEquals($revision->original_revisionable_id, $post->id);
    }

    /**
     * @test
     */
    public function all_revisionables_have_revision(): void
    {
        $posts = factory(Post::class, 5)->create();

        $this->assertCount($posts->count(), $posts->filter(function ($post) { return $post->revision()->exists(); }));
    }

    /**
     * @test
     */
    public function retrieve_initial_revisionable_from_any_revision(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $original_id = $post->id;

        $post->performRevision();
        $revision2 = $post->revision;

        $this->assertTrue($post->initial()->exists());
        $this->assertEquals($original_id, Post::withInitial()->find($post->id)->older->id);

        $this->assertEquals($post->id, $revision2->revisionable_id);
        $this->assertEquals($post->initial->id, $revision1->revisionable_id);
    }

    /**
     * @test
     */
    public function retrieve_previous_revisionable_from_any_revision(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $original_id = $post->id;

        $post->performRevision();
        $revision2 = $post->revision;

        $this->assertTrue($post->older()->exists());
        $this->assertEquals($original_id, Post::withPrevious()->find($post->id)->older->id);

        $this->assertEquals($post->id, $revision2->revisionable_id);
        $this->assertEquals($post->older->id, $revision1->revisionable_id);
    }

    /**
     * @test
     */
    public function retrieve_next_revisionable_if_available(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $original = clone $post;

        $this->assertFalse($original->newer()->exists());
        $this->assertFalse($post->newer()->exists());

        $post->performRevision();
        $revision2 = $post->revision;

        $this->assertEquals($post->id, Post::withoutRevisions()->withNext()->find($original->id)->newer->id);

        $this->assertEquals($original->id, $revision1->revisionable_id);
        $this->assertEquals($post->id, $revision2->revisionable_id);
    }

    /**
     * @test
     */
    public function retrieve_newest_revisionable(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $original = clone $post;

        $original_newest_id = $original->newest()->first()->id;
        $this->assertEquals($post->id, $original_newest_id);

        $post->performRevision();
        $revision2 = $post->revision;
        $this->assertEquals($post->id, Post::withNewest()->find($post->id)->newest->id);
        $this->assertNotEquals($original_newest_id, Post::withNewest()->find($post->id)->newest->id);

        $this->assertEquals($original->id, $revision1->revisionable_id);
        $this->assertEquals($post->id, $revision2->revisionable_id);
    }

    /**
     * @test
     */
    public function retrieve_all_revisions_from_revisionable(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $post->performRevision();
        $revision2 = $post->revision;

        $this->assertEquals(2, $post->revisions()->count());
        $this->assertContains($revision1->id, $post->revisions->pluck('id'));
        $this->assertContains($revision2->id, $post->revisions->pluck('id'));
    }

    /**
     * @test
     */
    public function retrieve_checkpoint_from_revisionable(): void
    {
        $checkpoint = factory(Checkpoint::class)->create();
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $post->performRevision();
        $revision2 = $post->revision;
        $original_post = $post->initial;

        $revision1->checkpoint_id = $checkpoint->id;
        $revision1->save();

        $this->assertFalse($post->checkpoint()->exists());
        $this->assertEquals($checkpoint->id, $original_post->checkpoint->id);
    }

    /**
     * @test
     */
    public function retrieve_all_checkpoints_where_revisionable_was_modified(): void
    {
        $c1 = factory(Checkpoint::class)->create();
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $revision1->checkpoint_id = $c1->id;
        $revision1->save();

        $c2 = factory(Checkpoint::class)->create();
        $post->performRevision();
        $original_post = $post->initial;
        $revision2 = $post->revision;
        $revision2->checkpoint_id = $c2->id;
        $revision2->save();

        $this->assertEquals(2, $post->checkpoints()->count());
        $this->assertEquals(2, $original_post->checkpoints()->count());
        $this->assertContains($c1->id, $post->checkpoints->pluck('id'));
        $this->assertContains($c2->id, $post->checkpoints->pluck('id'));
        $this->assertContains($c1->id, $original_post->checkpoints->pluck('id'));
        $this->assertContains($c2->id, $original_post->checkpoints->pluck('id'));
    }

}
