<?php

namespace KraenzleRitter\Resources\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Helpers\HttpClient;
use ReflectionProperty;

/**
 * Provider clients follow no redirect (ASVS V15.3.2).
 */
class HttpClientTest extends TestCase
{
    public function test_a_redirect_comes_back_as_the_response()
    {
        $mock = new MockHandler([
            new Response(302, ['Location' => 'https://elsewhere.example/']),
            new Response(200, [], 'followed'),
        ]);

        $response = HttpClient::make(['handler' => HandlerStack::create($mock)])->get('https://lobid.org/gnd/search');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertCount(1, $mock, 'the redirect target was not requested');
    }

    public function test_a_caller_may_allow_redirects_itself()
    {
        $mock = new MockHandler([
            new Response(302, ['Location' => 'https://lobid.org/gnd/other']),
            new Response(200, [], 'followed'),
        ]);

        $response = HttpClient::make([
            'handler' => HandlerStack::create($mock),
            'allow_redirects' => ['max' => 1],
        ])->get('https://lobid.org/gnd/search');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_the_providers_build_their_clients_through_the_helper()
    {
        $this->assertFalse($this->allowRedirects((new Gnd())->client));
        $this->assertFalse($this->allowRedirects((new Geonames())->client));

        foreach (glob(__DIR__ . '/../src/{,*/,*/*/}*.php', GLOB_BRACE) as $file) {
            if (str_ends_with($file, 'Helpers/HttpClient.php')) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/new\s+\\\\?(GuzzleHttp\\\\)?Client\s*\(/', file_get_contents($file), basename($file) . ' builds its own Guzzle client');
        }
    }

    public function test_geonames_is_called_over_https()
    {
        $this->assertSame('https://secure.geonames.org/', Geonames::DEFAULT_BASE_URL);
        $this->assertStringStartsWith('https://', config('resources.providers.geonames.base_url'));
    }

    private function allowRedirects(Client $client): mixed
    {
        return (new ReflectionProperty(Client::class, 'config'))->getValue($client)['allow_redirects'];
    }
}
