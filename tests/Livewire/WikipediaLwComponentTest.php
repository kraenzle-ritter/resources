<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Http\Livewire\WikipediaLwComponent;
use KraenzleRitter\Resources\Tests\TestModel;

class WikipediaLwComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock HTTP responses for Wikipedia API
        Http::fake([
            'wikipedia.org/*' => Http::response([
                'query' => [
                    'searchinfo' => ['totalhits' => 1],
                    'search' => [
                        [
                            'title' => 'Test Article',
                            'snippet' => 'Test snippet',
                            'pageid' => 12345
                        ]
                    ]
                ]
            ], 200)
        ]);
    }

    public function test_it_can_mount_with_model()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikipediaLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertSet('model', $model);
        $component->assertSet('resourceable_id', 1);
        $component->assertSet('provider', 'Wikipedia');
    }

    public function test_it_can_perform_search()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikipediaLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->set('search', 'Albert Einstein');

        $component->assertSet('search', 'Albert Einstein');
        $this->assertNotEmpty($component->get('queryOptions'));
    }

    public function test_it_can_toggle_show_all_results()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikipediaLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertSet('showAll', false);

        $component->call('showAllResults');

        $component->assertSet('showAll', true);
    }

    public function test_it_can_save_resource()
    {
        $model = new TestModel();
        $model->save();

        $component = Livewire::test(WikipediaLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $resourceData = [
            'provider_id' => '12345',
            'url' => 'https://de.wikipedia.org/?curid=12345',
            'full_json' => ['title' => 'Test Article']
        ];

        $component->call('saveResource', $resourceData['provider_id'], $resourceData['url']);

        $this->assertDatabaseHas('resources', [
            'provider' => 'wikipedia-de',
            'provider_id' => '12345',
            'url' => 'https://de.wikipedia.org/?curid=12345'
        ]);
    }

    public function test_it_can_remove_resource()
    {
        $model = new TestModel();
        $model->save();

        // Create a resource first
        $resource = $model->resources()->create([
            'provider' => 'wikipedia',
            'provider_id' => '12345',
            'url' => 'https://de.wikipedia.org/?curid=12345',
            'full_json' => json_encode(['title' => 'Test Article'])
        ]);

        $component = Livewire::test(WikipediaLwComponent::class, [
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

        $component = Livewire::test(WikipediaLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->assertStatus(200);
    }

    public function test_it_handles_empty_search_results()
    {
        // Mock empty response
        Http::fake([
            'wikipedia.org/*' => Http::response([
                'query' => [
                    'searchinfo' => ['totalhits' => 0],
                    'search' => []
                ]
            ], 200)
        ]);

        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(WikipediaLwComponent::class, [
            'model' => $model,
            'resourceable_id' => $model->id
        ]);

        $component->set('search', 'nonexistent article');

        // Simply verify the component can handle empty results without error
        $component->assertStatus(200);
    }
}
