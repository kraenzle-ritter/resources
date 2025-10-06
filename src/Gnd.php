<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use KraenzleRitter\Resources\Helpers\UserAgent;
use KraenzleRitter\Resources\Traits\HttpClientTrait;

/**
 * GND queries
 * cf . https://de.wikipedia.org/wiki/Hilfe:GND
 * cf. https://lobid.org/gnd/api
 *
 * Gnd::search('string', $params) : object
 * params:
 *      - field => 'preferredName',
 *      - filter => ['type' => 'Person'] ✔
 *      - from => 2
 *      - size (integer, default 20) ✔
 *      - format (default and only: json) ✔
 *      - formatFields
 */
class Gnd
{
    use HttpClientTrait;

    public $client;

    public $filter_types = [
            'Person',
            'CorporateBody',
            'ConferenceOrEvent',
            'PlaceOrGeographicName',
            'Work',
            'PlaceOrGeographicName',
            'SubjectHeading',
            'Family'
        ];

    public function __construct()
    {
        $baseUrl = 'https://lobid.org/gnd/';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => config('resources.providers.gnd.timeout', 15), // Configurable timeout, default 15 seconds
            'connect_timeout' => config('resources.providers.gnd.connect_timeout', 5), // Connection timeout
            'headers'  => UserAgent::get(),
            'http_errors' => false // Don't throw exceptions on 4xx and 5xx responses
        ]);
    }

    public function search(string $search, $params = [])
    {
        $search = str_replace(['[', ']', '!', '(', ')', ':'], ' ', $search);
        $search = 'search?q=' . urlencode($search);

        $filters = $params['filters'] ?? [];
        $size = $params['limit'] ?? config('sources-components.gnd.limit') ?? 5;
        $endpoint = $search . $this->buildFilter($filters) . '&size=' . $size . '&format=json';

        $fallbackValue = (object) ['member' => [], 'totalItems' => 0];

        return $this->safeHttpGet($endpoint, 'GND API', $fallbackValue, function($content, $endpoint, $apiName, $fallbackValue) {
            $result = json_decode($content);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $fallbackValue;
            }

            if (isset($result->totalItems) && $result->totalItems > 0) {
                return $result;
            }

            return $fallbackValue;
        });
    }

    public function buildFilter($filters = []) : string
    {
        if (!$filters) {
            return '';
        }

        $filter = str_replace('=', ':', http_build_query($filters, null, ' AND '));

        return '&filter=' . $filter;
    }
}
