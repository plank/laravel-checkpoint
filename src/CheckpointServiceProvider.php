<?php

namespace Plank\Checkpoint;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Plank\Checkpoint\Commands\StartRevisioning;
use Plank\Checkpoint\Contracts\CheckpointStore;

class CheckpointServiceProvider extends ServiceProvider
{
    protected $models = [
        \Plank\Checkpoint\Models\Revision::class,
        \Plank\Checkpoint\Models\Checkpoint::class,
        \Plank\Checkpoint\Models\Timeline::class,
    ];

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration files
            $this->publishes([
                __DIR__.'/../config/checkpoint.php' => config_path('checkpoint.php'),
            ], 'config');

            // dynamically publish all checkpoint migrations
            foreach (File::glob(__DIR__ . '/../database/migrations/*') as $migration) {
                $basename = strstr($migration, 'create');
                if (empty(File::glob(database_path('migrations/*' . $basename)))) {
                    $this->publishes([
                        $migration => database_path("migrations/". date('Y_m_d_His') . "_" . $basename)
                    ], 'migrations');
                }
            }

            // Load default migrations if the runs_migrations toggle is true in the config (or if any of the expected files is missing)
            if (config('checkpoint.run_migrations')) {
                $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            }

            // Publish extendable models
            $this->publishes([
                __DIR__.'/../src/Models/Checkpoint.php' => base_path('app/Checkpoint.php'),
                __DIR__.'/../src/Models/Revision.php' => base_path('app/Revision.php'),
            ], 'models');

            // Registering package commands.
            $this->commands(StartRevisioning::class);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/checkpoint.php', 'checkpoint');

        $this->registerModels();

        $this->registerCheckpointStore();
    }

    /**
     * Bind user-defined models from config to corresponding package models
     */
    public function registerModels()
    {
        $config = config('checkpoint.models');

        foreach ($this->models as $model) {
            $key = lcfirst(substr($model, strrpos($model, '\\') + 1));
            $this->app->bind($model, function () use ($config, $key) {
                return new $config[$key];
            });
        }
    }

    /**
     * Register a concrete implementation of a CheckpointStore
     */
    public function registerCheckpointStore()
    {
        $this->app->singleton(CheckpointStore::class, function () {
            /** @var class-string<CheckpointStore> $storeClass */
            $storeClass = config('checkpoint.store');
            return new $storeClass;
        });
    }
}
