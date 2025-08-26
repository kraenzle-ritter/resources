<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use KraenzleRitter\Resources\Helpers\UserAgent;

class FetchResourcesService
{
    public $defaultProviders = [
        'viaf' => 'P214',
        'gnd' => 'P227',
        'LCNAF' => 'P244' , //Library of Congress authority
        'BNF'  => 'P268', // Bibliothèque nationale de France
        'SBN' => 'P396', // National Library Service (SBN) of Italy
        'Perlentaucher' => 'P866',
        'hls-dhs-dss' => 'P902',
        'Munzinger person' => 'P1284',
        'Encyclopaedia Britannica Online' => 'P1417',
        'Geonames' => 'P1566',
        'Stanford Encyclopedia of Philosophy' => 'P3123',
        'Catholic Encyclopedia' => 'P3241',
        'europeana' => 'P7704',
        'worldCat' => 'P7859',
        'Deutsche Biographie' => 'P7902',
        'Ökumenisches Heiligenlexikon' => 'P8080',
        'McClintock and Strong Biblical Cyclopedia' => 'P8636',
        'Kalliope-Verbund' => 'P9964',
        'DDB' => 'P13049',
    ];

    public function __construct($provider)
    {
        $this->configProviders = config('resources.providers') ?? $this->defaultProviders;
        $this->setUpProviders();
        $this->lang = config('resources.preferred_locale') ?? App::getLocale();
        $this->provider = $provider;
    }

    // id is an id from a provider, like wikidata (eg Q42)
    public function run($id)
    {
        if ($this->provider == 'wikipedia') {
            $id = $this->getWikidataIdForWikipediaId($id);
        }
        if ($this->provider == 'gnd') {
            $id = $this->getWikidataIdForGndId($id);
        }

        if ($id === 0) {
            return false;
        }

        // id is now a wikidata id
        $baseUrl = 'https://www.wikidata.org/w/api.php';
        
        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            print($e->getMessage());
            return false;
        }

        $query = [
            'action' => 'wbgetentities',
            'format' => 'json',
            'ids' => $id,
            'languages' => $this->lang,
            'props' => 'labels|descriptions|claims'
        ];

        try {
            $response = $client->get('', ['query' => $query]);
            $body = json_decode($response->getBody(), true);
            
            if (!isset($body['entities'][$id])) {
                return false;
            }
            
            $entity = $body['entities'][$id];
            $resources = [];

            $data = [
                'provider' => 'wikidata',
                'provider_id' => $id,
                'url' => 'https://www.wikidata.org/wiki/' . $id,
                'full_json' => json_encode($entity)
            ];
            $resources[] = $data;

            // Extract claims for other providers
            if (isset($entity['claims'])) {
                foreach ($this->providers as $provider) {
                    // get the property key from the url (like 'P227' for gnd)
                    $key = preg_replace('|.*(P\d+).*|', "$1", $provider['provider']);

                    if (isset($entity['claims'][$key])) {
                        $claims = $entity['claims'][$key];
                        if (isset($claims[0]['mainsnak']['datavalue']['value'])) {
                            $providerValue = $claims[0]['mainsnak']['datavalue']['value'];
                            $label = $provider['providerLabel'];
                            if (isset($label)) {
                                $resource = [];
                                $resource['provider'] = Str::slug(array_search($key, $this->configProviders));
                                $resource['provider_id'] = $providerValue;
                                $resource['url'] = str_replace('$1', $providerValue, $provider['url']);
                                $resources[] = $resource;
                            }
                        }
                    }
                }
            }

            return $resources;
        } catch (RequestException $e) {
            echo Message::toString($e->getRequest());
            if ($e->hasResponse()) {
                echo Message::toString($e->getResponse());
            }
            return false;
        }
    }

    public function getWikidataIdForWikipediaId(string $wikipediaPageId)
    {
        $baseUrl = "https://{$this->lang}.wikipedia.org/w/api.php";
        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            print($e->getMessage());
            return null;
        }

        $query = [];
        $query[] = 'action=query';
        $query[] = 'format=json';
        $query[] = 'pageids=' . $wikipediaPageId;
        $query[] = 'prop=pageprops';

        try {
            $response = $client->get('?' . join('&', $query));
            $body = json_decode($response->getBody());
            return $body->query->pages->{$wikipediaPageId}->pageprops->wikibase_item ?? null;
        } catch (RequestException $e) {
            echo Message::toString($e->getRequest());
            if ($e->hasResponse()) {
                echo Message::toString($e->getResponse());
            }
            return null;
        }
    }

    public function getWikidataIdForGndId(string $gndId)
    {
        $baseUrl = 'https://query.wikidata.org/sparql';
        
        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            print($e->getMessage());
            return 0;
        }

        $sparqlQuery = 'SELECT ?item WHERE { ?item wdt:P227 "' . $gndId . '" }';
        
        $query = [
            'query' => $sparqlQuery,
            'format' => 'json'
        ];

        try {
            $response = $client->get('', ['query' => $query]);
            $body = json_decode($response->getBody(), true);
            
            if (isset($body['results']['bindings']) && count($body['results']['bindings']) === 1) {
                $itemUri = $body['results']['bindings'][0]['item']['value'];
                // Extract Q-ID from URI like https://www.wikidata.org/entity/Q42
                if (preg_match('/\/entity\/(Q\d+)$/', $itemUri, $matches)) {
                    return $matches[1];
                }
            }
            
            return 0;
        } catch (RequestException $e) {
            echo Message::toString($e->getRequest());
            if ($e->hasResponse()) {
                echo Message::toString($e->getResponse());
            }
            return 0;
        }
    }

    public static function unique_multidim_array($array, $key) {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }
        return $temp_array;
    }

    // Get the url pattern for the providers
    protected function setUpProviders()
    {
        $baseUrl = 'https://query.wikidata.org/sparql';
        
        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            print($e->getMessage());
            return;
        }

        $sparqlQuery = 'SELECT DISTINCT ?provider ?providerLabel ?url
                    WHERE {
                        VALUES ?provider { wd:'. join(' wd:', $this->configProviders) . ' } .
                        ?provider wdt:P1630 ?url.
                        SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
                    }
                    LIMIT ' . count($this->configProviders);

        $query = [
            'query' => $sparqlQuery,
            'format' => 'json'
        ];

        try {
            $response = $client->get('', ['query' => $query]);
            $body = json_decode($response->getBody(), true);
            
            $providers = [];
            if (isset($body['results']['bindings'])) {
                foreach ($body['results']['bindings'] as $binding) {
                    $providers[] = [
                        'provider' => $binding['provider']['value'],
                        'providerLabel' => $binding['providerLabel']['value'],
                        'url' => $binding['url']['value']
                    ];
                }
            }

            $providers = array_unique($providers, SORT_REGULAR);
            // we only want one link for a provider
            // eg Deutsch Biographie offers two links
            $this->providers = static::unique_multidim_array($providers, 'provider');
        } catch (RequestException $e) {
            echo Message::toString($e->getRequest());
            if ($e->hasResponse()) {
                echo Message::toString($e->getResponse());
            }
            $this->providers = [];
        }
    }
}
