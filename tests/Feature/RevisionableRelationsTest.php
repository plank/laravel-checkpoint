<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Tests\Support\Post;
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
    public function retrieve_initial_revisionable_from_any_revision(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $post->saveAsRevision();
        $revision2 = $post->revision;

        $this->assertNotEquals($revision1->id, $revision2->id);
        $this->assertEquals($post->revision->id, $revision2->id);
        $this->assertEquals($post->initial->id, $revision1->revisionable_id);
    }

    /**
     * @test
     */
    public function retrieve_previous_revisionable_from_any_revision(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $post->saveAsRevision();
        $revision2 = $post->revision;

        $this->assertEquals($post->id, $revision2->revisionable_id);
        $this->assertEquals($post->older->id, $revision1->revisionable_id);
    }

    /**
     * @test
     */
    public function retrieve_all_revisions_from_revisionable(): void
    {
        $post = factory(Post::class)->create();
        $revision1 = $post->revision;
        $post->saveAsRevision();
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
        $post->saveAsRevision();
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
        $post->saveAsRevision();
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
