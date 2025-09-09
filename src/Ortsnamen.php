<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use KraenzleRitter\Resources\Helpers\Params;
use KraenzleRitter\Resources\Helpers\UserAgent;

class Ortsnamen
{
    public $body;

    public $url;

    public function __construct()
    {
        $baseUrl = 'https://search.ortsnamen.ch/de/api/';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 10,
            'headers' => UserAgent::get(),
        ]);
    }

    public function search(string $search, $params = [])
    {
        if (!$search) {
            return [];
        }

        $search = str_replace(',', ' ', $search);
        try {
            $response = $this->client->get('search?q=' . $search);
        } catch (\Exception $e) {
            return [];
        }

        $body = json_decode($response->getBody());

        // Limit anwenden, falls angegeben
        $limit = $params['limit'] ?? config('resources.limit') ?? 5;
        if ($limit && is_array($body->results)) {
            return array_slice($body->results, 0, $limit);
        }

        return $body->results;

    }

}
