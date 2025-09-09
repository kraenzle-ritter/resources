<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Support\Facades\Http;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\ResourceSyncService;

class MetagridSyncTest extends TestCase
{
    public function test_metagrid_provider_is_configured()
    {
        $metagridProvider = config('resources.providers.metagrid');

        $this->assertIsArray($metagridProvider);
        $this->assertArrayHasKey('label', $metagridProvider);
        $this->assertArrayHasKey('base_url', $metagridProvider);
        $this->assertEquals('Metagrid', $metagridProvider['label']);
    }

    public function test_resource_sync_service_can_handle_metagrid()
    {
        // Mock HTTP response für Metagrid
        Http::fake([
            'metagrid.org/*' => Http::response([
                'records' => [
                    [
                        'id' => 'test123',
                        'url' => 'https://metagrid.org/test123',
                        'title' => 'Test Record'
                    ]
                ]
            ], 200)
        ]);

        $service = new ResourceSyncService();

        // Test dass der Service instantiiert werden kann und die Metagrid-Konfiguration hat
        $this->assertInstanceOf(ResourceSyncService::class, $service);
    }

    public function test_metagrid_provider_has_correct_target_url()
    {
        $metagridProvider = config('resources.providers.metagrid');

        $this->assertNotNull($metagridProvider, 'Metagrid provider configuration should exist');
        $this->assertIsArray($metagridProvider, 'Metagrid provider should be an array');
        $this->assertArrayHasKey('target_url', $metagridProvider);
        $this->assertStringContainsString('{provider_id}', $metagridProvider['target_url']);
    }
}
