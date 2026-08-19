<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use KraenzleRitter\Resources\Tests\TestCase;

class GeonamesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['resources.providers.geonames.user_name' => 'test-account']);
        config(['resources.limit' => 5]);
    }

    public function test_search_returns_the_geonames_array()
    {
        $mock = MockProviderClient::withFixture('geonames', 'search');

        $results = (new Geonames($mock->client))->search('Augsburg', ['limit' => 3]);

        $expected = MockProviderClient::fixtureData('geonames', 'search')['geonames'];

        $this->assertIsArray($results);
        $this->assertCount(count($expected), $results);
        $this->assertSame('Augsburg', $results[0]['toponymName']);
        $this->assertSame($expected[0]['geonameId'], $results[0]['geonameId']);
    }

    public function test_search_sends_the_configured_username_and_limit()
    {
        $mock = MockProviderClient::withFixture('geonames', 'search');

        (new Geonames($mock->client))->search('Augsburg', ['limit' => 3]);

        $query = $mock->lastQuery();

        $this->assertSame(1, $mock->requestCount());
        $this->assertSame('Augsburg', $query['q']);
        $this->assertSame('test-account', $query['username']);
        $this->assertSame('3', $query['maxRows']);
    }

    public function test_search_returns_empty_array_when_nothing_matches()
    {
        $mock = MockProviderClient::withFixture('geonames', 'empty');

        $results = (new Geonames($mock->client))->search('zzzzzzzz', ['limit' => 3]);

        $this->assertSame([], $results);
    }

    /**
     * Geonames answers HTTP 200 with a status object when the account is over
     * its quota — the client has to treat that as "no results", not as data.
     */
    public function test_rate_limited_response_yields_no_results()
    {
        $mock = MockProviderClient::withFixture('geonames', 'rate-limited');

        $results = (new Geonames($mock->client))->search('Augsburg', ['limit' => 3]);

        $this->assertSame([], $results);
    }
}
