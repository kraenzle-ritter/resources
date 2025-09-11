<?php

namespace KraenzleRitter\Resources\Tests\Livewire;

use Livewire\Livewire;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Tests\TestModel;
use KraenzleRitter\Resources\Http\Livewire\ProviderSelect;

class ProviderSelectTest extends TestCase
{
    public function test_it_can_mount_with_providers()
    {
        $providers = ['gnd', 'wikidata', 'geonames'];
        $model = TestModel::create(['name' => 'Test']);

        $component = Livewire::test(ProviderSelect::class, [
            'model' => $model,
            'providers' => $providers
        ]);

        $component->assertSet('model', $model);
        $component->assertSet('providers_all', $providers);
    }

    public function test_it_can_set_provider()
    {
        $providers = ['gnd', 'wikidata', 'geonames'];
        $model = TestModel::create(['name' => 'Test']);

        $component = Livewire::test(ProviderSelect::class, [
            'model' => $model,
            'providers' => $providers
        ]);

        $component->call('setProvider', 'wikidata');
        $component->assertSet('providerKey', 'wikidata');
    }

    public function test_it_filters_available_providers()
    {
        $providers = ['gnd', 'invalid-provider', 'wikidata'];
        $model = TestModel::create(['name' => 'Test']);

        $component = Livewire::test(ProviderSelect::class, [
            'model' => $model,
            'providers' => $providers
        ]);

        // Should filter out invalid providers
        $this->assertContains('gnd', $component->get('providers'));
        $this->assertContains('wikidata', $component->get('providers'));
        $this->assertNotContains('invalid-provider', $component->get('providers'));
    }

    public function test_it_updates_component_params_when_provider_changes()
    {
        $providers = ['gnd', 'wikidata'];
        $model = TestModel::create(['name' => 'Test']);

        $component = Livewire::test(ProviderSelect::class, [
            'model' => $model,
            'providers' => $providers
        ]);

        $component->call('setProvider', 'gnd');

        $params = $component->get('componentParams');
        $this->assertArrayHasKey('model', $params);
        $this->assertEquals($model->id, $params['model']->id);
        $this->assertEquals($model->name, $params['model']->name);
    }

    public function test_it_renders_without_errors()
    {
        $providers = ['gnd', 'wikidata'];
        $model = TestModel::create(['name' => 'Test']);

        $component = Livewire::test(ProviderSelect::class, [
            'model' => $model,
            'providers' => $providers
        ]);

        $component->assertStatus(200);
    }
}
