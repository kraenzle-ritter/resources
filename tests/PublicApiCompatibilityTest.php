<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Database\Eloquent\Model;
use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\HasResources;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Ortsnamen;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\ResourceSyncService;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Wikipedia;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins the API surface that Anton (~/Sites/anton.test) and KB (~/Sites/kb)
 * actually call. Both track dev-main, so a merge to main is a production deploy
 * for them: changing anything asserted here breaks a live application.
 *
 * Derived from a grep of both codebases; see openspec/project.md for the table.
 */
class PublicApiCompatibilityTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function traitMethods(): array
    {
        return [
            'resources' => ['resources'],
            'hasResources' => ['hasResources'],
            'updateOrCreateResource' => ['updateOrCreateResource'],
            'updateResource' => ['updateResource'],
            'removeResource' => ['removeResource'],
            'syncFromProvider' => ['syncFromProvider'],
        ];
    }

    #[DataProvider('traitMethods')]
    public function test_has_resources_still_exposes(string $method)
    {
        $this->assertTrue(
            method_exists(TestModel::class, $method),
            "HasResources::{$method}() is used by the consuming applications"
        );
    }

    public function test_update_or_create_resource_takes_a_single_array()
    {
        $method = new \ReflectionMethod(HasResources::class, 'updateOrCreateResource');

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('array', (string) $method->getParameters()[0]->getType());
    }

    public function test_sync_from_provider_keeps_its_two_argument_form()
    {
        // Anton: $actor->syncFromProvider('wikidata', setting('resources_filter'))
        $method = new \ReflectionMethod(HasResources::class, 'syncFromProvider');

        $this->assertSame(2, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());
        $this->assertSame('array', (string) $method->getReturnType());
    }

    public function test_remove_resource_still_returns_a_boolean()
    {
        $method = new \ReflectionMethod(HasResources::class, 'removeResource');

        $this->assertSame('bool', (string) $method->getReturnType());
    }

    public function test_the_resource_model_is_still_an_eloquent_model()
    {
        // Both apps query it directly, e.g.
        // Resource::where('resourceable_type', $class)->count()
        $this->assertTrue(is_subclass_of(Resource::class, Model::class));

        foreach (['updateOrCreateResource', 'updateResource', 'removeResource', 'resourceable'] as $method) {
            $this->assertTrue(method_exists(Resource::class, $method), "Resource::{$method}()");
        }
    }

    public function test_the_resource_saved_event_keeps_the_properties_the_listeners_read()
    {
        // UpdateLocationWithGeonamesCoordinates in both apps reads
        // $event->resource and $event->model_id.
        $event = new \ReflectionClass(ResourceSaved::class);

        $this->assertTrue($event->hasProperty('resource'));
        $this->assertTrue($event->hasProperty('model_id'));
        $this->assertSame(2, $event->getConstructor()->getNumberOfParameters());
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function directlyInstantiatedClients(): array
    {
        return [
            // Anton's LobidApiController does `new Gnd()`.
            'Gnd' => [Gnd::class],
            // KB's GeminiAgent\ResourceCollector does `new Wikipedia()`.
            'Wikipedia' => [Wikipedia::class],
            'Wikidata' => [Wikidata::class],
            'Geonames' => [Geonames::class],
            'Metagrid' => [Metagrid::class],
            'Idiotikon' => [Idiotikon::class],
            'Ortsnamen' => [Ortsnamen::class],
        ];
    }

    #[DataProvider('directlyInstantiatedClients')]
    public function test_the_client_is_still_constructible_without_arguments(string $class)
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();

        $this->assertSame(
            0,
            $constructor->getNumberOfRequiredParameters(),
            "{$class} is instantiated with `new {$class}()` in application code"
        );
        $this->assertInstanceOf($class, new $class());
    }

    public function test_anton_still_takes_its_provider_key_first()
    {
        $constructor = (new \ReflectionClass(Anton::class))->getConstructor();

        $this->assertSame('providerKey', $constructor->getParameters()[0]->getName());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());
    }

    public function test_the_sync_service_still_takes_the_filter_first()
    {
        $constructor = (new \ReflectionClass(ResourceSyncService::class))->getConstructor();

        $this->assertSame('filter', $constructor->getParameters()[0]->getName());
        $this->assertSame(0, $constructor->getNumberOfRequiredParameters());
    }

    /**
     * Provider keys are persisted in the resources.provider column; renaming one
     * orphans existing rows.
     */
    public function test_the_persisted_provider_keys_still_exist()
    {
        $configured = array_keys(config('resources.providers', []));

        foreach ([
            'gnd', 'geonames', 'wikidata', 'wikipedia-de', 'wikipedia-en', 'wikipedia-fr',
            'idiotikon', 'ortsnamen', 'metagrid', 'manual-input',
            'georgfischer', 'gosteli', 'kba',
            'hls-dhs-dss', 'viaf', 'lcnaf', 'idref', 'dodis', 'sikart',
        ] as $key) {
            $this->assertContains($key, $configured, "Provider key '{$key}' is persisted in existing rows");
        }
    }

    public function test_the_legacy_rename_map_still_resolves()
    {
        $rename = config('resources.rename', []);

        $this->assertSame('idref', $rename['sudoc'] ?? null);
        $this->assertSame('wikipedia-de', $rename['wikipedia'] ?? null);
        $this->assertSame('hls-dhs-dss', $rename['hls'] ?? null);
        $this->assertSame('lcnaf', $rename['loc'] ?? null);
    }

    /**
     * Both apps render these by alias:
     *   @livewire('resources-list', [$model, 'deleteButton' => true])
     *   @livewire('provider-select', [$model, $providers, $endpoint, $filter])
     * Mounting by alias is the check that the registration still resolves;
     * reflecting into Livewire's registry is version-specific and was silently
     * passing for aliases that do not exist.
     */
    public function test_resources_list_still_mounts_by_its_alias()
    {
        $model = TestModel::create(['name' => 'Compat']);

        \Livewire\Livewire::test('resources-list', [$model, 'deleteButton' => true])
            ->assertOk();
    }

    public function test_provider_select_still_mounts_by_its_alias_with_positional_arguments()
    {
        $model = TestModel::create(['name' => 'Compat']);

        // Anton: @livewire('provider-select', [$model, $providers, $endpoint, setting('resources_filter')])
        \Livewire\Livewire::test('provider-select', [$model, ['gnd', 'wikidata'], 'actors', []])
            ->assertOk();
    }

    public function test_provider_select_still_accepts_a_null_endpoint()
    {
        $model = TestModel::create(['name' => 'Compat']);

        \Livewire\Livewire::test('provider-select', [$model, ['gnd'], null, []])
            ->assertOk();
    }
}
