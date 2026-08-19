<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Http\Livewire\AntonLwComponent;
use KraenzleRitter\Resources\Http\Livewire\GeonamesLwComponent;
use KraenzleRitter\Resources\Http\Livewire\GndLwComponent;
use KraenzleRitter\Resources\Http\Livewire\IdiotikonLwComponent;
use KraenzleRitter\Resources\Http\Livewire\MetagridLwComponent;
use KraenzleRitter\Resources\Http\Livewire\OrtsnamenLwComponent;
use KraenzleRitter\Resources\Http\Livewire\ResourcesList;
use KraenzleRitter\Resources\Http\Livewire\WikidataLwComponent;
use KraenzleRitter\Resources\Http\Livewire\WikipediaLwComponent;
use KraenzleRitter\Resources\Resource;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Livewire method calls are client-controlled: the browser sends the method name
 * and its arguments. A component must therefore only ever delete resources that
 * belong to the model it was mounted with.
 */
class ResourceOwnershipTest extends TestCase
{
    private const SHARED_URL = 'https://d-nb.info/gnd/118500775';

    /**
     * Two models, both linked to the same provider record.
     *
     * @return array{0: TestModel, 1: TestModel}
     */
    private function twoModelsSharingAResource(string $provider = 'gnd'): array
    {
        $mine = TestModel::create(['name' => 'Mine']);
        $theirs = TestModel::create(['name' => 'Theirs']);

        foreach ([$mine, $theirs] as $model) {
            $model->updateOrCreateResource([
                'provider' => $provider,
                'provider_id' => '118500775',
                'url' => self::SHARED_URL,
            ]);
        }

        return [$mine, $theirs];
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function providerComponents(): array
    {
        return [
            'gnd' => [GndLwComponent::class, 'gnd'],
            'geonames' => [GeonamesLwComponent::class, 'geonames'],
            'idiotikon' => [IdiotikonLwComponent::class, 'idiotikon'],
            'ortsnamen' => [OrtsnamenLwComponent::class, 'ortsnamen'],
            'metagrid' => [MetagridLwComponent::class, 'metagrid'],
            'wikidata' => [WikidataLwComponent::class, 'wikidata'],
            'wikipedia' => [WikipediaLwComponent::class, 'wikipedia-de'],
            'anton' => [AntonLwComponent::class, 'georgfischer'],
        ];
    }

    #[DataProvider('providerComponents')]
    public function test_component_cannot_remove_another_models_resource_by_url(string $component, string $provider)
    {
        [$mine, $theirs] = $this->twoModelsSharingAResource($provider);

        Livewire::test($component, $this->mountParams($component, $mine))
            ->call('removeResource', self::SHARED_URL);

        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $theirs->id,
            'url' => self::SHARED_URL,
        ]);
        $this->assertSame(1, $theirs->fresh()->resources()->count(), "{$provider}: another model's resource was deleted");
    }

    #[DataProvider('providerComponents')]
    public function test_component_removes_its_own_resource_by_url(string $component, string $provider)
    {
        [$mine] = $this->twoModelsSharingAResource($provider);

        Livewire::test($component, $this->mountParams($component, $mine))
            ->call('removeResource', self::SHARED_URL)
            ->assertDispatched('resourcesChanged');

        $this->assertDatabaseMissing('resources', [
            'resourceable_id' => $mine->id,
            'url' => self::SHARED_URL,
        ]);
    }

    public function test_resources_list_cannot_remove_another_models_resource_by_id()
    {
        [$mine, $theirs] = $this->twoModelsSharingAResource();
        $victim = $theirs->resources()->first();

        Livewire::test(ResourcesList::class, ['model' => $mine])
            ->call('removeResource', $victim->id);

        $this->assertDatabaseHas('resources', ['id' => $victim->id]);
    }

    public function test_resources_list_removes_its_own_resource_by_id()
    {
        [$mine] = $this->twoModelsSharingAResource();
        $own = $mine->resources()->first();

        Livewire::test(ResourcesList::class, ['model' => $mine])
            ->call('removeResource', $own->id)
            ->assertDispatched('resourcesChanged');

        $this->assertDatabaseMissing('resources', ['id' => $own->id]);
    }

    public function test_trait_removal_is_scoped_to_the_model()
    {
        [$mine, $theirs] = $this->twoModelsSharingAResource();
        $victim = $theirs->resources()->first();
        $own = $mine->resources()->first();

        // Consumers call this directly; the boolean contract must hold.
        $this->assertFalse($mine->removeResource($victim->id), 'Removing a foreign resource must report false');
        $this->assertDatabaseHas('resources', ['id' => $victim->id]);

        $this->assertTrue($mine->removeResource($own->id), 'Removing an own resource must report true');
        $this->assertDatabaseMissing('resources', ['id' => $own->id]);
    }

    public function test_removal_does_not_cross_model_types()
    {
        $mine = TestModel::create(['name' => 'Mine']);
        $mine->updateOrCreateResource([
            'provider' => 'gnd',
            'provider_id' => '118500775',
            'url' => self::SHARED_URL,
        ]);

        // Same id, different resourceable_type.
        $foreign = Resource::create([
            'provider' => 'gnd',
            'provider_id' => '118500775',
            'url' => self::SHARED_URL,
            'resourceable_type' => 'App\\Models\\SomethingElse',
            'resourceable_id' => $mine->id,
        ]);

        Livewire::test(GndLwComponent::class, ['model' => $mine])
            ->call('removeResource', self::SHARED_URL);

        $this->assertDatabaseHas('resources', ['id' => $foreign->id]);
    }

    public function test_saving_attaches_the_resource_to_the_mounted_model_only()
    {
        $mine = TestModel::create(['name' => 'Mine']);
        $other = TestModel::create(['name' => 'Other']);

        Livewire::test(GndLwComponent::class, ['model' => $mine])
            ->call('saveResource', '118500775', self::SHARED_URL, null);

        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $mine->id,
            'resourceable_type' => TestModel::class,
            'provider' => 'gnd',
        ]);
        $this->assertSame(0, $other->fresh()->resources()->count());
    }

    /**
     * Components do not share a mount() signature: Wikipedia and Anton take a
     * providerKey, Anton additionally an endpoint.
     */
    private function mountParams(string $component, TestModel $model): array
    {
        return match ($component) {
            WikipediaLwComponent::class => ['model' => $model, 'search' => '', 'providerKey' => 'wikipedia-de'],
            AntonLwComponent::class => ['model' => $model, 'search' => '', 'providerKey' => 'georgfischer', 'endpoint' => 'actors'],
            default => ['model' => $model],
        };
    }
}
