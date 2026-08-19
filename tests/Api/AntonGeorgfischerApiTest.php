<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use KraenzleRitter\Resources\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

class AntonGeorgfischerApiTest extends TestCase
{
    public function test_client_takes_its_url_from_the_provider_config()
    {
        $anton = new Anton('georgfischer');

        $this->assertSame(
            config('resources.providers.georgfischer.base_url'),
            $anton->url
        );
        $this->assertSame('https://archives.georgfischer.com/api/', $anton->url);
    }

    public function test_search_returns_the_data_array()
    {
        $mock = MockProviderClient::withFixture('anton', 'search');

        $results = (new Anton('georgfischer', $mock->client))->search('Fischer', ['size' => 3], 'actors');

        $expected = MockProviderClient::fixtureData('anton', 'search')['data'];

        $this->assertNotEmpty($results);
        $this->assertCount(count($expected), $results);
        $this->assertTrue(property_exists($results[0], 'id'));
        $this->assertTrue(property_exists($results[0], 'name'));
        $this->assertSame($expected[0]['id'], $results[0]->id);
    }

    public function test_search_hits_the_requested_endpoint_with_paging_parameters()
    {
        $mock = MockProviderClient::withFixture('anton', 'search');

        (new Anton('georgfischer', $mock->client))->search('Fischer', ['size' => 3], 'actors');

        $query = $mock->lastQuery();

        $this->assertStringContainsString('actors', $mock->lastUri());
        $this->assertSame('Fischer', $query['search']);
        $this->assertSame('3', $query['perPage']);
        $this->assertSame('1', $query['page']);
    }

    public function test_search_returns_empty_array_when_nothing_matches()
    {
        $mock = MockProviderClient::withFixture('anton', 'empty');

        $results = (new Anton('georgfischer', $mock->client))->search('zzzzzzzz', ['size' => 3], 'actors');

        $this->assertSame([], $results);
    }

    /**
     * A result has to carry everything AntonLwComponent needs to build a
     * resource row.
     */
    public function test_a_result_can_be_turned_into_resource_data()
    {
        $mock = MockProviderClient::withFixture('anton', 'search');

        $results = (new Anton('georgfischer', $mock->client))->search('Fischer', ['size' => 1], 'actors');

        $baseUrl = config('resources.providers.georgfischer.base_url');

        $resourceData = [
            'provider' => 'georgfischer',
            'provider_id' => $results[0]->id,
            'url' => $baseUrl . 'actors/' . $results[0]->id,
            'full_json' => json_encode($results[0]),
        ];

        $this->assertStringStartsWith($baseUrl, $resourceData['url']);
        $this->assertNotEmpty($resourceData['provider_id']);
        $this->assertJson($resourceData['full_json']);
    }

    #[Group('live-api')]
    public function test_live_georgfischer_search_still_answers()
    {
        $results = (new Anton('georgfischer'))->search('Fischer', ['size' => 3], 'actors');

        $this->assertNotEmpty($results, 'Live Georg Fischer API returned no results');
        $this->assertLessThanOrEqual(3, count($results));
    }
}
