<?php

namespace KraenzleRitter\Resources\Tests\Api;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Ortsnamen;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Wikipedia;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every provider client owes the caller the same contract: an upstream failure
 * is logged and degrades to an empty result. It must never throw — an edit page
 * in a host application should survive an API outage.
 */
class ProviderHttpContractTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function providers(): array
    {
        return [
            'gnd' => ['gnd'],
            'geonames' => ['geonames'],
            'idiotikon' => ['idiotikon'],
            'ortsnamen' => ['ortsnamen'],
            'metagrid' => ['metagrid'],
            'wikidata' => ['wikidata'],
            'wikipedia' => ['wikipedia'],
            'anton' => ['anton'],
        ];
    }

    /**
     * Build the client with an injected HTTP client and run its search.
     */
    private function search(string $provider, \Psr\Http\Message\ResponseInterface|\Throwable $response): mixed
    {
        $mock = new MockProviderClient([$response]);

        return match ($provider) {
            'gnd' => (new Gnd($mock->client))->search('test'),
            'geonames' => (new Geonames($mock->client))->search('test', ['limit' => 5]),
            'idiotikon' => (new Idiotikon($mock->client))->search('test'),
            'ortsnamen' => (new Ortsnamen($mock->client))->search('test'),
            'metagrid' => (new Metagrid($mock->client))->search('test'),
            'wikidata' => (new Wikidata($mock->client))->search('test'),
            'wikipedia' => (new Wikipedia($mock->client))->search('test', ['providerKey' => 'wikipedia-de']),
            'anton' => (new Anton('georgfischer', $mock->client))->search('test', [], 'actors'),
        };
    }

    private function assertEmptyResult(string $provider, mixed $result): void
    {
        if ($provider === 'gnd') {
            // GND answers with an object carrying a member list.
            $this->assertIsObject($result);
            $this->assertSame([], (array) $result->member);

            return;
        }

        $this->assertIsArray($result, "{$provider}: an empty result must be an array, got " . gettype($result));
        $this->assertSame([], $result, "{$provider}: expected an empty array");
    }

    #[DataProvider('providers')]
    public function test_a_server_error_yields_an_empty_result(string $provider)
    {
        $this->assertEmptyResult($provider, $this->search($provider, new Response(500, [], 'Internal Server Error')));
    }

    #[DataProvider('providers')]
    public function test_a_client_error_yields_an_empty_result(string $provider)
    {
        $this->assertEmptyResult($provider, $this->search($provider, new Response(404, [], 'Not Found')));
    }

    #[DataProvider('providers')]
    public function test_malformed_json_yields_an_empty_result(string $provider)
    {
        $this->assertEmptyResult($provider, $this->search($provider, new Response(200, [], 'this is not json at all <html>')));
    }

    #[DataProvider('providers')]
    public function test_an_unexpected_payload_shape_yields_an_empty_result(string $provider)
    {
        $this->assertEmptyResult($provider, $this->search($provider, new Response(200, [], '{"totally":"unexpected","shape":true}')));
    }

    #[DataProvider('providers')]
    public function test_an_empty_body_yields_an_empty_result(string $provider)
    {
        $this->assertEmptyResult($provider, $this->search($provider, new Response(200, [], '')));
    }

    #[DataProvider('providers')]
    public function test_a_connection_failure_yields_an_empty_result(string $provider)
    {
        $exception = new ConnectException('Connection timed out', new Request('GET', 'https://example.org'));

        $this->assertEmptyResult($provider, $this->search($provider, $exception));
    }

    #[DataProvider('providers')]
    public function test_a_json_null_body_yields_an_empty_result(string $provider)
    {
        $this->assertEmptyResult($provider, $this->search($provider, new Response(200, [], 'null')));
    }
}
