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

        $this->setUpDatabase($this->app);
        $this->withFactories(__DIR__ . '/../database/factories');
        $this->withFactories(__DIR__.'/Support/factories');
    }

    /**
     * Get package providers.
     *
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

    protected function getEnvironmentSetUp($app)
    {
        config()->set('checkpoint.enabled', true);
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        $builder = $app['db']->connection()->getSchemaBuilder();

        $builder->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->index();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        $builder->create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->mediumText('body');
            $table->unsignedBigInteger('post_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('post_id')->references('id')->on('posts')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        include_once __DIR__.'/../database/migrations/2016_06_01_000000_create_checkpoints_table.php';
        (new \CreateCheckpointsTable())->up();

        include_once __DIR__.'/../database/migrations/2016_06_01_000001_create_revisions_table.php';
        (new \CreateRevisionsTable())->up();

        include_once __DIR__.'/../database/migrations/2021_09_07_000001_create_timelines_table.php';
        (new \CreateTimelinesTable())->up();

        include_once __DIR__.'/../database/migrations/2021_09_07_000002_add_timelines_to_checkpoints.php';
        (new \AddTimelinesToCheckpoints())->up();

        include_once __DIR__.'/../database/migrations/2021_09_07_000003_add_timelines_to_revisions.php';
        (new \AddTimelinesToRevisions())->up();
    }

    public function tearDown(): void
    {
        Checkpoint::clearActive();

        parent::tearDown();
    }
}