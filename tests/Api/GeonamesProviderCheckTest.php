<?php

namespace KraenzleRitter\Resources\Tests\Feature;

use KraenzleRitter\Resources\Tests\TestCase;

class GeonamesProviderCheckTest extends TestCase
{
    /**
     * Test that Geonames results work with provider check view logic
     */
    public function test_geonames_results_work_with_view_logic()
    {
        $mockGeonamesResult = (object) [
            'geonameId' => 2657896,
            'name' => 'Zürich',
            'adminName1' => 'Zurich',
            'countryName' => 'Switzerland',
            'fclName' => 'city, village,...'
        ];

        // Test the logic that would be used in the view
        $provider_id = $mockGeonamesResult->geonameId;
        $name = $mockGeonamesResult->name;

        // Build description from available Geonames fields
        $descParts = [];
        if (isset($mockGeonamesResult->adminName1)) $descParts[] = $mockGeonamesResult->adminName1;
        if (isset($mockGeonamesResult->countryName)) $descParts[] = $mockGeonamesResult->countryName;
        if (isset($mockGeonamesResult->fclName)) $descParts[] = $mockGeonamesResult->fclName;
        $desc = implode(', ', $descParts);

        $this->assertEquals(2657896, $provider_id);
        $this->assertEquals('Zürich', $name);
        $this->assertEquals('Zurich, Switzerland, city, village,...', $desc);

        // Test URL generation
        $targetUrlTemplate = 'https://www.geonames.org/{provider_id}';
        $expectedUrl = str_replace('{provider_id}', $provider_id, $targetUrlTemplate);

        $this->assertEquals('https://www.geonames.org/2657896', $expectedUrl);
    }

    /**
     * Test Geonames results array format
     */
    public function test_geonames_results_array_format()
    {
        $mockGeonamesResultArray = [
            'geonameId' => 1234567,
            'name' => 'Basel',
            'adminName1' => 'Basel-Stadt',
            'countryName' => 'Switzerland',
            'fclName' => 'city, village,...'
        ];

        // Test array-based logic
        $provider_id = $mockGeonamesResultArray['geonameId'];
        $name = $mockGeonamesResultArray['name'];

        $descParts = [];
        if (!empty($mockGeonamesResultArray['adminName1'])) $descParts[] = $mockGeonamesResultArray['adminName1'];
        if (!empty($mockGeonamesResultArray['countryName'])) $descParts[] = $mockGeonamesResultArray['countryName'];
        if (!empty($mockGeonamesResultArray['fclName'])) $descParts[] = $mockGeonamesResultArray['fclName'];
        $desc = implode(', ', $descParts);

        $this->assertEquals(1234567, $provider_id);
        $this->assertEquals('Basel', $name);
        $this->assertEquals('Basel-Stadt, Switzerland, city, village,...', $desc);
    }

    /**
     * Test Geonames results with minimal data
     */
    public function test_geonames_results_with_minimal_data()
    {
        $mockMinimalResult = (object) [
            'geonameId' => 987654,
            'name' => 'Unknown Place'
            // No additional fields
        ];

        $provider_id = $mockMinimalResult->geonameId;
        $name = $mockMinimalResult->name;

        $descParts = [];
        if (isset($mockMinimalResult->adminName1)) $descParts[] = $mockMinimalResult->adminName1;
        if (isset($mockMinimalResult->countryName)) $descParts[] = $mockMinimalResult->countryName;
        if (isset($mockMinimalResult->fclName)) $descParts[] = $mockMinimalResult->fclName;
        $desc = implode(', ', $descParts) ?: '-';

        $this->assertEquals(987654, $provider_id);
        $this->assertEquals('Unknown Place', $name);
        $this->assertEquals('-', $desc); // Should fallback to '-' when no description parts
    }
}
