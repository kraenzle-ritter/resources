<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
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

    public const DEFAULT_BASE_URL = 'https://lobid.org/gnd/';

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

    public function __construct(?ClientInterface $client = null)
    {
        if ($client) {
            $this->client = $client;

            return;
        }

        $this->client = new Client([
            'base_uri' => config('resources.providers.gnd.base_url', self::DEFAULT_BASE_URL),
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
        $size = $params['limit'] ?? config('resources.providers.gnd.limit') ?? config('resources.limit') ?? 5;
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

        $filter = str_replace('=', ':', http_build_query($filters, '', ' AND '));

        return '&filter=' . $filter;
    }
}
