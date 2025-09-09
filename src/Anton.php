<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Psr7;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use KraenzleRitter\Resources\Helpers\Params;
use KraenzleRitter\Resources\Helpers\UserAgent;


class Anton
{
    public $body;
    public $url;
    private $token;
    private $providerKey;
    private $client;
    private $query_params = [];

    public function __construct(string $providerKey)
    {
        $this->providerKey = $providerKey;
        $this->url = config("resources.providers.{$providerKey}.base_url");

        $this->token = config("resources.providers.{$providerKey}.api_token");
    }

    public function search(
        string $search,
        array $params = [],
        string $endpoint = 'actors')
    {
        // Make sure URL ends with a slash
        $baseUrl = rtrim($this->url, '/') . '/';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => 10,
            'headers'  => UserAgent::get(),
        ]);

        $this->query_params = $params ?: $this->query_params;

        $limit = $params['limit'] ??
            $params['size'] ?? // Support both 'size' and 'limit' parameters
            config("resources.providers.{$this->providerKey}.limit") ??
            config('resources.limit') ??
            5;

        $this->query_params['perPage'] = $limit;
        $this->query_params['page'] = $params['page'] ?? 1;

        // Build proper query params - no ? in the key name
        $this->query_params = array_merge(['search' => $search], $this->query_params);

        // Add API token only if it exists
        if ($this->token) {
            $this->query_params['api_token'] = $this->token;
        }

        // Convert to query string
        $query_string = Params::toQueryString($this->query_params);

        // Construct the full URL
        $fullUrl = $endpoint . '?' . ltrim($query_string, '?');

        try {
            $response = $this->client->get($fullUrl);

            if ($response->getStatusCode() == 200) {
                $result = json_decode($response->getBody());
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->hasResponse()) {
            }
            $result = [];
        } catch (\Exception $e) {
            $result = [];
        }

        // Make sure we have a valid result
        if (isset($result) && isset($result->data) && is_array($result->data)) {
            return $result->data;
        }

        // Return empty array if no results or result is not as expected
        return [];
    }
}
