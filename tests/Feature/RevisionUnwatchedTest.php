<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Tests\Support\Models\CMS\Block;
use Plank\Checkpoint\Tests\Support\Models\CMS\Page;
use Plank\Checkpoint\Tests\TestCase;

class RevisionUnwatchedTest extends TestCase
{

    /**
     * @test
     */
    public function it_doesnt_revision_when_only_unwatched_fields_are_updated(): void
    {
        $block = factory(Block::class)->create(['status' => 'draft']);
        $block->ignore(['status']);

        $block->status = 'published';
        $block->save();

        $this->assertEquals(1, $block->revisions()->count());
        $this->assertEquals(1, Page::withoutRevisions()->count());
    }

    /**
     * @test
     */
    public function it_copies_even_unwatched_fields_when_deleting(): void
    {
        /**
         * @var $block Block
         */
        $block = factory(Block::class)->create(['status' => 'published']);

        $block->delete();

        $this->assertEquals(2, $block->revisions()->count());
        $this->assertEquals('published', $block->status);
    }

    /**
     * @test
     */
    public function updated_children_can_preserve_normally_unwatched_changes_revisions(): void
    {
        $page = factory(Page::class)->create();
        $block = factory(Block::class)->create([
            'status' => 'published',
            'page_id' => $page->getKey(),
        ]);

        $page->title .= ' v2';
        $page->save();
        $this->assertNotEquals($block->getKey(), $page->blocks()->first()->getKey());
    }


}
