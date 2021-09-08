<?php

namespace Plank\Checkpoint\Tests;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Plank\Checkpoint\CheckpointServiceProvider;
use Plank\Checkpoint\Models\Checkpoint;

abstract class TestCase extends Orchestra
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->withFactories(__DIR__ . '/../database/factories');
        $this->withFactories(__DIR__ . '/Support/factories');
    }

    /**
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(['--database' => 'sqlite']);
        $this->loadMigrationsFrom([
            '--path' => __DIR__ . '/Support/migrations',
            '--database' => 'sqlite'
        ]);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('checkpoint.enabled', true);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            CheckpointServiceProvider::class
        ];
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        Checkpoint::clearActive();

        parent::tearDown();
    }
}
