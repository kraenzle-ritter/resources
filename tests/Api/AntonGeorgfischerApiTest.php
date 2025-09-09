<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Tests\TestCase;

class AntonGeorgfischerApiTest extends TestCase
{
    protected $skipIfNoInternet = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip test if internet connection is not available
        if ($this->skipIfNoInternet) {
            try {
                $connection = @fsockopen("archives.georgfischer.com", 80);
                if (!$connection) {
                    $this->markTestSkipped('No internet connection available or Georg Fischer API not reachable');
                }
                fclose($connection);
            } catch (\Exception $e) {
                $this->markTestSkipped('No internet connection available or Georg Fischer API not reachable');
            }
        }
    }

    public function test_anton_georgfischer_search_returns_expected_structure()
    {
        // Arrange
        $anton = new Anton('georgfischer');
        $searchTerm = 'Fischer';

        $configuredUrl = config('resources.providers.georgfischer.base_url');
        $this->assertEquals($configuredUrl, $anton->url);

        $results = $anton->search($searchTerm, ['size' => 3], 'actors');

        // Assert
        $this->assertNotNull($results, 'Anton search for "Fischer" should return results');
        $this->assertNotEmpty($results, 'Anton search for "Fischer" results should not be empty');
        $this->assertLessThanOrEqual(3, count($results), 'Anton search should respect the size parameter');

        // Check structure of the first result
        if (!empty($results)) {
            $firstResult = $results[0];
            $this->assertTrue(property_exists($firstResult, 'id'), 'Result should have an id');
            $this->assertTrue(property_exists($firstResult, 'name'), 'Result should have a name');
        }
    }

    public function test_anton_georgfischer_results_can_be_used_as_resources()
    {
        // Arrange
        $anton = new Anton('georgfischer');
        $searchTerm = 'Fischer'; // Ein allgemeiner Suchbegriff, der wahrscheinlich Ergebnisse liefert

        // Die konfigurierte URL für Georgfischer überprüfen
        $configuredUrl = config('resources.providers.georgfischer.base_url');
        $this->assertEquals(
            'https://archives.georgfischer.com/api/',
            $configuredUrl,
            'Die konfigurierte URL für Georgfischer ist nicht korrekt'
        );

        // Act - Verwenden des 'actors' Endpoints, der ohne API-Token funktioniert
        $results = $anton->search($searchTerm, ['size' => 1], 'actors');        // Skip if no results
        if (empty($results)) {
            $this->markTestSkipped('No search results for Anton Georgfischer');
        }

        // Assert
        $firstResult = $results[0];

        // Erstelle eine Resource-Daten-Struktur, wie sie von AntonLwComponent verwendet würde
        // Dabei die konfigurierte URL verwenden, nicht hartcodieren
        $configuredUrl = config('resources.providers.georgfischer.base_url');
        $resourceData = [
            'provider' => 'Georgfischer',
            'provider_id' => $firstResult->id ?? '',
            'url' => $configuredUrl . 'actors/' . ($firstResult->id ?? ''),
            'full_json' => json_encode($firstResult)
        ];

        // Überprüfen, ob die URL tatsächlich mit der konfigurierten URL beginnt
        $this->assertStringStartsWith(
            $configuredUrl,
            $resourceData['url'],
            'Die Resource-URL verwendet nicht die konfigurierte Base-URL'
        );

        // Überprüfe, ob alle notwendigen Daten vorhanden sind
        $this->assertArrayHasKey('provider', $resourceData);
        $this->assertArrayHasKey('provider_id', $resourceData);
        $this->assertArrayHasKey('url', $resourceData);
        $this->assertArrayHasKey('full_json', $resourceData);

        $this->assertEquals('Georgfischer', $resourceData['provider']);
        $this->assertNotEmpty($resourceData['provider_id']);
        $this->assertNotEmpty($resourceData['url']);
        $this->assertJson($resourceData['full_json']);
    }
}
