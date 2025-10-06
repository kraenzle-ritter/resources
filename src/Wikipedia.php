<?php

namespace KraenzleRitter\Resources;

use \GuzzleHttp\Client;
use KraenzleRitter\Resources\Helpers\UserAgent;

/**
 * Wikipedia queries
 *
 *  Use the Wikipedia API
 *  (new Wiki())->search('Karl Barth');
 *  (new Wiki())->getArticle('Karl Barth'); // you need the exact title
 *
 */
class Wikipedia
{
    public $client;

    /**
     * Get a Wikipedia article list
     * @param  string $searchstring
     * @param  array $params possible keys: limit, providerKey
     * @return array|null Array of objects with $entry->title, strip_tags($entry->snippet) or null on error
     */
    public function search($searchstring, $params)
    {
        $limit = $params['limit'] ?? 5;
        $providerKey = $params['providerKey'] ?? 'wikipedia-de';

        $baseUrl = config('resources.providers.' . $providerKey . '.base_url');
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => config('resources.providers.' . $providerKey . '.timeout', 15),
            'connect_timeout' => config('resources.providers.' . $providerKey . '.connect_timeout', 5),
            'headers' => UserAgent::get(),
            'http_errors' => false // Don't throw exceptions on 4xx and 5xx responses
        ]);

        $searchstring = trim(str_replace(' ', '_', $searchstring), '_');
        $query = [];
        $query[] = 'action=query';
        $query[] = 'format=json';
        $query[] = 'list=search';
        $query[] = 'srsearch=intitle:' . $searchstring;
        $query[] = 'srnamespace=0';
        $query[] = 'srlimit=' . $limit;

        try {
            $response = $this->client->get('?' . join('&', $query));
            $body = json_decode($response->getBody());

            if (isset($body->query->searchinfo->totalhits) && $body->query->searchinfo->totalhits > 0) {
                return $body->query->search;
            }
        } catch (\Exception $e) {
            // Log error but don't crash - return empty array
            error_log("Wikipedia API search error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Get an article extract from Wikipedia
     * @param  string $title Title of the article
     * @param  array $params Possible keys: providerKey
     * @return object|null   Object with ->title, ->extract or null on error
     */
    public function getArticle($title, $params = [])
    {
        $providerKey = $params['providerKey'] ?? 'wikipedia-de';

        $apiUrl = config('resources.providers.' . $providerKey . '.base_url');

        // Set User-Agent and timeout to comply with Wikipedia robot policy
        $this->client = new Client([
            'base_uri' => $apiUrl,
            'timeout' => config('resources.providers.' . $providerKey . '.timeout', 15),
            'connect_timeout' => config('resources.providers.' . $providerKey . '.connect_timeout', 5),
            'headers' => UserAgent::get(),
            'http_errors' => false // Don't throw exceptions on 4xx and 5xx responses
        ]);

        $title = trim(str_replace(' ', '_', $title), '_');
        $query = [];
        $query[] = 'action=query';
        $query[] = 'titles=' . $title;
        $query[] = 'format=json';
        $query[] = 'prop=extracts';
        $query[] = 'exintro';
        $query[] = 'explaintext';
        $query[] = 'redirects=1';
        $query[] = 'indexpageids';

        try {
            $response = $this->client->get('?' . join('&', $query));
            $body = json_decode($response->getBody());

            if (isset($body->query->pages)) {
                foreach ($body->query->pages as $article) {
                    // Ensure common properties are always available
                    if (!isset($article->pageprops)) {
                        $article->pageprops = new \stdClass();
                    }
                    if (!isset($article->extract)) {
                        $article->extract = '';
                    }
                    if (!isset($article->title)) {
                        $article->title = '';
                    }

                    return $article;
                }
            }
        } catch (\Exception $e) {
            // Log error but don't crash
            error_log("Wikipedia API article error: " . $e->getMessage());
        }

        // If no article was found or an error occurred
        return null;
    }
}
