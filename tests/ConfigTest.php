<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_default_providers_config_exists()
    {
        $providers = config('resources.providers');

        // Test dass die Basis-Provider existieren
        $this->assertIsArray($providers);
        $this->assertArrayHasKey('wikidata', $providers);
        $this->assertArrayHasKey('gnd', $providers);
        $this->assertArrayHasKey('wikipedia-de', $providers);
    }

    public function test_wikidata_provider_has_required_config()
    {
        $wikidataProvider = config('resources.providers.wikidata');

        $this->assertArrayHasKey('label', $wikidataProvider);
        $this->assertArrayHasKey('base_url', $wikidataProvider);
        $this->assertArrayHasKey('target_url', $wikidataProvider);
        $this->assertEquals('Wikidata', $wikidataProvider['label']);
    }

    public function test_gnd_provider_has_required_config()
    {
        $gndProvider = config('resources.providers.gnd');

        $this->assertArrayHasKey('label', $gndProvider);
        $this->assertArrayHasKey('base_url', $gndProvider);
        $this->assertArrayHasKey('target_url', $gndProvider);
        $this->assertEquals('GND', $gndProvider['label']);
    }

    public function test_providers_config_is_published()
    {
        // Test dass die Config-Datei korrekt geladen wird
        $this->assertNotNull(config('resources.providers'));
        $this->assertIsArray(config('resources.providers'));
    }
}
