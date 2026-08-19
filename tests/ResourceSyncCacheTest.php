<?php

namespace KraenzleRitter\Resources\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use KraenzleRitter\Resources\ResourceSyncService;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;

/**
 * setUpProviders() fired a SPARQL query against query.wikidata.org from the
 * constructor — so every save in every Livewire component blocked on it.
 */
class ResourceSyncCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function sparqlResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'results' => ['bindings' => [
                [
                    'provider' => ['value' => 'http://www.wikidata.org/entity/P227'],
                    'providerLabel' => ['value' => 'GND ID'],
                    'url' => ['value' => 'https://d-nb.info/gnd/$1'],
                ],
            ]],
        ]));
    }

    private function entityResponse(array $claims = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'entities' => ['Q42' => [
                'labels' => ['en' => ['value' => 'Test Entity']],
                'descriptions' => [],
                'claims' => $claims,
            ]],
        ]));
    }

    private function claim(string $property, string $value): array
    {
        return [$property => [['mainsnak' => ['datavalue' => ['value' => $value]]]]];
    }

    public function test_constructing_the_service_makes_no_request()
    {
        $mock = new MockProviderClient([$this->sparqlResponse()]);

        new ResourceSyncService([], $mock->client);

        $this->assertSame(0, $mock->requestCount(), 'The constructor must not hit the network');
    }

    public function test_the_sparql_bootstrap_is_cached_across_instances()
    {
        $mock = new MockProviderClient([
            $this->sparqlResponse(),
            $this->sparqlResponse(),
            $this->sparqlResponse(),
        ]);

        $first = new ResourceSyncService([], $mock->client);
        $first->wikidataUrlPatterns();

        $second = new ResourceSyncService([], $mock->client);
        $second->wikidataUrlPatterns();

        $this->assertSame(1, $mock->requestCount(), 'The bootstrap must be requested at most once within the TTL');
    }

    public function test_a_failed_bootstrap_is_not_cached()
    {
        $mock = new MockProviderClient([
            new ConnectException('offline', new Request('GET', 'https://query.wikidata.org/sparql')),
            $this->sparqlResponse(),
        ]);

        $first = new ResourceSyncService([], $mock->client);
        $this->assertSame([], $first->wikidataUrlPatterns());

        $second = new ResourceSyncService([], $mock->client);
        $this->assertNotSame([], $second->wikidataUrlPatterns(), 'A failure must not be cached for the full TTL');

        $this->assertSame(2, $mock->requestCount());
    }

    public function test_the_cache_ttl_is_configurable()
    {
        $this->assertIsInt(config('resources.sync.cache_ttl'));
    }

    public function test_syncing_from_wikidata_creates_the_claimed_resources()
    {
        $model = TestModel::create(['name' => 'Sync']);
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q42',
            'url' => 'https://www.wikidata.org/wiki/Q42',
        ]);

        $mock = new MockProviderClient([
            $this->entityResponse($this->claim('P227', '118500775')),
        ]);

        $synced = (new ResourceSyncService([], $mock->client))->syncFromProvider($model, 'wikidata');

        $this->assertNotEmpty($synced);
        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $model->id,
            'provider' => 'gnd',
            'url' => 'https://d-nb.info/gnd/118500775',
        ]);
    }

    public function test_the_exclusion_filter_skips_a_provider()
    {
        $model = TestModel::create(['name' => 'Filtered']);
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q42',
            'url' => 'https://www.wikidata.org/wiki/Q42',
        ]);

        $mock = new MockProviderClient([
            $this->entityResponse(array_merge(
                $this->claim('P227', '118500775'),
                $this->claim('P214', '12345')
            )),
        ]);

        (new ResourceSyncService(['viaf'], $mock->client))->syncFromProvider($model, 'wikidata');

        $this->assertDatabaseMissing('resources', ['resourceable_id' => $model->id, 'provider' => 'viaf']);
        $this->assertDatabaseHas('resources', ['resourceable_id' => $model->id, 'provider' => 'gnd']);
    }

    public function test_an_unreachable_wikidata_returns_an_empty_array()
    {
        $model = TestModel::create(['name' => 'Offline']);
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q42',
            'url' => 'https://www.wikidata.org/wiki/Q42',
        ]);

        $mock = new MockProviderClient([
            new ConnectException('offline', new Request('GET', 'https://www.wikidata.org/w/api.php')),
        ]);

        $result = (new ResourceSyncService([], $mock->client))->syncFromProvider($model, 'wikidata');

        $this->assertSame([], $result);
    }

    public function test_a_metagrid_payload_without_concordances_returns_an_empty_array()
    {
        $model = TestModel::create(['name' => 'Metagrid']);
        $model->updateOrCreateResource([
            'provider' => 'metagrid',
            'provider_id' => '47451',
            'url' => 'https://api.metagrid.ch/concordance/47451.json',
        ]);

        $mock = new MockProviderClient([
            new Response(200, [], json_encode(['unexpected' => true])),
        ]);

        $result = (new ResourceSyncService([], $mock->client))->syncFromProvider($model, 'metagrid');

        $this->assertSame([], $result);
    }

    /**
     * The idref provider declares wikidata_property P269 and now also a
     * target_url, which is what makes the generic sync path create the link.
     * No IdRef-specific code should be needed in ResourceSyncService.
     */
    public function test_a_p269_claim_becomes_an_idref_resource()
    {
        $model = TestModel::create(['name' => 'IdRef sync']);
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q42',
            'url' => 'https://www.wikidata.org/wiki/Q42',
        ]);

        $mock = new MockProviderClient([
            $this->entityResponse($this->claim('P269', '026707357')),
        ]);

        (new ResourceSyncService([], $mock->client))->syncFromProvider($model, 'wikidata');

        $this->assertDatabaseHas('resources', [
            'resourceable_id' => $model->id,
            'provider' => 'idref',
            'provider_id' => '026707357',
            'url' => 'https://www.idref.fr/026707357',
        ]);
    }

    public function test_the_filter_suppresses_the_idref_resource()
    {
        $model = TestModel::create(['name' => 'IdRef filtered']);
        $model->updateOrCreateResource([
            'provider' => 'wikidata',
            'provider_id' => 'Q42',
            'url' => 'https://www.wikidata.org/wiki/Q42',
        ]);

        $mock = new MockProviderClient([
            $this->entityResponse(array_merge(
                $this->claim('P269', '026707357'),
                $this->claim('P227', '118500775')
            )),
        ]);

        (new ResourceSyncService(['idref'], $mock->client))->syncFromProvider($model, 'wikidata');

        $this->assertDatabaseMissing('resources', ['resourceable_id' => $model->id, 'provider' => 'idref']);
        $this->assertDatabaseHas('resources', ['resourceable_id' => $model->id, 'provider' => 'gnd']);
    }

    public function test_syncing_without_an_existing_resource_returns_an_empty_array()
    {
        $model = TestModel::create(['name' => 'Nothing']);

        $mock = new MockProviderClient([]);

        $this->assertSame([], (new ResourceSyncService([], $mock->client))->syncFromProvider($model, 'gnd'));
        $this->assertSame(0, $mock->requestCount());
    }
}
