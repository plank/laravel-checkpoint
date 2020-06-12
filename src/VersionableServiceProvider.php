<?php

namespace Plank\Versionable;

use Illuminate\Support\ServiceProvider;

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
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'versionable');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'versionable');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/versionable.php' => config_path('versionable.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/versionable'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/versionable'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/versionable'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);
        }


        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
/*        if (empty(File::glob(database_path('migrations/*_create_versions_table.php')))) {
            $timestamp = date('Y_m_d_His');
            $migration = database_path("migrations/{$timestamp}_create_versions_table.php");

            $this->publishes([
                __DIR__.'/../database/migrations/create_versions_table.php.stub' => $migration,
            ], 'migrations');
        }*/
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/versionable.php', 'versionable');
    }
}
