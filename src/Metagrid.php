<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use KraenzleRitter\Resources\Helpers\UserAgent;
use KraenzleRitter\Resources\Traits\HttpClientTrait;

/**
 * Metagrid concordance search.
 *
 * (new Metagrid())->search('Karl Barth');
 */
class Metagrid
{
    use HttpClientTrait;

    public const DEFAULT_BASE_URL = 'https://api.metagrid.ch/';

    public $client;

    public $body;

    public function __construct(?ClientInterface $client = null)
    {
        if ($client) {
            $this->client = $client;

            return;
        }

        $this->client = new Client([
            'base_uri' => config('resources.providers.metagrid.base_url', self::DEFAULT_BASE_URL),
            'timeout' => config('resources.providers.metagrid.timeout', 15),
            'connect_timeout' => config('resources.providers.metagrid.connect_timeout', 5),
            'headers' => UserAgent::get(),
            'http_errors' => false,
        ]);
    }

    /**
     * @return array Concordance objects, empty on any failure or no match.
     *               Previously returned null for "no match", which forced every
     *               caller to handle two empty shapes.
     */
    public function search($search, $params = [])
    {
        if (! trim((string) $search)) {
            return [];
        }

        $limit = $params['limit']
            ?? config('resources.providers.metagrid.limit')
            ?? config('resources.limit')
            ?? 5;

        // https://api.metagrid.ch/search?group=1&query=cassirer&skip=0&take=10
        $endpoint = 'search?query=' . urlencode(str_replace(',', ' ', $search))
            . '&group=1&take=' . $limit . '&_format=json';

        return $this->safeHttpGet($endpoint, 'Metagrid API', [], function ($content, $endpoint, $apiName, $fallbackValue) {
            $body = json_decode($content);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_object($body)) {
                return $fallbackValue;
            }

            if (! isset($body->concordances) || ! is_array($body->concordances)) {
                return $fallbackValue;
            }

            if (isset($body->meta->total) && (int) $body->meta->total === 0) {
                return $fallbackValue;
            }

            return $body->concordances;
        });
    }

    // https://api.metagrid.ch/concordance/47451.json
    public function getConcordance($id)
    {
        $this->body = $this->safeHttpGet('concordance/' . $id . '.json', 'Metagrid API', null, function ($content) {
            return json_decode($content);
        });

        return $this;
    }
}
