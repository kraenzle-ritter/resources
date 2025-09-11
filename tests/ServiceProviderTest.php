<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\ResourceSyncService;
use KraenzleRitter\Resources\ResourcesServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_is_registered()
    {
        $this->assertTrue($this->app->getProvider(ResourcesServiceProvider::class) !== null);
    }

    public function test_resource_sync_service_is_bound()
    {
        $this->assertTrue($this->app->bound('resources'));

        $service = $this->app->make('resources');
        $this->assertInstanceOf(ResourceSyncService::class, $service);
    }

    public function test_config_is_published()
    {
        $config = config('resources');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('providers', $config);
        $this->assertArrayHasKey('table', $config);
    }


    public function test_migration_is_loadable()
    {
        // Test dass die Migration existiert
        $migrationPath = __DIR__ . '/../migrations/create_resources_table.php.stub';

        if (file_exists($migrationPath)) {
            $this->assertFileExists($migrationPath);
        } else {
            // Falls die Migration nicht als Stub existiert, prüfen wir die Tabelle
            $this->assertTrue(\Schema::hasTable('resources'));
        }
    }
}
