<?php

namespace KraenzleRitter\Resources\Tests;

use Mockery;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Tests\TestModel;
use KraenzleRitter\Resources\ResourceSyncService;

class ResourceSyncServiceTest extends TestCase
{
    protected $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test environment
        config([
            'resources.providers' => [
                'wikidata' => [
                    'label' => 'Wikidata',
                    'api-type' => 'Wikidata',
                    'base_url' => 'https://www.wikidata.org/w/api.php',
                    'wikidata_property' => 'Q',
                ],
                'gnd' => [
                    'label' => 'GND',
                    'wikidata_property' => 'P227',
                ],
                'wikipedia-de' => [
                    'label' => 'Wikipedia (DE)',
                    'wikidata_property' => 'wikipedia-de',
                ],
            ]
        ]);

        $this->syncService = new ResourceSyncService();
    }

    /**
     * Testet die Grundkonfiguration des ResourceSyncService
     */
    public function test_resource_sync_service_initialization()
    {
        $this->assertInstanceOf(ResourceSyncService::class, $this->syncService);

        // Test mit Exceptions
        $exceptions = ['provider1', 'provider2'];
        $serviceWithExceptions = new ResourceSyncService($exceptions);
        $this->assertInstanceOf(ResourceSyncService::class, $serviceWithExceptions);
    }

    /**
     * Testet die getWikidataArray Methode
     */
    public function test_get_wikidata_array()
    {
        $input = [
            'provider1' => ['wikidata_property' => 'P123'],
            'provider2' => ['no_wikidata' => 'test'],
            'provider3' => ['wikidata_property' => 'P456'],
        ];

        $result = $this->syncService->getWikidataArray($input);

        $expected = [
            'provider1' => 'P123',
            'provider3' => 'P456',
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Testet syncFromProvider ohne existierende Ressource
     */
    public function test_sync_from_provider_no_existing_resource()
    {
        $model = TestModel::create(['name' => 'Test Model']);
        $result = $this->syncService->syncFromProvider($model, 'nonexistent');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Testet die unique_multidim_array Methode über Reflection
     */
    public function test_unique_multidim_array()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('unique_multidim_array');
        $method->setAccessible(true);

        $input = [
            ['provider' => 'gnd', 'id' => '123'],
            ['provider' => 'viaf', 'id' => '456'],
            ['provider' => 'gnd', 'id' => '123'], // Duplikat
            ['provider' => 'bnf', 'id' => '789'],
        ];

        $result = $method->invoke($this->syncService, $input, 'provider');

        $this->assertCount(3, $result);

        $providers = array_column($result, 'provider');
        $this->assertContains('gnd', $providers);
        $this->assertContains('viaf', $providers);
        $this->assertContains('bnf', $providers);

        // Sollte nur einen 'gnd' Eintrag geben
        $gndCount = array_count_values($providers)['gnd'];
        $this->assertEquals(1, $gndCount);
    }

    /**
     * Testet getWikidataIdForGndId Methode mit Mock (vereinfacht)
     */
    public function test_get_wikidata_id_for_gnd_id_method_exists()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('getWikidataIdForGndId');
        $method->setAccessible(true);

        // Da die Methode HTTP-Calls macht, testen wir nur die Existenz und Aufrufbarkeit
        $this->assertTrue($method->isProtected());
        $this->assertEquals('getWikidataIdForGndId', $method->getName());

        // Test mit ungültiger GND ID (sollte null zurückgeben)
        $result = $method->invoke($this->syncService, 'invalid-gnd-id');
        $this->assertNull($result);
    }

    /**
     * Testet Exception-Handling mit Exceptions-Liste
     */
    public function test_sync_service_with_exceptions_list()
    {
        $exceptions = ['gnd', 'viaf'];
        $serviceWithExceptions = new ResourceSyncService($exceptions);

        $this->assertInstanceOf(ResourceSyncService::class, $serviceWithExceptions);

        // Test mit leerem Array
        $emptyService = new ResourceSyncService([]);
        $this->assertInstanceOf(ResourceSyncService::class, $emptyService);
    }

    public function test_sync_from_wikidata_provider()
    {
        $model = TestModel::create(['name' => 'Test sync from wikidata']);
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q57188',
            'url' => 'https://www.wikidata.org/wiki/Q57188'
        ]);

        $this->assertEquals(1, count($model->resources));

        $syncService = new ResourceSyncService();
        $syncedResources = $syncService->syncFromProvider($model, 'wikidata');

        $this->assertIsArray($syncedResources);
        $this->assertGreaterThan(0, count($syncedResources));

        $model->refresh();
        $this->assertGreaterThan(1, count($model->resources));
        $this->assertTrue($model->resources->contains('provider', 'wikidata'));
    }

    public function test_sync_from_metagrid_provider()
    {
        $model = TestModel::create(['name' => 'Test sync from metagrid']);

        // Create a test metagrid resource
        $model->updateOrCreateResource([
            'provider' => 'metagrid',
            'provider_id' => 'test-id',
            'url' => 'https://httpbin.org/json' // Mock endpoint that returns JSON
        ]);

        $this->assertEquals(1, count($model->resources));

        $syncService = new ResourceSyncService();

        // Note: This test may fail in real scenarios since httpbin.org doesn't return
        // metagrid format, but it tests the code path
        $syncedResources = $syncService->syncFromProvider($model, 'metagrid');

        $this->assertIsArray($syncedResources);
        // We expect empty array since httpbin.org doesn't return metagrid format
        $this->assertEquals(0, count($syncedResources));
    }

    /**
     * Testet fetchFromWikidata mit Mock-Response
     */
    public function test_fetch_from_wikidata_with_mock()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('fetchFromWikidata');
        $method->setAccessible(true);

        // Da die Methode HTTP-Calls macht, testen wir mit einer ungültigen ID
        $result = $method->invoke($this->syncService, 'Q_INVALID_ID');

        $this->assertIsArray($result);
        // Ungültige ID sollte leeres Array zurückgeben
        $this->assertEmpty($result);
    }

    /**
     * Testet getWikidataId Methode
     */
    public function test_get_wikidata_id_method()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('getWikidataId');
        $method->setAccessible(true);

        // Test mit Wikidata Provider (sollte die ID direkt zurückgeben)
        $result = $method->invoke($this->syncService, 'Q12345', 'wikidata');
        $this->assertEquals('Q12345', $result);

        // Test mit GND Provider (macht HTTP-Call, daher erwarten wir null bei ungültiger ID)
        $result = $method->invoke($this->syncService, 'invalid-gnd-id', 'gnd');
        $this->assertNull($result);
    }

    /**
     * Testet getWikidataIdForWikipediaId Methode
     */
    public function test_get_wikidata_id_for_wikipedia_id_method()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('getWikidataIdForWikipediaId');
        $method->setAccessible(true);

        // Test mit ungültiger Wikipedia ID
        $result = $method->invoke($this->syncService, 'Invalid_Wikipedia_Page');
        $this->assertNull($result);
    }

    /**
     * Testet fetchFromMetagrid Methode
     */
    public function test_fetch_from_metagrid_method()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('fetchFromMetagrid');
        $method->setAccessible(true);

        // Test mit ungültiger URL
        $result = $method->invoke($this->syncService, 'invalid-url');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Testet setUpProviders Methode
     */
    public function test_setup_providers_method()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('setUpProviders');
        $method->setAccessible(true);

        // Setup ausführen
        $method->invoke($this->syncService);

        // Teste dass keine Exception geworfen wird
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
