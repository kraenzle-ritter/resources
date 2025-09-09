<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Support\Facades\Http;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Tests\TestModel;

class IntegrationTest extends TestCase
{
    public function test_complete_resource_lifecycle()
    {
        // 1. Create a Test-Model
        $model = TestModel::create(['name' => 'Test Integration']);

        // 2. Add a resource
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q12345',
            'url' => 'https://www.wikidata.org/wiki/Q12345',
            'full_json' => ['title' => 'Test Resource']
        ]);

        // 3. Check that the resource was saved correctly
        $this->assertEquals(1, $model->resources->count());
        $resource = $model->resources->first();
        $this->assertEquals('wikidata', $resource->provider);
        $this->assertEquals('Q12345', $resource->provider_id);

        // 4. Update the resource
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q12345',
            'url' => 'https://www.wikidata.org/wiki/Q12345',
            'full_json' => ['title' => 'Updated Test Resource']
        ]);

        // 5. Check that there is still only one resource (update, not create)
        $model->refresh();
        $this->assertEquals(1, $model->resources->count());
        $updatedResource = $model->resources->first();
        $this->assertEquals('Updated Test Resource', $updatedResource->full_json['title']);

        // 6. Remove the resource
        $resourceToDelete = $model->resources->first();
        $model->removeResource($resourceToDelete->id);
        $model->refresh();
        $this->assertEquals(0, $model->resources->count());
    }

    public function test_multiple_providers_for_same_model()
    {
        $model = TestModel::create(['name' => 'Multi Provider Test']);

        // Add multiple resources from different providers
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q11111',
            'url' => 'https://www.wikidata.org/wiki/Q11111'
        ]);

        $model->updateOrCreateResource([
            'provider' => 'gnd',
            'provider_id' => '123456789',
            'url' => 'https://d-nb.info/gnd/123456789'
        ]);

        $model->updateOrCreateResource([
            'provider' => 'wikipedia-de',
            'provider_id' => '987654',
            'url' => 'https://de.wikipedia.org/?curid=987654'
        ]);

        // Check that all resources are saved
        $model->refresh();
        $this->assertEquals(3, $model->resources->count());

        $providers = $model->resources->pluck('provider')->toArray();
        $this->assertContains('wikidata', $providers);
        $this->assertContains('gnd', $providers);
        $this->assertContains('wikipedia-de', $providers);
    }

    public function test_resource_sync_service_integration()
    {
        // Mock HTTP responses
        Http::fake([
            'wikidata.org/*' => Http::response([
                'entities' => [
                    'Q12345' => [
                        'labels' => ['en' => ['value' => 'Test Entity']],
                        'claims' => [
                            'P227' => [['mainsnak' => ['datavalue' => ['value' => 'gnd123']]]]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $model = TestModel::create(['name' => 'Sync Test']);
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q12345',
            'url' => 'https://www.wikidata.org/wiki/Q12345'
        ]);

        // Test that the ResourceSyncService can be resolved and used
        $service = app('resources');
        $this->assertInstanceOf(\KraenzleRitter\Resources\ResourceSyncService::class, $service);

        $this->assertNotEmpty($service);
    }
}
