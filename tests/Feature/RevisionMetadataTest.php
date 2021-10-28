<?php

namespace Plank\Checkpoint\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\TestCase;

class RevisionMetadataTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('checkpoint.store_unique_columns_on_revision', true);
    }

    /**
     * the current revision doesn't need to keep unique columns in metadata,
     * these can stay in the original table
     * @test
     */
    public function current_revision_doesnt_keep_metadata(): void
    {
        $post = factory(Post::class)->create();

        $this->assertEmpty($post->revision->metadata);
        $this->assertEmpty($post->metadata);

        $post->title .= ' v2';
        $post->save();

        // stays empty even after a new revision is created
        $this->assertEmpty($post->revision->metadata);
        $this->assertEmpty($post->metadata);
    }

    /**
     * @test
     */
    public function revisions_store_metadata(): void
    {
        $post = factory(Post::class)->create();
        $post->title .= ' v2';
        $post->save();

        // the metadata on the revision matches the mutator on the revisionable
        $this->assertEquals($post->older->metadata, $post->revision->previous->metadata);

        // verify entries in the metadata are available as attributes on revisionable
        foreach ($post->getRevisionMeta() as $key) {
            $this->assertEquals($post->$key, $post->older->$key);
            $this->assertEquals($post->older->$key, $post->revision->previous->metadata[$key]);
            $this->assertContains($post->revision->previous->metadata[$key], $post->older->toArray());
        }
    }

    /**
     * @test
     */
    public function revisonable_without_revision_returns_empty_metadata()
    {
        $post = factory(Post::class)->create();

        $this->assertTrue($post->revision()->exists());

        $post->revision()->delete();

        $this->assertFalse($post->revision()->exists());
        $this->assertNull($post->revision);

        // stays the same, doesn't crash
        $this->assertEmpty($post->metadata);
    }

    /**
     * @test
     */
    public function revisionable_with_metadata_scope()
    {
        $post = factory(Post::class)->create();

        $this->assertArrayNotHasKey('metadata', $post->toArray());

        $post_with_meta = Post::withMetadata()->find($post->id);

        $this->assertArrayHasKey('metadata', $post_with_meta->toArray());
    }
}
