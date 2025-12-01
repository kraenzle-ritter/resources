<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Tests\TestCase;

class ProviderConfigurationTest extends TestCase
{
    /**
     * Testet, dass Provider-Konfiguration korrekt ist, ohne echte API-Calls zu machen
     */
    public function test_provider_configuration_completeness()
    {
        $providers = config('resources.providers');
        $incompleteProviders = [];
        $apiProvidersCount = 0;
        $apiProviders = [];

        foreach ($providers as $providerKey => $config) {
            // Überprüfe nur Provider die eine api-type haben (suchbare Provider)
            if (!isset($config['api-type'])) {
                continue; // Skip sync-only providers
            }

            $apiProvidersCount++;

            // Überprüfe erforderliche Konfigurationsfelder für suchbare Provider
            $requiredFields = ['label'];

            foreach ($requiredFields as $field) {
                if (!isset($config[$field])) {
                    $incompleteProviders[] = "{$providerKey} missing {$field}";
                }
            }

            // Für API-Provider (außer ManualInput) sollte base_url vorhanden sein
            if ($config['api-type'] !== 'ManualInput' && !isset($config['base_url'])) {
                $incompleteProviders[] = "{$providerKey} missing base_url";
            } else if (isset($config['base_url'])) {
                $apiProviders[] = $providerKey;
            }
        }

        echo "\nAPI Provider Statistik:";
        echo "\nProvider mit api-type insgesamt: {$apiProvidersCount}";
        echo "\nProvider mit base_url (testbar): " . count($apiProviders);
        echo "\nTestbare Provider: " . implode(', ', $apiProviders);

        $this->assertEmpty($incompleteProviders,
            'All searchable providers should have complete configuration. Missing: ' . implode(', ', $incompleteProviders));

        // Mindestens 10 API-Provider sollten konfiguriert sein
        $this->assertGreaterThanOrEqual(10, count($apiProviders),
            'At least 10 API providers should be configured with base_url');
    }

    /**
     * Testet, dass die wichtigsten Provider korrekt konfiguriert sind
     */
    public function test_core_providers_configuration()
    {
        $providers = config('resources.providers');

        $coreProviders = [
            'gnd' => 'Gnd',
            'wikidata' => 'Wikidata',
            'wikipedia-de' => 'Wikipedia',
            'geonames' => 'Geonames',
            'idiotikon' => 'Idiotikon'
        ];

        foreach ($coreProviders as $providerKey => $expectedApiType) {
            $this->assertArrayHasKey($providerKey, $providers,
                "Core provider {$providerKey} should be configured");

            $config = $providers[$providerKey];

            $this->assertEquals($expectedApiType, $config['api-type'],
                "Provider {$providerKey} should have api-type {$expectedApiType}");

            $this->assertArrayHasKey('base_url', $config,
                "Provider {$providerKey} should have base_url");

            $this->assertArrayHasKey('test_search', $config,
                "Provider {$providerKey} should have test_search term");

            echo "\n✓ {$providerKey}: {$config['api-type']} at {$config['base_url']}";
        }
    }

    /**
     * Zählt eindeutige API-Typen
     */
    public function test_api_type_diversity()
    {
        $providers = config('resources.providers');
        $apiTypes = [];

        foreach ($providers as $providerKey => $config) {
            if (isset($config['api-type'])) {
                if (!isset($apiTypes[$config['api-type']])) {
                    $apiTypes[$config['api-type']] = [];
                }
                $apiTypes[$config['api-type']][] = $providerKey;
            }
        }

        echo "\nAPI-Type Übersicht:";
        foreach ($apiTypes as $apiType => $providerKeys) {
            echo "\n  {$apiType}: " . count($providerKeys) . " Provider (" . implode(', ', $providerKeys) . ")";
        }

        // Mindestens 5 verschiedene API-Typen sollten unterstützt werden
        $this->assertGreaterThanOrEqual(5, count($apiTypes),
            'At least 5 different API types should be supported');
    }
}
