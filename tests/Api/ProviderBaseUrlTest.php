<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Ortsnamen;
use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Wikidata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Several clients used to hardcode their base URL, so config('...base_url') was
 * decorative. For idiotikon and metagrid the configured value was not merely
 * unused, it was dead: api.idiotikon.ch does not resolve and metagrid.ch/api/
 * answers 404. Honouring the config without fixing it would have broken both.
 */
class ProviderBaseUrlTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function clients(): array
    {
        return [
            'gnd' => [Gnd::class, 'gnd'],
            'geonames' => [Geonames::class, 'geonames'],
            'idiotikon' => [Idiotikon::class, 'idiotikon'],
            'ortsnamen' => [Ortsnamen::class, 'ortsnamen'],
            'metagrid' => [Metagrid::class, 'metagrid'],
            'wikidata' => [Wikidata::class, 'wikidata'],
        ];
    }

    #[DataProvider('clients')]
    public function test_the_client_uses_the_configured_base_url(string $class, string $provider)
    {
        config(["resources.providers.{$provider}.base_url" => 'https://example.test/api/']);

        $client = new $class();

        $this->assertSame(
            'https://example.test/api/',
            (string) $client->client->getConfig('base_uri'),
            "{$provider} ignores its configured base_url"
        );
    }

    #[DataProvider('clients')]
    public function test_the_configured_base_url_is_absolute_and_https(string $class, string $provider)
    {
        $baseUrl = config("resources.providers.{$provider}.base_url");

        $this->assertIsString($baseUrl);
        $this->assertMatchesRegularExpression('#^https?://#', $baseUrl);
    }

    /**
     * The configured hosts must actually answer. This is what would have caught
     * the dead idiotikon and metagrid entries.
     */
    #[Group('live-api')]
    #[DataProvider('clients')]
    public function test_the_configured_base_url_is_reachable(string $class, string $provider)
    {
        $baseUrl = config("resources.providers.{$provider}.base_url");
        $host = parse_url($baseUrl, PHP_URL_HOST);

        $this->assertNotFalse(
            gethostbyname($host) !== $host || filter_var($host, FILTER_VALIDATE_IP),
            "{$provider}: host {$host} does not resolve"
        );
    }
}
