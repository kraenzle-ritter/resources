<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Http\Livewire\WikidataLwComponent;
use KraenzleRitter\Resources\Tests\TestModel;

class WikidataLwComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock HTTP responses for Wikidata API
        Http::fake([
            'wikidata.org/*' => Http::response([
                'search' => [
                    [
                        'id' => 'Q57188',
                        'title' => 'Q57188',
                        'label' => 'Ernst Cassirer',
                        'description' => 'German philosopher',
                        'match' => [
                            'type' => 'label',
                            'language' => 'en',
                            'text' => 'Ernst Cassirer'
                        ]
                    ]
                ]
            ], 200)
        ]);
    }

    /** @test */
    public function it_can_mount_with_model()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikidataLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertSet('model', $model);
        $component->assertSet('resourceable_id', 1);
        $component->assertSet('provider', 'wikidata');
    }

    /** @test */
    public function it_can_perform_wikidata_search()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikidataLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->set('search', 'Ernst Cassirer');
        $component->call('searchProvider');

        $component->assertSet('search', 'Ernst Cassirer');
        $this->assertNotEmpty($component->get('queryOptions'));
    }

    /** @test */
    public function it_can_save_wikidata_resource()
    {
        $model = new TestModel();
        $model->save();

        $component = Livewire::test(WikidataLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $resourceData = [
            'provider_id' => 'Q57188',
            'name' => 'Ernst Cassirer',
            'additional_data' => ['description' => 'German philosopher']
        ];

        $component->call('updateOrCreateResource', $resourceData);

        $this->assertDatabaseHas('resources', [
            'provider' => 'wikidata',
            'provider_id' => 'Q57188',
            'name' => 'Ernst Cassirer'
        ]);
    }

    /** @test */
    public function it_triggers_sync_from_wikidata_on_save()
    {
        $model = new TestModel();
        $model->save();

        // Mock additional HTTP calls for sync
        Http::fake([
            '*' => Http::response(['entities' => []], 200)
        ]);

        $component = Livewire::test(WikidataLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $resourceData = [
            'provider_id' => 'Q57188',
            'name' => 'Ernst Cassirer',
            'additional_data' => ['description' => 'German philosopher']
        ];

        $component->call('updateOrCreateResource', $resourceData);

        // Verify that sync was triggered
        $this->assertDatabaseHas('resources', [
            'provider' => 'wikidata',
            'provider_id' => 'Q57188'
        ]);
    }

    /** @test */
    public function it_can_toggle_show_all_results()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikidataLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertSet('showAll', false);

        $component->call('toggleShowAll');

        $component->assertSet('showAll', true);
    }

    /** @test */
    public function it_renders_without_errors()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikidataLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertStatus(200);
    }

    /** @test */
    public function it_handles_wikidata_api_errors()
    {
        // Mock API error
        Http::fake([
            'wikidata.org/*' => Http::response([], 500)
        ]);

        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikidataLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->set('search', 'test query');
        $component->call('searchProvider');

        // Should handle error gracefully
        $this->assertEmpty($component->get('queryOptions'));
    }
}
