<?php

namespace Plank\Checkpoint\Tests\Feature;

use Plank\Checkpoint\Models\Revision;
use Plank\Checkpoint\Models\Checkpoint;
use Plank\Checkpoint\Tests\Support\Models\CMS\Block;
use Plank\Checkpoint\Tests\Support\Models\CMS\Page;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\Support\Models\Blog\Comment;
use Plank\Checkpoint\Tests\Support\Models\Servers\Server;
use Plank\Checkpoint\Tests\Support\Models\Servers\Group;
use Plank\Checkpoint\Tests\Support\Models\Servers\Environment;
use Plank\Checkpoint\Tests\Support\Models\Servers\Cluster;
use Plank\Checkpoint\Tests\TestCase;

class RevisionChildrenTest extends TestCase
{

    /**
     * @test
     */
    public function it_revisions_single_child_relations(): void
    {
        $environment = factory(Environment::class)->create();
        factory(Server::class)->create(['environment_id' => $environment->id]);

        $this->assertEquals(2, Revision::count());
        $this->assertContains($environment->id, $environment->servers->pluck('id'));

        $environment->title = 'v2';
        $environment->save();
        $this->assertEquals(4, Revision::count());
    }

    /**
     * @test
     */
    public function it_revisions_many_child_relations(): void
    {
        // we create 1 group and 3 child environments
        $group = factory(Group::class)->create();
        $environments = factory(Environment::class, 3)->create([
            'group_id' => $group->id,
            'subgroup_id' => null,
        ]);
        $this->assertEquals(4, Revision::count());

        // after update, we expect all children to also get revisioned
        $group->title = 'v2';
        $group->save();
        $this->assertEquals(8, Revision::count());
    }

    /**
     * @test
     */
    public function it_doesnt_revision_parent_relations(): void
    {
        $group = factory(Group::class)->create();
        $subgroup = factory(Group::class)->create(['parent_id' => $group]);

        $this->assertEquals(2, Revision::count());

        $subgroup->title = 'v2';
        $subgroup->save();

        $this->assertEquals(1, $group->revisions()->count());
        $this->assertEquals(2, $subgroup->revisions()->count());
        $this->assertEquals(3, Revision::count());
    }

    /**
     * @test
     */
    public function it_revisions_morphed_child_relations(): void
    {
        $page = factory(Page::class)->create();
        $blocks = factory(Block::class, 3)->create(['page_id' => $page->id]);

        $environment = factory(Environment::class)->create();
        $server = factory(Server::class)->create(['environment_id' => $environment->id]);

        $callout1 = factory(Block::class)->create([
            'page_id' => $page->id,
            'calloutable_id' => $server->id,
            'calloutable_type' => Server::class,
        ]);

/*        $callout2 = factory(Block::class)->create([
            'page_id' => $page->id,
            'calloutable_id' => $server->id,
            'calloutable_type' => server::class,
        ]);*/

        $this->assertEquals(7, Revision::count());
        $this->assertEquals(1, $callout1->revisions()->count());
        //$this->assertEquals(1, $callout2->revisions()->count());

        $server->title = 'v2';
        $server->save();

        $this->assertEquals(9, Revision::count());
        $this->assertEquals(2, $callout1->revisions()->count());
        //$this->assertEquals(2, $callout2->revisions()->count());
    }


    /**
     * @test
     */
    public function it_duplicates_pivot_relations(): void
    {
        $cluster = factory(Cluster::class)->create();
        $environment = factory(Environment::class)->create();
        $server = factory(Server::class)->create(['environment_id' => $environment->id]);
        $server->clusters()->attach($cluster);

        $this->assertEquals(3, Revision::count());
        $this->assertEquals(1, $server->clusters()->count());
        $this->assertEquals(1, $cluster->servers()->count());

        $environment->title .= 'v2';
        $environment->save();

        $this->assertEquals(5, Revision::count());
        $this->assertEquals(1, $server->clusters()->count());
        $this->assertEquals(1, $cluster->servers()->count());
        $this->assertEquals(2, $cluster->servers()->withoutGlobalScopes()->count());
        $this->assertTrue($cluster->is($server->clusters()->first()));
        $this->assertTrue($cluster->is($server->newer->clusters()->first()));
    }

}
