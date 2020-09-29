<?php

namespace KraenzleRitter\Resources;

use Illuminate\Support\ServiceProvider;

class ResourcesServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'kraenzle-ritter');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'kraenzle-ritter');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/resources.php', 'resources');

        // Register the service the package provides.
        $this->app->singleton('resources', function ($app) {
            return new resources;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['resources'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/resources.php' => config_path('resources.php'),
        ], 'resources.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/kraenzle-ritter'),
        ], 'resources.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/kraenzle-ritter'),
        ], 'resources.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/kraenzle-ritter'),
        ], 'resources.views');*/

        // Registering package commands.
        // $this->commands([]);

        // publishing migrations
        $this->publishes([
            __DIR__.'/../migrations/create_resources_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_resources_table.php'),
        ], 'migrations');
    }
}
