<?php

namespace Plank\Checkpoint;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Plank\Checkpoint\Commands\StartRevisioning;

class VersionableServiceProvider extends ServiceProvider
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
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {

            $this->commands([
                StartRevisioning::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/checkpoint.php' => config_path('checkpoint.php'),
            ], 'config');

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
            // $this->commands([]);
        }


        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
/*        if (empty(File::glob(database_path('migrations/*_create_checkpoints_table.php')))) {
            $timestamp = date('Y_m_d_His');
            $migration = database_path("migrations/{$timestamp}_create_checkpoints_table.php");

            $this->publishes([
                __DIR__.'/../database/migrations/create_checkpoints_table.php.stub' => $migration,
            ], 'migrations');
        }*/

/*        if (empty(File::glob(database_path('migrations/*_create_revisions_table.php')))) {
            $timestamp = date('Y_m_d_His');
            $migration = database_path("migrations/{$timestamp}_create_revisions_table.php");

            $this->publishes([
                __DIR__.'/../database/migrations/create_revisions_table.php.stub' => $migration,
            ], 'migrations');
        }*/
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
