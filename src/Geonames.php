<?php

namespace KraenzleRitter\Resources;

use Illuminate\Support\Facades\Http;
use KraenzleRitter\Resources\Helpers\Params;
use KraenzleRitter\Resources\Helpers\UserAgent;

class Geonames
{
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
        $search = 'searchJSON?' . $query_string;

        $response = HTTP::withHeaders(UserAgent::get())->get($this->base_uri.$search);

        if ($response->serverError()) {
            return [];
        }

        if ($response->clientError()) {
            return [];
        }

        if ($response->getStatusCode() == 200) {
            $result = json_decode($response->getBody());

            // Check for errors in the response, even if the status is 200
            if (isset($result->status) && isset($result->status->value) && $result->status->value > 0) {

                // Falls es ein Limit-Problem mit dem Demo-Account ist, geben wir einen hilfreichen Hinweis
                if (isset($result->status->message) && strpos($result->status->message, 'limit') !== false) {
                }

                return [];
            }

            return $result->geonames ?? [];
        }

        return [];
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
