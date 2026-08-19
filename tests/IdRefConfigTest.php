<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\IdRef;

class IdRefConfigTest extends TestCase
{
    public function test_the_provider_is_fully_configured()
    {
        $config = config('resources.providers.idref');

        $this->assertSame('IdRef', $config['api-type']);
        $this->assertSame('https://www.idref.fr/Sru/Solr', $config['base_url']);
        $this->assertSame('https://www.idref.fr/{provider_id}', $config['target_url']);
        $this->assertNotEmpty($config['test_search']);
        $this->assertSame('IdRef', $config['label']);
    }

    public function test_the_wikidata_property_is_preserved()
    {
        // P269 was already configured; the provider was just inert without an
        // api-type and a target_url.
        $this->assertSame('P269', config('resources.providers.idref.wikidata_property'));
    }

    public function test_the_record_type_map_is_complete()
    {
        $types = config('resources.providers.idref.record_types');

        foreach (['person', 'corporate', 'place', 'family', 'subject'] as $type) {
            $this->assertArrayHasKey($type, $types);
            $this->assertArrayHasKey('code', $types[$type]);
            $this->assertArrayHasKey('index', $types[$type]);
            $this->assertStringEndsWith('_t', $types[$type]['index']);
        }
    }

    public function test_the_record_type_codes_match_what_idref_returns()
    {
        $codes = array_column(config('resources.providers.idref.record_types'), 'code', 'index');

        // Verified empirically against the Solr service, one probe per letter.
        $this->assertSame('a', $codes['persname_t']);
        $this->assertSame('b', $codes['corpname_t']);
        $this->assertSame('c', $codes['geogname_t']);
        $this->assertSame('e', $codes['famname_t']);
        $this->assertSame('j', $codes['subjectheading_t']);
    }

    public function test_the_endpoint_mapping_covers_the_consumer_endpoints()
    {
        $map = config('resources.providers.idref.endpoint_record_types');

        $this->assertSame(['person', 'corporate', 'family'], $map['actors']);
        $this->assertSame(['place'], $map['places']);
        $this->assertSame(['subject'], $map['keywords']);
    }

    public function test_the_default_record_types_are_valid_keys()
    {
        $types = config('resources.providers.idref.record_types');

        foreach (config('resources.providers.idref.default_record_types') as $type) {
            $this->assertArrayHasKey($type, $types);
        }
    }

    public function test_the_sudoc_rename_still_resolves_to_idref()
    {
        $this->assertSame('idref', config('resources.rename.sudoc'));

        $model = TestModel::create(['name' => 'Rename']);
        $resource = $model->updateOrCreateResource([
            'provider' => 'sudoc',
            'provider_id' => '026707357',
            'url' => 'https://www.idref.fr/026707357',
        ]);

        $this->assertSame('idref', $resource->provider);
    }

    public function test_an_unmapped_endpoint_falls_back_to_a_working_search()
    {
        $mock = \KraenzleRitter\Resources\Tests\Support\MockProviderClient::withFixture('idref', 'person-search');

        $results = (new IdRef($mock->client))->search('Karl Barth', ['endpoint' => 'songs']);

        $this->assertNotEmpty($results, 'An endpoint with no mapping must still search');
    }
}
