<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use Livewire\Livewire;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Http\Livewire\ResourcesList;
use KraenzleRitter\Resources\Tests\TestModel;

class ResourcesListTest extends TestCase
{
    /** @test */
    public function it_can_mount_with_model()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(ResourcesList::class, [
            'model' => $model
        ]);

        $component->assertSet('model', $model);
        $component->assertSet('deleteButton', false);
    }

    /** @test */
    public function it_can_mount_with_delete_button_enabled()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(ResourcesList::class, [
            'model' => $model,
            'deleteButton' => true
        ]);

        $component->assertSet('model', $model);
        $component->assertSet('deleteButton', true);
    }

    /** @test */
    public function it_can_remove_resource()
    {
        $model = new TestModel();
        $model->save();

        // Create a resource
        $resource = $model->resources()->create([
            'provider' => 'gnd',
            'provider_id' => '123456789',
            'name' => 'Test Person',
            'additional_data' => json_encode(['test' => 'data'])
        ]);

        $component = Livewire::test(ResourcesList::class, [
            'model' => $model,
            'deleteButton' => true
        ]);

        $component->call('removeResource', $resource->id);

        $this->assertDatabaseMissing('resources', [
            'id' => $resource->id
        ]);
    }

    /** @test */
    public function it_dispatches_resources_changed_event_on_removal()
    {
        $model = new TestModel();
        $model->save();

        // Create a resource
        $resource = $model->resources()->create([
            'provider' => 'gnd',
            'provider_id' => '123456789',
            'name' => 'Test Person',
            'additional_data' => json_encode(['test' => 'data'])
        ]);

        $component = Livewire::test(ResourcesList::class, [
            'model' => $model,
            'deleteButton' => true
        ]);

        $component->call('removeResource', $resource->id);

        $component->assertDispatched('resourcesChanged');
    }

    /** @test */
    public function it_displays_model_resources()
    {
        $model = new TestModel();
        $model->save();

        // Create multiple resources
        $model->resources()->create([
            'provider' => 'gnd',
            'provider_id' => '123456789',
            'name' => 'Test Person 1',
            'additional_data' => json_encode(['test' => 'data1'])
        ]);

        $model->resources()->create([
            'provider' => 'wikidata',
            'provider_id' => 'Q12345',
            'name' => 'Test Person 2',
            'additional_data' => json_encode(['test' => 'data2'])
        ]);

        $component = Livewire::test(ResourcesList::class, [
            'model' => $model
        ]);

        $resources = $component->get('resources');
        $this->assertCount(2, $resources);
    }

    /** @test */
    public function it_renders_without_errors()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(ResourcesList::class, [
            'model' => $model
        ]);

        $component->assertStatus(200);
    }

    /** @test */
    public function it_listens_to_resources_changed_event()
    {
        $model = new TestModel();
        $model->id = 1;

        $component = Livewire::test(ResourcesList::class, [
            'model' => $model
        ]);

        // Verify that the component listens to the resourcesChanged event
        $this->assertContains('resourcesChanged', array_keys($component->instance()->getEventsBeingListenedFor()));
    }
}
