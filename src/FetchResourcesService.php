<?php

namespace KraenzleRitter\Resources;

use Illuminate\Support\Str;
use Wikidata\Wikidata as Wiki;
use GuzzleHttp\Client;
use Wikidata\SparqlClient;
use Illuminate\Support\Facades\App;

class FetchResourcesService
{
    public $defaultProviders = [
        'viaf' => 'P214',
        'gnd' => 'P227',
        'LCNAF' => 'P244' ,
        'Perlentaucher' => 'P866',
        'HLS' => 'P902',
        'Munzinger person' => 'P1284',
        'Encyclopaedia Britannica Online' => 'P1417',
        'Geonames' => 'P1566',
        'Stanford Encyclopedia of Philosophy' => 'P3123',
        'Catholic Encyclopedia' => 'P3241',
        'europeana' => 'P7704',
        'worldCat' => 'P7859',
        'Deutsch Biographie' => 'P7902',
        'Ökumenisches Heiligenlexikon' => 'P8080',
        'McClintock and Strong Biblical Cyclopedia' => 'P8636'
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

        $client = new Wiki();
        $result = $client->get($id, $this->lang);
        $resources = [];

        $data = [
            'provider' => 'wikidata',
            'provider_id' => $id,
            'url' => 'https://www.wikidata.org/wiki/' . $id,
            'full_json' => json_encode($result)
        ];
        $resources[] = $data;

        foreach ($this->providers as $provider) {
            // get the property key from the url (like 'P227' for gnd)
            $key = preg_replace('|.*(P\d+).*|', "$1", $provider['provider']);

            if (isset($result->properties->toArray()[$key])) {
                $id = $result->properties->toArray()[$key]->values->toArray()[0]->id;
                $label = $provider['providerLabel'];
                if (isset($label)) {
                    $resource['provider'] = Str::slug(array_search($key, $this->configProviders));
                    $resource['provider_id'] = $id;
                    $resource['url'] = str_replace('$1', $id, $provider['url']);
                    $resources[] = $resource;
                    unset($resource);
                }
            }
        }

        return $resources;
    }

    public function getWikidataIdForWikipediaId(string $wikipediaPageId)
    {
        $base_uri = "https://{$this->lang}.wikipedia.org/w/api.php";
        try {
            $client = new Client(['base_uri' => $base_uri]);
        } catch (Exception $e) {
            print($e->message);
        }

        $query = [];
        $query[] = 'action=query';
        $query[] = 'format=json';
        $query[] = 'pageids=' . $wikipediaPageId;
        $query[] = 'prop=pageprops';

        try {
            $response = $client->get('?' . join('&', $query));
            $body = json_decode($response->getBody());
        } catch (RequestException $e) {
            echo Psr7\str($e->getRequest());
            if ($e->hasResponse()) {
                echo Psr7\str($e->getResponse());
            }
        }

        return $body->query->pages->{$wikipediaPageId}->pageprops->wikibase_item;
    }

    public function getWikidataIdForGndId(string $gndId)
    {
        $client = new Wiki();
        $result = $client->searchBy('P227', $gndId);

        if (isset($result) && count($result) == 1) {
            $first = $result->toArray();
            return array_shift($first)->id;
        } else {
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
        $query = 'SELECT DISTINCT ?provider ?providerLabel ?url
                    WHERE {
                        VALUES ?provider { wd:'. join(' wd:', $this->configProviders) . ' } .
                        ?provider wdt:P1630 ?url.
                        SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
                    }
                    LIMIT ' . count($this->configProviders);

        $client = new SparqlClient();
        $providers = $client->execute($query);

        $providers = array_unique($providers, 3);
        // we only want one link for a provider
        // eg Deutsch Biographie offers two links
        $this->providers = static::unique_multidim_array($providers, 'provider');
    }
}
