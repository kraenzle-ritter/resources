<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use KraenzleRitter\Resources\Helpers\UserAgent;

/**
 * Wikidata queries
 * Direct implementation using GuzzleHttp instead of external dependency
 */
class Wikidata
{
    /**
     * GuzzleHttp client for API requests
     */
    public $client;

    /**
     * Initialize the client with the configured base URL
     */
    public function __construct()
    {
        $baseUrl = config('resources.providers.wikidata.base_url', 'https://www.wikidata.org/w/api.php');
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 10,
            'headers' => UserAgent::get(),
        ]);
    }

    /**
     * Search for entities in Wikidata
     * @param  string $search The search term
     * @param  array  $params Options: locale (language code), limit (max results)
     * @return array          Array of search results or empty array if none found
     */
    public function search(string $search, $params = [])
    {
        if (empty($search)) {
            return [];
        }

        $lang = $params['locale'] ?? config('resources.providers.wikidata.locale') ?? 'de';
        $limit = $params['limit'] ?? config('resources.providers.wikidata.limit') ?? 5;

        // Try first search
        $results = $this->performSearch($search, $lang, $limit);

        if (!empty($results)) {
            return $results;
        }

        // If no results and search contains comma, try reversing names (lastname, firstname -> firstname lastname)
        if (str_contains($search, ',')) {
            $array = explode(',', $search);
            $array = array_reverse($array);
            $search = join(' ', $array);
            $results = $this->performSearch($search, $lang, $limit);
        }

        return !empty($results) ? $results : [];
    }

    /**
     * Perform the actual search request to Wikidata API
     * @param  string $search The search term
     * @param  string $lang   Language code
     * @param  int    $limit  Maximum number of results
     * @return array          Array of search results
     */
    protected function performSearch(string $search, string $lang, int $limit)
    {
        try {
            // Build the API request parameters
            $params = [
                'action' => 'wbsearchentities',
                'format' => 'json',
                'search' => $search,
                'language' => $lang,
                'uselang' => $lang,
                'type' => 'item',
                'limit' => $limit,
                'origin' => '*' // Required for CORS when used in browser
            ];

            // Perform the request
            $response = $this->client->get('', [
                'query' => $params
            ]);

            $data = json_decode($response->getBody(), true);

            // If successful, process and return the results
            if (isset($data['search']) && is_array($data['search'])) {
                return $this->processSearchResults($data['search'], $lang);
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Process and transform the raw API response into usable objects
     * @param  array  $results Raw search results from API
     * @param  string $lang    Language code used for the search
     * @return array           Processed results
     */
    protected function processSearchResults(array $results, string $lang)
    {
        $processed = [];

        foreach ($results as $item) {
            if (!isset($item['id'])) continue;

            $processed[] = (object) [
                'id' => $item['id'],
                'url' => "https://www.wikidata.org/wiki/{$item['id']}",
                'label' => $item['label'] ?? $item['id'], // Use 'label' for consistency with other providers
                'title' => $item['label'] ?? $item['id'], // Keep 'title' for backward compatibility
                'description' => $item['description'] ?? '',
                'lang' => $lang,
                'fullJson' => json_encode($item)
            ];
        }

        return $processed;
    }
}
