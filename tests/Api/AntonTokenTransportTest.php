<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use KraenzleRitter\Resources\Tests\TestCase;

/**
 * The Anton api_token used to travel as a query parameter, so it ended up in
 * the upstream access log, in any proxy in between, and in the Referer of any
 * link the response caused the browser to follow.
 */
class AntonTokenTransportTest extends TestCase
{
    private const TOKEN = 'anton-test-token-42';

    protected function setUp(): void
    {
        parent::setUp();

        config(['resources.providers.georgfischer.api_token' => self::TOKEN]);
    }

    public function test_the_token_is_sent_as_a_bearer_header_by_default()
    {
        $mock = MockProviderClient::withFixture('anton', 'search');

        (new Anton('georgfischer', $mock->client))->search('Fischer', [], 'actors');

        $this->assertSame('Bearer ' . self::TOKEN, $mock->lastHeader('Authorization'));
        $this->assertArrayNotHasKey('api_token', $mock->lastQuery());
        $this->assertStringNotContainsString(self::TOKEN, $mock->lastUri());
    }

    public function test_the_query_transport_can_be_restored_by_config()
    {
        config(['resources.providers.georgfischer.api_token_transport' => 'query']);

        $mock = MockProviderClient::withFixture('anton', 'search');

        (new Anton('georgfischer', $mock->client))->search('Fischer', [], 'actors');

        $this->assertNull($mock->lastHeader('Authorization'));
        $this->assertSame(self::TOKEN, $mock->lastQuery()['api_token'] ?? null);
    }

    public function test_no_token_means_no_authorization_header_and_no_parameter()
    {
        config(['resources.providers.georgfischer.api_token' => '']);

        $mock = MockProviderClient::withFixture('anton', 'search');

        (new Anton('georgfischer', $mock->client))->search('Fischer', [], 'actors');

        $this->assertNull($mock->lastHeader('Authorization'));
        $this->assertArrayNotHasKey('api_token', $mock->lastQuery());
    }

    /**
     * The real check: an authenticated search against a live Anton instance.
     * Skipped unless a token is configured, so it can run in CI (or locally)
     * once the secret is available. This is the gate for task 8.6a.
     */
    #[\PHPUnit\Framework\Attributes\Group('live-api')]
    public function test_live_anton_accepts_the_bearer_token()
    {
        $token = env('GEORGFISCHER_API_TOKEN');

        if (! $token) {
            $this->markTestSkipped('No GEORGFISCHER_API_TOKEN configured; cannot verify the Bearer transport end to end.');
        }

        config([
            'resources.providers.georgfischer.api_token' => $token,
            'resources.providers.georgfischer.api_token_transport' => 'header',
        ]);

        $results = (new Anton('georgfischer'))->search('Fischer', ['limit' => 3], 'actors');

        $this->assertNotEmpty($results, 'Live Anton search with a Bearer token returned nothing');
    }

    public function test_every_anton_provider_defaults_to_the_header_transport()
    {
        foreach (['georgfischer', 'gosteli', 'kba'] as $provider) {
            $this->assertSame(
                'header',
                config("resources.providers.{$provider}.api_token_transport"),
                "{$provider} must default to the header transport"
            );
        }
    }
}
