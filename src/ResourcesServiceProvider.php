<?php

namespace KraenzleRitter\Resources;

use Illuminate\Support\ServiceProvider;
use KraenzleRitter\Resources\Console\Commands\ResourcesFetch;
use KraenzleRitter\Resources\Console\Commands\TestResourcesCommand;
use KraenzleRitter\Resources\Http\Livewire\AntonLwComponent;
use KraenzleRitter\Resources\Http\Livewire\GeonamesLwComponent;
use KraenzleRitter\Resources\Http\Livewire\GndLwComponent;
use KraenzleRitter\Resources\Http\Livewire\IdiotikonLwComponent;
use KraenzleRitter\Resources\Http\Livewire\ManualInputLwComponent;
use KraenzleRitter\Resources\Http\Livewire\MetagridLwComponent;
use KraenzleRitter\Resources\Http\Livewire\OrtsnamenLwComponent;
use KraenzleRitter\Resources\Http\Livewire\WikidataLwComponent;
use KraenzleRitter\Resources\Http\Livewire\WikipediaLwComponent;
use KraenzleRitter\Resources\Http\Livewire\ProviderSelect;
use KraenzleRitter\Resources\Http\Livewire\ResourcesList;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Wikipedia;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Ortsnamen;
use Livewire\Livewire;

class ResourcesServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'resources');

        // Load translation files
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'resources');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Register Livewire components
        if (class_exists(\Livewire\Livewire::class)) {
            Livewire::component('provider-select', ProviderSelect::class);
            Livewire::component('resources-list', ResourcesList::class);
            Livewire::component('anton-lw-component', AntonLwComponent::class);
            Livewire::component('geonames-lw-component', GeonamesLwComponent::class);
            Livewire::component('gnd-lw-component', GndLwComponent::class);
            Livewire::component('idiotikon-lw-component', IdiotikonLwComponent::class);
            Livewire::component('metagrid-lw-component', MetagridLwComponent::class);
            Livewire::component('ortsnamen-lw-component', OrtsnamenLwComponent::class);
            Livewire::component('wikidata-lw-component', WikidataLwComponent::class);
            Livewire::component('wikipedia-lw-component', WikipediaLwComponent::class);
            Livewire::component('manual-input-lw-component', ManualInputLwComponent::class);
        }

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ResourcesFetch::class,
                TestResourcesCommand::class,
            ]);
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
            return new ResourceSyncService();
        });

        // Register Provider classes for dependency injection
        $this->app->bind(Wikidata::class);
        $this->app->bind(Wikipedia::class);
        $this->app->bind(Gnd::class);
        $this->app->bind(Geonames::class);
        $this->app->bind(Metagrid::class);
        $this->app->bind(Idiotikon::class);
        $this->app->bind(Ortsnamen::class);
        // Anton is handled separately as it requires parameters
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

        // publishing migrations
        $this->publishes([
            __DIR__.'/../migrations/create_resources_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_resources_table.php'),
        ], 'migrations');
    }
}
