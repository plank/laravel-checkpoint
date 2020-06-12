<?php

namespace Plank\Versionable\Tests;

use Orchestra\Testbench\TestCase;
use Plank\Versionable\VersionableServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [VersionableServiceProvider::class];
    }
    
    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
