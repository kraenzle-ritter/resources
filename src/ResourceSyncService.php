<?php

namespace KraenzleRitter\Resources;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Exception\RequestException;
use KraenzleRitter\Resources\Helpers\UserAgent;

class ResourceSyncService
{
    protected $providers;
    protected $configProviders;
    protected $filter = [];


    public function __construct($filter = [])
    {
        $this->providers = config('resources.providers', []);
        $this->configProviders = $this->getWikidataArray($this->providers);
        $this->setUpProviders();
        $this->filter = $filter;

    }

    public function getWikidataArray(array $input): array
    {
        $output = [];
        foreach ($input as $key => $item) {
            if (isset($item['wikidata_property'])) {
                $output[$key] = $item['wikidata_property'];
            }
        }
        return $output;
    }


    /**
     * Sync resources from a specific provider for a given model
     *
     * @param Model $model The model to sync resources for
     * @param string $provider The provider to sync from (e.g., 'wikidata', 'gnd', 'wikipedia')
     * @return array Array of synced resources or empty array if none found
     */
    public function syncFromProvider(Model $model, string $provider): array
    {
        // Load existing resources
        $model->load('resources');

        // Find existing resource for the specified provider
        $existingResource = $model->resources->where('provider', $provider)->first();

        if (!$existingResource) {
            Log::warning("No existing resource found for provider: {$provider}", [
                'model_id' => $model->id,
                'model_type' => get_class($model)
            ]);
            return [];
        }

        try {
            // Fetch new resources from the provider
            $newResources = $this->fetchResourcesFromProvider($existingResource->provider_id, $provider, $existingResource->url);

            if (empty($newResources)) {
                Log::warning("No resources found for provider: {$provider} with ID: {$existingResource->provider_id}");
                return [];
            }

            $syncedResources = [];

            foreach ($newResources as $resourceData) {
                if ($filter && in_array($resourceData['provider'] ?? '', $this->filter, true)) {
                    Log::info("Skipping resource from provider due to filter list", [
                        'provider' => $resourceData['provider'] ?? '',
                        'model_id' => $model->id
                    ]);
                    continue;
                }
                try {
                    $resource = (new Resource())->updateOrCreateResource($model, $resourceData);
                    $syncedResources[] = $resource;
                } catch (\Exception $e) {
                    Log::error('Error updating/creating resource during sync', [
                        'provider' => $provider,
                        'resource_data' => $resourceData,
                        'model_id' => $model->id,
                        'exception' => $e->getMessage()
                    ]);
                }
            }

            Log::info("Successfully synced {count} resources from provider: {$provider}", [
                'count' => count($syncedResources),
                'model_id' => $model->id,
                'provider' => $provider
            ]);

            return $syncedResources;

        } catch (\Exception $e) {
            Log::error('Error syncing resources from provider', [
                'provider' => $provider,
                'model_id' => $model->id,
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Fetch resources from a specific provider
     *
     * @param string $providerId The ID from the provider
     * @param string $provider The provider name
     * @param string|null $url The URL of the resource (needed for metagrid)
     * @return array Array of resource data
     */
    protected function fetchResourcesFromProvider(string $providerId, string $provider, ?string $url = null): array
    {

        // Handle metagrid provider separately
        if ($provider === 'metagrid') {
            if (!$url) {
                Log::warning('Metagrid provider requires URL');
                return [];
            }
            return $this->fetchFromMetagrid($url);
        }

        // Handle other providers via Wikidata
        $wikidataId = $this->getWikidataId($providerId, $provider);

        if (!$wikidataId) {
            return [];
        }

        return $this->fetchFromWikidata($wikidataId);
    }

    /**
     * Get Wikidata ID from provider ID
     *
     * @param string $providerId The provider ID
     * @param string $provider The provider name
     * @return string|null The Wikidata ID or null if not found
     */
    protected function getWikidataId(string $providerId, string $provider): ?string
    {
        switch ($provider) {
            case 'wikipedia':
                return $this->getWikidataIdForWikipediaId($providerId);
            case 'gnd':
                return $this->getWikidataIdForGndId($providerId);
            case 'wikidata':
                return $providerId; // Already a Wikidata ID
            default:
                // Handle language-specific Wikipedia providers (e.g., wikipedia-de, wikipedia-en)
                if (strpos($provider, 'wikipedia-') === 0) {
                    return $this->getWikidataIdForWikipediaId($providerId, $provider);
                }

                Log::warning("Unknown provider for Wikidata ID conversion: {$provider}");
                return null;
        }
    }

    /**
     * Fetch resources from Wikidata
     *
     * @param string $wikidataId The Wikidata ID (e.g., Q42)
     * @return array Array of resource data
     */
    protected function fetchFromWikidata(string $wikidataId): array
    {
        $baseUrl = 'https://www.wikidata.org/w/api.php';

        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create HTTP client for Wikidata', ['exception' => $e->getMessage()]);
            return [];
        }

        $query = [
            'action' => 'wbgetentities',
            'format' => 'json',
            'ids' => $wikidataId,
            'languages' => app()->getLocale(),
            'props' => 'labels|descriptions|claims'
        ];

        try {
            $response = $client->get('', ['query' => $query]);
            $body = json_decode($response->getBody(), true);

            if (!isset($body['entities'][$wikidataId])) {
                Log::warning("No entity found for Wikidata ID: {$wikidataId}");
                return [];
            }

            $entity = $body['entities'][$wikidataId];
            $resources = [];

            // Add the main Wikidata resource
            $data = [
                'provider' => 'wikidata',
                'provider_id' => $wikidataId,
                'url' => 'https://www.wikidata.org/wiki/' . $wikidataId,
                'full_json' => json_encode($entity)
            ];
            $resources[] = $data;

            // Extract claims for other providers using the local configuration
            if (isset($entity['claims'])) {
                // Try new implementation first (using local config)
                foreach ($this->configProviders as $providerKey => $wikidataProperty) {
                    if (isset($entity['claims'][$wikidataProperty])) {
                        $claims = $entity['claims'][$wikidataProperty];
                        if (isset($claims[0]['mainsnak']['datavalue']['value'])) {
                            $providerValue = $claims[0]['mainsnak']['datavalue']['value'];

                            // Get the provider configuration
                            $providerConfig = config("resources.providers.{$providerKey}");

                            if ($providerConfig && isset($providerConfig['target_url'])) {
                                $resource = [
                                    'provider' => $providerKey,
                                    'provider_id' => $providerValue,
                                    'url' => str_replace('{provider_id}', $providerValue, $providerConfig['target_url'])
                                ];
                                $resources[] = $resource;
                            }
                        }
                    }
                }

                // Fallback: Use dynamic provider setup (old implementation)
                if (!empty($this->providers) && is_array($this->providers)) {
                    foreach ($this->providers as $provider) {
                        if (isset($provider['provider'])) {
                            $key = preg_replace('|.*(P\d+).*|', "$1", $provider['provider']);

                            if (isset($entity['claims'][$key])) {
                                $claims = $entity['claims'][$key];
                                if (isset($claims[0]['mainsnak']['datavalue']['value'])) {
                                    $providerValue = $claims[0]['mainsnak']['datavalue']['value'];
                                    $label = $provider['providerLabel'] ?? null;
                                    if ($label && isset($provider['url'])) {
                                        $providerSlug = Str::slug(array_search($key, $this->configProviders));
                                        if ($providerSlug) {
                                            $resource = [
                                                'provider' => $providerSlug,
                                                'provider_id' => $providerValue,
                                                'url' => str_replace('$1', $providerValue, $provider['url'])
                                            ];
                                            $resources[] = $resource;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $resources;

        } catch (RequestException $e) {
            Log::error('HTTP error fetching from Wikidata', [
                'wikidata_id' => $wikidataId,
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get Wikidata ID for a Wikipedia page ID
     *
     * @param string $wikipediaPageId The Wikipedia page ID
     * @param string|null $provider The provider name (e.g., 'wikipedia-de', 'wikipedia-en')
     * @return string|null The Wikidata ID or null if not found
     */
    protected function getWikidataIdForWikipediaId(string $wikipediaPageId, ?string $provider = null): ?string
    {
        // Extract language from provider if provided, otherwise use default language
        if ($provider && strpos($provider, 'wikipedia-') === 0) {
            $lang = substr($provider, strlen('wikipedia-'));
        } else {
            Log::warning('Provider for Wikipedia ID must specify language, e.g., wikipedia-en');
            return null;
        }

        $baseUrl = "https://{$lang}.wikipedia.org/w/api.php";

        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create HTTP client for Wikipedia', ['exception' => $e->getMessage()]);
            return null;
        }

        $query = [
            'action' => 'query',
            'format' => 'json',
            'pageids' => $wikipediaPageId,
            'prop' => 'pageprops'
        ];

        try {
            $response = $client->get('', ['query' => $query]);
            $body = json_decode($response->getBody(), true);

            return $body['query']['pages'][$wikipediaPageId]['pageprops']['wikibase_item'] ?? null;

        } catch (RequestException $e) {
            Log::error('HTTP error fetching Wikipedia page info', [
                'page_id' => $wikipediaPageId,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get Wikidata ID for a GND ID
     *
     * @param string $gndId The GND ID
     * @return string|null The Wikidata ID or null if not found
     */
    protected function getWikidataIdForGndId(string $gndId): ?string
    {
        $baseUrl = 'https://query.wikidata.org/sparql';

        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create HTTP client for SPARQL', ['exception' => $e->getMessage()]);
            return null;
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
                if (preg_match('/\/entity\/(Q\d+)$/', $itemUri, $matches)) {
                    return $matches[1];
                }
            }

            return null;

        } catch (RequestException $e) {
            Log::error('HTTP error fetching GND to Wikidata mapping', [
                'gnd_id' => $gndId,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set up provider information from Wikidata
     */
    protected function setUpProviders(): void
    {
        $baseUrl = 'https://query.wikidata.org/sparql';

        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create HTTP client for provider setup', ['exception' => $e->getMessage()]);
            $this->providers = [];
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
            $this->providers = $this->unique_multidim_array($providers, 'provider');

        } catch (RequestException $e) {
            Log::error('HTTP error setting up providers', ['exception' => $e->getMessage()]);
            $this->providers = [];
        }
    }

    /**
     * Remove duplicate entries from multidimensional array
     *
     * @param array $array The array to process
     * @param string $key The key to check for uniqueness
     * @return array The array with unique entries
     */
    protected function unique_multidim_array(array $array, string $key): array
    {
        $temp_array = [];
        $key_array = [];

        foreach($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[] = $val[$key];
                $temp_array[] = $val;
            }
        }

        return $temp_array;
    }

    /**
     * Fetch resources from Metagrid
     *
     * @param string $metagridUrl The Metagrid URL
     * @return array Array of resource data
     */
    protected function fetchFromMetagrid(string $metagridUrl): array
    {
        try {
            $client = new Client([
                'timeout' => 10,
                'headers' => UserAgent::get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create HTTP client for Metagrid', ['exception' => $e->getMessage()]);
            return [];
        }

        try {
            $response = $client->get($metagridUrl);
            $body = json_decode($response->getBody(), true);

            if (!isset($body['concordances'][0]['resources'])) {
                Log::warning("No concordances found in Metagrid response", ['url' => $metagridUrl]);
                return [];
            }

            $resources = [];
            foreach ($body['concordances'][0]['resources'] as $srcData) {
                $data = [
                    'provider' => $srcData['provider']['slug'] ?? 'unknown',
                    'url' => $srcData['link']['uri'] ?? '',
                ];

                if (isset($srcData['identifier'])) {
                    $data['provider_id'] = $srcData['identifier'];
                }

                if (!empty($data['provider']) && !empty($data['url'])) {
                    $resources[] = $data;
                }
            }

            Log::info("Successfully fetched {count} resources from Metagrid", [
                'count' => count($resources),
                'url' => $metagridUrl
            ]);

            return $resources;

        } catch (RequestException $e) {
            Log::error('HTTP error fetching from Metagrid', [
                'url' => $metagridUrl,
                'exception' => $e->getMessage()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Error parsing Metagrid response', [
                'url' => $metagridUrl,
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }
}
