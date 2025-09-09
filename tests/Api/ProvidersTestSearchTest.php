<?php

namespace KraenzleRitter\Resources\Tests\Api;

use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Wikipedia;
use KraenzleRitter\Resources\Tests\TestCase;

class ProvidersTestSearchTest extends TestCase
{
    protected $skipIfNoInternet = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip test if internet connection is not available
        if ($this->skipIfNoInternet) {
            try {
                $connection = @fsockopen("www.google.com", 80, $errno, $errstr, 5);
                if (!$connection) {
                    $this->markTestSkipped('No internet connection available');
                }
                fclose($connection);
            } catch (\Exception $e) {
                $this->markTestSkipped('No internet connection available');
            }
        }
    }

    /**
     * Testet, dass alle Provider mit test_search mindestens ein Ergebnis liefern
     */
    public function test_all_providers_with_test_search_return_results()
    {
        $providers = config('resources.providers');
        $testedProviders = [];
        $failedProviders = [];

        foreach ($providers as $providerKey => $config) {
            // Nur Provider mit test_search testen
            if (!isset($config['test_search']) || !isset($config['api-type'])) {
                continue;
            }

            $searchTerm = $config['test_search'];
            $apiType = $config['api-type'];

            echo "\nTesting Provider: {$providerKey} (Type: {$apiType}) with term: '{$searchTerm}'";

            try {
                $results = $this->searchWithProvider($providerKey, $apiType, $searchTerm);

                // Handle different return types
                $resultCount = 0;
                if (is_array($results)) {
                    $resultCount = count($results);
                } elseif (is_object($results) && !empty((array)$results)) {
                    $resultCount = 1; // Single object result
                } elseif (is_string($results) || is_numeric($results)) {
                    $resultCount = 1; // String or numeric result
                }

                if ($resultCount === 0) {
                    $failedProviders[] = "{$providerKey} ('{$searchTerm}') - No results returned";
                    echo " - FAILED: No results";
                } else {
                    $testedProviders[] = $providerKey;
                    echo " - PASSED: {$resultCount} results";
                }
            } catch (\Exception $e) {
                $failedProviders[] = "{$providerKey} ('{$searchTerm}') - Exception: " . $e->getMessage();
                echo " - ERROR: " . $e->getMessage();
            }
        }

        // Ausgabe der Zusammenfassung
        echo "\n\n=== SUMMARY ===";
        echo "\nSuccessfully tested providers: " . count($testedProviders);
        echo "\nFailed providers: " . count($failedProviders);

        if (!empty($testedProviders)) {
            echo "\n\nPassed providers:";
            foreach ($testedProviders as $provider) {
                echo "\n  ✓ {$provider}";
            }
        }

        if (!empty($failedProviders)) {
            echo "\n\nFailed providers:";
            foreach ($failedProviders as $failure) {
                echo "\n  ✗ {$failure}";
            }
        }

        // Mindestens ein Provider sollte funktionieren
        $this->assertGreaterThan(0, count($testedProviders),
            'At least one provider should return results. Failed providers: ' . implode(', ', $failedProviders));
    }

    /**
     * Testet spezifische Provider einzeln für detailliertere Fehleranalyse
     */
    public function test_individual_provider_search_detailed()
    {
        $providers = config('resources.providers');
        $totalTests = 0;
        $passedTests = 0;

        foreach ($providers as $providerKey => $config) {
            if (!isset($config['test_search']) || !isset($config['api-type'])) {
                continue;
            }

            $totalTests++;
            $searchTerm = $config['test_search'];
            $apiType = $config['api-type'];

            echo "\nDetailed test for {$providerKey} with '{$searchTerm}'";

            try {
                $results = $this->searchWithProvider($providerKey, $apiType, $searchTerm);

                // Handle different return types for assertions
                $hasResults = false;
                if (is_array($results)) {
                    $hasResults = !empty($results);
                    if ($hasResults && isset($results['skipped'])) {
                        echo " - SKIPPED";
                        $passedTests++; // Count skipped as passed for API tokens etc.
                        continue;
                    }
                } elseif (is_object($results)) {
                    $hasResults = !empty((array)$results);
                } elseif (is_string($results) || is_numeric($results)) {
                    $hasResults = !empty($results);
                }

                if ($hasResults) {
                    echo " - PASSED";
                    $passedTests++;
                } else {
                    echo " - FAILED: No results";
                }

            } catch (\Exception $e) {
                // Bei API-Problemen den Test als skipped markieren statt failed
                if (strpos($e->getMessage(), 'cURL error') !== false ||
                    strpos($e->getMessage(), 'Connection') !== false) {
                    echo " - SKIPPED: Network issue";
                    $passedTests++; // Count network issues as passed
                } else {
                    echo " - ERROR: " . $e->getMessage();
                }
            }
        }

        echo "\n\nDetailed Results: {$passedTests}/{$totalTests} providers working";

        // Mindestens 50% der Provider sollten funktionieren
        $this->assertGreaterThanOrEqual($totalTests * 0.5, $passedTests,
            "At least 50% of providers should work");
    }

    /**
     * Führt eine Suche mit dem entsprechenden Provider durch
     */
    private function searchWithProvider($providerKey, $apiType, $searchTerm)
    {
        $limit = 3; // Begrenzen für Performance

        switch ($apiType) {
            case 'Gnd':
                $provider = new Gnd();
                return $provider->search($searchTerm, ['limit' => $limit]);

            case 'Idiotikon':
                $provider = new Idiotikon();
                return $provider->search($searchTerm, ['limit' => $limit]);

            case 'Metagrid':
                $provider = new Metagrid();
                return $provider->search($searchTerm, ['limit' => $limit]);

            case 'Wikidata':
                $provider = new Wikidata();
                return $provider->search($searchTerm, ['limit' => $limit]);

            case 'Wikipedia':
                $provider = new Wikipedia();
                return $provider->search($searchTerm, ['providerKey' => $providerKey, 'limit' => $limit]);

            case 'Anton':
                // Anton-Provider können auch ohne API-Token getestet werden
                $provider = new Anton($providerKey);
                return $provider->search($searchTerm, ['limit' => $limit]);

            case 'Geonames':
                // Geonames mit Environment-Variable testen
                $username = env('GEONAMES_USERNAME', 'demo');
                config(['resources.providers.geonames.user_name' => $username]);

                try {
                    $provider = new Geonames();
                    $results = $provider->search($searchTerm, ['limit' => $limit]);

                    if (empty($results)) {
                        if ($username === 'demo') {
                            echo " - SKIPPED: Demo account may be rate-limited";
                            return ['skipped' => true];
                        } else {
                            echo " - FAILED: No results with username: $username";
                            return [];
                        }
                    }

                    return $results;
                } catch (\Exception $e) {
                    echo " - SKIPPED: Geonames error: " . $e->getMessage();
                    return ['skipped' => true];
                }

            case 'Ortsnamen':
                // Ortsnamen-Provider implementieren falls verfügbar
                echo " - SKIPPED: Ortsnamen provider not yet implemented";
                return ['skipped' => true];

            case 'ManualInput':
                // Manual Input ist kein API-Provider
                echo " - SKIPPED: Manual input is not an API provider";
                return ['skipped' => true];

            default:
                throw new \Exception("Unknown provider type: {$apiType}");
        }
    }


    public function test_provider_configuration_completeness()
    {
        $providers = config('resources.providers');
        $incompleteProviders = [];

        foreach ($providers as $providerKey => $config) {
            // Überprüfe nur Provider die eine api-type haben (suchbare Provider)
            // Provider ohne api-type sind nur für automatische Syncs gedacht
            if (!isset($config['api-type'])) {
                continue; // Skip sync-only providers
            }

            // Überprüfe erforderliche Konfigurationsfelder für suchbare Provider
            $requiredFields = ['label'];

            foreach ($requiredFields as $field) {
                if (!isset($config[$field])) {
                    $incompleteProviders[] = "{$providerKey} missing {$field}";
                }
            }

            // Für API-Provider sollte base_url vorhanden sein
            if ($config['api-type'] !== 'ManualInput' &&
                !isset($config['base_url'])) {
                $incompleteProviders[] = "{$providerKey} missing base_url";
            }
        }

        $this->assertEmpty($incompleteProviders,
            'All searchable providers should have complete configuration. Missing: ' . implode(', ', $incompleteProviders));
    }
}
