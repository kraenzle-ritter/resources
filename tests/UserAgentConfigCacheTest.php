<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Helpers\UserAgent;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;

/**
 * UserAgent::get() used to call env() at request time. Once a host application
 * runs `php artisan config:cache`, .env is no longer loaded and env() returns
 * null — so the package would have sent an empty User-Agent to APIs (lobid,
 * Wikimedia) that require a descriptive one.
 */
class UserAgentConfigCacheTest extends TestCase
{
    public function test_the_user_agent_comes_from_config_not_from_env()
    {
        config(['resources.user_agent' => 'configured-agent/9.9 (+https://example.test)']);
        putenv('RESOURCES_USER_AGENT=env-agent-should-be-ignored');

        $this->assertSame('configured-agent/9.9 (+https://example.test)', UserAgent::get()['User-Agent']);

        putenv('RESOURCES_USER_AGENT');
    }

    /**
     * With a cached config, env() returns null. The header must survive that.
     */
    public function test_the_user_agent_survives_a_cached_config_with_no_env()
    {
        putenv('RESOURCES_USER_AGENT');

        $agent = UserAgent::get()['User-Agent'];

        $this->assertIsString($agent);
        $this->assertNotSame('', $agent);
        $this->assertStringContainsString('resources/', $agent);
        $this->assertStringContainsString('github.com/kraenzle-ritter/resources', $agent);
    }

    public function test_provider_requests_carry_the_user_agent()
    {
        config(['resources.user_agent' => 'configured-agent/9.9 (+https://example.test)']);

        $mock = MockProviderClient::withFixture('gnd', 'search');

        // The mock replaces the whole client, so assert on the header the real
        // client would be built with rather than on the outgoing request.
        $this->assertSame(
            ['User-Agent' => 'configured-agent/9.9 (+https://example.test)'],
            UserAgent::get()
        );

        $real = new Gnd();
        $headers = $real->client->getConfig('headers');

        $this->assertSame('configured-agent/9.9 (+https://example.test)', $headers['User-Agent']);
    }
}
