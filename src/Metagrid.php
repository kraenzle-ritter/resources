<?php

namespace KraenzleRitter\Resources;

use \GuzzleHttp\Client;
use KraenzleRitter\Resources\Helpers\UserAgent;

/**
 * Metagrid queries
 *
 *  use the Metagrid api
 *  (new Metagrid())->search('Karl Barth');
 */
class Metagrid
{
    public $client;

    public function __construct()
    {
        $baseUrl = 'https://api.metagrid.ch/';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 10,
            'headers' => UserAgent::get(),
        ]);
    }

    public function search($search, $params = [])
    {
        if (!$search) {
            return [];
        }

        $search = str_replace(',', ' ', $search);

        // Determine limit from parameters or configuration
        $limit = $params['limit'] ?? config('resources.limit') ?? 5;

        try {
            // https://api.metagrid.ch/search?group=1&query=cassirer&skip=0&take=10
            $response = $this->client->get('/search?query=' . $search . '&group=1&take=' . $limit . '&_format=json');
        } catch (\Exception $e) {
            return [];
        }

        $body = json_decode($response->getBody());

        if (!$body || $body->meta->total == 0) {
            return null;
        }

        return $body->concordances;
    }

    // https://api.metagrid.ch/concordance/47451.json
    public function getConcordance($id)
    {
        $response = $this->client->get('/concordance/' . $id . '.json');
        $this->body = json_decode($response->getBody());

        return $this;
    }

    private function composeName($resource)
    {
        if (!isset($resource->metadata)) {
            return '';
        }

        if (isset($resource->metadata->first_name) && isset($resource->metadata->last_name)) {
            $name = $resource->metadata->last_name . ', ' . $resource->metadata->first_name;
        } else {
            $name = $resource->metadata->name ?? $resource->metadata->last_name ?? null;
        }

        return $name;
    }

    private function composeDates($resource, $full = true)
    {
        $date = '';
        if (isset($resource->metadata->birth_date)) {
            $date = ' *' . substr($resource->metadata->birth_date, 0, 4);
        }

        if (isset($resource->metadata->death_date)) {
            $date .= ' †' . substr($resource->metadata->death_date, 0, 4);
        }
        if ($date) {
            return ' ('. $date .')';
        }
    }

}
