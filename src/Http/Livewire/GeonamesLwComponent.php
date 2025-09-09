<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;
use KraenzleRitter\Resources\Geonames;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Traits\ProviderComponentTrait;

class GeonamesLwComponent extends Component
{
    use ProviderComponentTrait;
    public $search;

    public $queryOptions;

    public $model;

    public $resourceable_id;

    public $provider = 'geonames';

    public $saveMethod = 'updateOrCreateResource'; // Method name for saving resources

    public $removeMethod = 'removeResource'; // Method name for resource removal

    protected $listeners = ['resourcesChanged' => 'render'];

    public function mount($model, string $search = '', array $params = [])
    {
        $this->model = $model;

        $this->search = trim($search) ?: 'Cassirer';

        $this->queryOptions = $params['queryOptions'] ?? ['limit' => 5];
    }

    public function saveResource($provider_id, $url, $full_json = null)
    {
        // Check if a target_url is defined in the configuration
        $targetUrlTemplate = config("components.providers.geonames.target_url");

        if ($targetUrlTemplate) {
            // Platzhalter im Template ersetzen
            $url = str_replace('{provider_id}', $provider_id, $targetUrlTemplate);
        }

        $data = [
            'provider' => $this->provider,
            'provider_id' => $provider_id,
            'url' => $url,
            'full_json' => json_decode($full_json)
        ];

        $resource = $this->model->{$this->saveMethod}($data);

        $this->dispatch('resourcesChanged');

        event(new ResourceSaved($resource, $this->model->id));
    }

    public function removeResource($url)
    {
        Resource::where([
            'url' => $url
        ])->delete();
        $this->dispatch('resourcesChanged');
    }

    public function render()
    {
        $client = new Geonames();

        // Ensure we have proper query options with a default limit
        $queryOptions = is_array($this->queryOptions) ? $this->queryOptions : [];
        if (empty($queryOptions['limit'])) {
            $queryOptions['limit'] = config('resources.limit', 5);
        }

        $resources = $client->search($this->search, $queryOptions);

        // Verarbeite die Ergebnisse mit dem ProviderComponentTrait
        if (!empty($resources)) {
            foreach ($resources as $key => $result) {
                // Sammle beschreibende Elemente
                $description = [];
                if (!empty($result->fclName)) {
                    $description[] = $result->fclName;
                }
                if (!empty($result->countryName)) {
                    $description[] = $result->countryName;
                }

                // Erstelle die kombinierte Beschreibung
                $combinedText = !empty($description) ? implode(', ', $description) : '';

                // Optional: Add additional descriptions if available
                if (!empty($result->summary)) {
                    $combinedText .= (!empty($combinedText) ? '. ' : '') . $result->summary;
                }

                // Speichere die verarbeitete Beschreibung im Ergebnisobjekt
                $resources[$key]->combinedDescription = $combinedText;
            }
        }

        // Get base_url from config
        $apiUrl = config('resources.providers.geonames.base_url', 'http://api.geonames.org/');

        // For Geonames, we need to adjust the URL for web links vs API
        $base_url = 'https://www.geonames.org/'; // Default frontend URL

        // If base_url configuration contains API URL, make sure we send the correct frontend URL to the view
        if (strpos($apiUrl, 'api.geonames.org') !== false) {
            $base_url = 'https://www.geonames.org/';
        }

        // Debug logging

        $view = view()->exists('vendor.kraenzle-ritter.livewire.geonames-lw-component')
              ? 'vendor.kraenzle-ritter.livewire.geonames-lw-component'
              : 'resources::livewire.geonames-lw-component';

        if (!isset($resources) or !count($resources)) {
            return view($view, [
                'results' => [],
                'base_url' => $base_url
            ]);
        }

        return view($view, [
            'results' => $resources,
            'base_url' => $base_url
        ]);
    }
}
