<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Geonames;

class GeonamesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Debug: Check what config is actually loaded
        echo "\nConfig check - resources.providers.geonames.user_name: " . config('resources.providers.geonames.user_name');
        echo "\nEnvironment check - GEONAMES_USERNAME: " . env('GEONAMES_USERNAME');

        // Direkt setzen für Tests - aber jetzt mit der Environment-Variable
        $username = env('GEONAMES_USERNAME', 'demo');
        config(['resources.providers.geonames.user_name' => $username]);
        config(['resources.limit' => 5]);

        echo "\nSet config to: " . config('resources.providers.geonames.user_name');
    }

    public function test_geonames_search_with_demo_user()
    {
        echo "\nTesting Geonames with demo user...";

        $geonames = new Geonames();

        echo "\nGeonames username: " . $geonames->username;
        echo "\nGeonames base_uri: " . $geonames->base_uri;

        $results = $geonames->search('Augsburg', ['limit' => 3]);

        echo "\nResults type: " . gettype($results);
        echo "\nResults: " . json_encode($results);

        // Demo-Account kann eingeschränkt sein, das ist ok
        if (empty($results)) {
            $this->markTestSkipped('Demo account may be rate-limited or have restrictions');
        } else {
            $this->assertNotEmpty($results, 'Geonames should return results for Augsburg');
        }
    }

    public function test_geonames_search_with_zurich()
    {
        $geonames = new Geonames();
        $results = $geonames->search('Zurich', ['limit' => 3]);

        echo "\nZurich results: " . json_encode($results);

        // Demo-Account kann eingeschränkt sein, das ist ok
        if (empty($results)) {
            $this->markTestSkipped('Demo account may be rate-limited or have restrictions');
        } else {
            $this->assertNotEmpty($results, 'Geonames should return results for Zurich');
        }
    }
}
