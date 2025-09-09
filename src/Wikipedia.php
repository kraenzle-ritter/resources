<?php

namespace KraenzleRitter\Resources;

use \GuzzleHttp\Psr7;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\RequestException;
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
            'timeout' => 10,
            'headers' => UserAgent::get(),
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
        } catch (RequestException $e) {
            // Log error during API call
            error_log(Psr7\Message::toString($e->getRequest()));
            if ($e->hasResponse()) {
                error_log(Psr7\Message::toString($e->getResponse()));
            }
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
            'timeout' => 10,
            'headers' => UserAgent::get(),
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
        } catch (RequestException $e) {
            // Log error during API call
            error_log(Psr7\Message::toString($e->getRequest()));
            if ($e->hasResponse()) {
                error_log(Psr7\Message::toString($e->getResponse()));
            }
        }

        // If no article was found or an error occurred
        return null;
    }
}
