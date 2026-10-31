<?php

namespace KraenzleRitter\Resources\Tests;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Helpers\HttpClient;
use KraenzleRitter\Resources\Helpers\LaravelClient;
use KraenzleRitter\Resources\Helpers\UserAgent;

/**
 * Provider clients send through the Laravel HTTP client (anton#791) and
 * follow no redirect (ASVS V15.3.2).
 */
class HttpClientTest extends TestCase
{
    public function test_a_provider_request_goes_through_the_laravel_client(): void
    {
        Http::fake(['https://lobid.org/*' => Http::response(['member' => [], 'totalItems' => 0])]);

        (new Gnd())->search('Hannah Arendt');

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://lobid.org/gnd/search?q=Hannah+Arendt')
            && $request->hasHeader('User-Agent', UserAgent::get()['User-Agent']));
    }

    /**
     * A faked answer still passes Guzzle's redirect middleware, so this is the
     * real redirect handling.
     */
    public function test_no_redirect_is_followed_unless_the_caller_allows_one(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.example/'])]);

        $response = HttpClient::make([])->get('https://lobid.org/gnd/search');

        $this->assertSame(302, $response->getStatusCode(), 'the 3xx comes back as the response');
        Http::assertSentCount(1);

        try {
            HttpClient::make(['allow_redirects' => ['max' => 1]])->get('https://lobid.org/gnd/search');
            $this->fail('a caller that allows one redirect follows one');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Will not follow more than 1 redirects', $e->getMessage());
        }
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://elsewhere.example/');
    }

    /**
     * Guzzle resolves a path against base_uri as RFC 3986 does; Laravel would
     * join them by string and turn `…/api.php` with `?q` into `…/api.php/?q`.
     */
    public function test_the_url_is_resolved_against_the_base_as_guzzle_does(): void
    {
        Http::fake(['*' => Http::response('{}')]);
        $client = HttpClient::make(['base_uri' => 'https://de.wikipedia.org/w/api.php']);

        $client->get('?action=query');
        $client->get('', ['query' => ['action' => 'parse']]);
        $client->get('https://api.metagrid.ch/concordance/1.json');
        HttpClient::make(['base_uri' => 'https://lobid.org/gnd/'])->get('search?q=x');

        $this->assertSame([
            'https://de.wikipedia.org/w/api.php?action=query',
            'https://de.wikipedia.org/w/api.php?action=parse',
            'https://api.metagrid.ch/concordance/1.json',
            'https://lobid.org/gnd/search?q=x',
        ], Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all());
    }

    public function test_a_connection_failure_is_guzzles_and_the_provider_falls_back(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        try {
            HttpClient::make([])->get('https://lobid.org/gnd/search');
            $this->fail('expected a ConnectException');
        } catch (ConnectException $e) {
            $this->assertStringContainsString('lobid.org', (string) $e->getRequest()->getUri());
        }

        $this->assertSame(0, (new Gnd())->search('Hannah Arendt')->totalItems);
    }

    public function test_the_providers_build_their_clients_through_the_helper()
    {
        $this->assertInstanceOf(LaravelClient::class, (new Gnd())->client);
        $this->assertFalse((new Geonames())->client->getConfig('allow_redirects'));

        foreach (glob(__DIR__ . '/../src/{,*/,*/*/}*.php', GLOB_BRACE) as $file) {
            $this->assertDoesNotMatchRegularExpression('/new\s+\\\\?(GuzzleHttp\\\\)?Client\s*\(/', file_get_contents($file), basename($file) . ' builds its own Guzzle client');
        }
    }

    public function test_geonames_is_called_over_https()
    {
        $this->assertSame('https://secure.geonames.org/', Geonames::DEFAULT_BASE_URL);
        $this->assertStringStartsWith('https://', config('resources.providers.geonames.base_url'));
    }
}
