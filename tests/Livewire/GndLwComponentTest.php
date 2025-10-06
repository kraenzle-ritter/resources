<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Http\Livewire\GndLwComponent;
use KraenzleRitter\Resources\Tests\TestModel;

class GndLwComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock HTTP responses for GND API
        Http::fake([
            'lobid.org/*' => Http::response([
                'member' => [
                    [
                        'gndIdentifier' => '123456789',
                        'preferredName' => 'Test Person',
                        'variantName' => ['Alternative Name'],
                        'professionOrOccupation' => [
                            ['label' => 'Philosopher']
                        ],
                        'dateOfBirth' => ['1900'],
                        'dateOfDeath' => ['1980'],
                        'biographicalOrHistoricalInformation' => ['Test biography']
                    ]
                ]
            ], 200)
        ]);
    }

    public function test_it_can_mount_with_model()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(GndLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertSet('model', $model);
        $component->assertSet('resourceable_id', 1);
        $component->assertSet('provider', 'gnd');
    }

    public function test_it_can_perform_search()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(GndLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->set('search', 'Hannah Arendt');
        $component->call('searchProvider');

        $component->assertSet('search', 'Hannah Arendt');
        $this->assertNotEmpty($component->get('queryOptions'));
    }

    public function test_it_can_toggle_show_all_results()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(GndLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertSet('showAll', false);

        $component->call('toggleShowAll');

        $component->assertSet('showAll', true);
    }

    public function test_it_can_save_resource()
    {
        $model = new TestModel();
        $model->save();

        $component = Livewire::test(GndLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $resourceData = [
            'provider_id' => '123456789',
            'url' => 'https://d-nb.info/gnd/123456789',
            'full_json' => ['profession' => 'Philosopher']
        ];

        $component->call('updateOrCreateResource', $resourceData);

        $this->assertDatabaseHas('resources', [
            'provider' => 'gnd',
            'provider_id' => '123456789',
            'url' => 'https://d-nb.info/gnd/123456789'
        ]);
    }

    public function test_it_can_remove_resource()
    {
        $model = new TestModel();
        $model->save();

        // Create a resource first
        $resource = $model->resources()->create([
            'provider' => 'gnd',
            'provider_id' => '123456789',
            'url' => 'https://d-nb.info/gnd/123456789',
            'full_json' => json_encode(['profession' => 'Philosopher'])
        ]);

        $component = Livewire::test(GndLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->call('removeResource', $resource->url);

        $this->assertDatabaseMissing('resources', [
            'id' => $resource->id
        ]);
    }

    public function test_it_renders_without_errors()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(GndLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertStatus(200);
    }

    public function test_it_handles_empty_search_results()
    {
        // Mock empty response
        Http::fake([
            'lobid.org/*' => Http::response(['member' => []], 200)
        ]);

        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(GndLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->set('search', 'nonexistent person');

        // Simply verify the component can handle empty results without error
        $component->assertStatus(200);
    }
}
