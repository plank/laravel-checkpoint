<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Tests\Support\Models\CMS\Block;
use Plank\Checkpoint\Tests\TestCase;

class RevisionEventsTest extends TestCase
{

    /**
     * @test
     */
    public function can_register_callbacks_to_revisioning_event(): void
    {
        $block = factory(Block::class)->create(['status' => 'draft']);
        // force revisioning to be skipped
        $block::revisioning(function () {
            return false;
        });

        $block->status = 'published';
        $block->save();

        $this->assertEquals(1, $block->revisions()->count());
        $this->assertEquals(1, Block::withoutRevisions()->count());
    }

    /**
     * @test
     */
    public function can_register_callbacks_to_revisioned_event(): void
    {
        $block = factory(Block::class)->create(['status' => 'draft']);
        // force a status change after the revisioning has completed
        $block::revisioned(function (Block $block) {
            $block->status = 'reset';
        });

        $block->status = 'published';
        $block->save();

        $this->assertEquals(2, $block->revisions()->count());
        $this->assertEquals(2, Block::withoutRevisions()->count());
    }


}
