<?php

namespace Plank\Checkpoint;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Plank\Checkpoint\Commands\StartRevisioning;

class CheckpointServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'checkpoint');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'checkpoint');
        //$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {

            // Publish configuration files
            $this->publishes([
                __DIR__.'/../config/checkpoint.php' => config_path('checkpoint.php'),
            ], 'config');

            // Publish extendable models
            $this->publishes([
                __DIR__.'/../src/Models/Checkpoint.php' => base_path('app/Checkpoint.php'),
                __DIR__.'/../src/Models/Revision.php' => base_path('app/Revision.php'),
            ], 'models');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/checkpoint'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/checkpoint'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/checkpoint'),
            ], 'lang');*/

            // Registering package commands.
            $this->commands([
                StartRevisioning::class,
            ]);
        }

        foreach (File::glob(__DIR__ . '/../database/migrations/*') as $migration) {
            $basename = strstr($migration, 'create');
            if (empty(File::glob(database_path('migrations/*' . $basename)))) {
                $timestamp = date('Y_m_d_His');
                $publish = database_path("migrations/{$timestamp}_{$basename}");

                $this->publishes([$migration => $publish], 'migrations');
            }
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/checkpoint.php', 'checkpoint');
    }
}
