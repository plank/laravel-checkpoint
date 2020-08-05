<?php

namespace Plank\Versionable\Tests;

use Orchestra\Testbench\TestCase;
use Plank\Checkpoint\CheckpointServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [CheckpointServiceProvider::class];
    }

    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
