<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use KraenzleRitter\Resources\Helpers\Params;
use KraenzleRitter\Resources\Helpers\UserAgent;
use KraenzleRitter\Resources\Traits\HttpClientTrait;

class Geonames
{
    use HttpClientTrait;

    public $client;

    public $base_uri;

    public $username;

    public $body;

    public $query_params = [
        'style' => 'FULL',          // Verbosity of returned xml document, default = MEDIUM
        'type' => 'JSON',           // the format type of the returned document, default = xml
        'isNameRequired' => 'true'  // At least one of the search term needs to be part of the place name
    ];

    public function __construct()
    {
        // https://www.geonames.org/export/geonames-search.html
        $this->username = config('resources.providers.geonames.user_name');

        $this->query_params['maxRows'] = config('resources.limit') ?? 5; // Default is 100, the maximal allowed value is 1000.
        $this->query_params['continentCode'] = config('resources.providers.geonames.continent_code');
        $this->query_params['countryBias'] = config('resources.providers.geonames.country_bias');
        $this->query_params = array_filter($this->query_params);

        $this->base_uri = 'http://api.geonames.org/';

        $this->client = new Client([
            'base_uri' => $this->base_uri,
            'timeout'  => config('resources.providers.geonames.timeout', 15), // Configurable timeout, default 15 seconds
            'connect_timeout' => config('resources.providers.geonames.connect_timeout', 5), // Connection timeout
            'headers'  => UserAgent::get(),
            'http_errors' => false // Don't throw exceptions on 4xx and 5xx responses
        ]);
    }

    /**
     * Sucht nach Orten in der Geonames-Datenbank.
     *
     * Verfügbare Parameter:
     * - maxRows: Maximale Anzahl der Ergebnisse (Default: 5, Max: 1000)
     * - continentCode: Beschränkt die Suche auf Toponyme des angegebenen Kontinents (z.B. 'EU')
     * - countryBias: Datensätze aus diesem Land werden zuerst aufgelistet (z.B. 'CH')
     *
     * @param string $string Der Suchbegriff
     * @param array $params Additional parameters for the API request
     * @return array Gefundene Orte
     */
    public function search($string, $params = [])
    {
        // Adopt the passed parameters or use the default values
        $this->query_params = $params ?: $this->query_params;

        // Ensure that the limit from the passed parameters is used
        if (isset($params['limit'])) {
            $this->query_params['maxRows'] = $params['limit'];
        }

        $this->query_params = array_merge(['q' => $string, 'username' => $this->username], $this->query_params);

        $query_string = Params::toQueryString($this->query_params);
        $endpoint = 'searchJSON?' . $query_string;

        return $this->safeHttpGet($endpoint, 'Geonames API', [], function($content, $endpoint, $apiName, $fallbackValue) {
            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $fallbackValue;
            }

            // Check for errors in the response, even if the status is 200
            if (isset($result['status']) && isset($result['status']['value']) && $result['status']['value'] > 0) {
                // Falls es ein Limit-Problem mit dem Demo-Account ist, geben wir einen hilfreichen Hinweis
                if (isset($result['status']['message']) && strpos($result['status']['message'], 'limit') !== false) {
                    \Illuminate\Support\Facades\Log::warning('Geonames API: Rate limit exceeded', [
                        'message' => $result['status']['message'],
                        'username' => $this->username
                    ]);
                }
                return $fallbackValue;
            }

            return $result['geonames'] ?? $fallbackValue;
        });
    }

    // http://api.geonames.org/get?geonameId=2658434&username=demo
    public function getPlaceByGeonameId(string $id): \SimpleXMLElement
    {
        $response = $this->client->get('get?geonameId=' . $id . '&username=' . $this->username);
        $xml = simplexml_load_string($response->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // this also works but has slightly other format therefore i dont change this (ak/2019-11-25)
        //$response2 = $this->client->get('getJSON?geonameId=' . $id . '&username=' . $this->username);
        //$body =  $response2->getBody();

        return $xml;
    }
}
