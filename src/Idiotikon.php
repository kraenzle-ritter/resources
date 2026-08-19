<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use KraenzleRitter\Resources\Helpers\UserAgent;
use KraenzleRitter\Resources\Traits\HttpClientTrait;

/**
 * Idiotikon (Schweizerisches Idiotikon) lemma search.
 *
 * (new Idiotikon())->search('Allmend');
 */
class Idiotikon
{
    use HttpClientTrait;

    public const DEFAULT_BASE_URL = 'https://digital.idiotikon.ch/api/';

    public $body;

    public $url;

    public $client;

    public function __construct(?ClientInterface $client = null)
    {
        if ($client) {
            $this->client = $client;

            return;
        }

        $this->client = new Client([
            'base_uri' => config('resources.providers.idiotikon.base_url', self::DEFAULT_BASE_URL),
            'timeout' => config('resources.providers.idiotikon.timeout', 15),
            'connect_timeout' => config('resources.providers.idiotikon.connect_timeout', 5),
            'headers' => UserAgent::get(),
            'http_errors' => false,
        ]);
    }

    /**
     * @return array Lemma objects, empty on any failure.
     */
    public function search(string $search, $params = [])
    {
        if (! trim($search)) {
            return [];
        }

        $limit = $params['limit']
            ?? config('resources.providers.idiotikon.limit')
            ?? config('resources.limit')
            ?? 5;

        $endpoint = 'lemmata?query=' . urlencode(str_replace(',', ' ', $search));

        return $this->safeHttpGet($endpoint, 'Idiotikon API', [], function ($content, $endpoint, $apiName, $fallbackValue) use ($limit) {
            $body = json_decode($content);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_object($body) || ! isset($body->results) || ! is_array($body->results)) {
                return $fallbackValue;
            }

            return $limit ? array_slice($body->results, 0, $limit) : $body->results;
        });
    }
}
