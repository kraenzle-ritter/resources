<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Traits\ProviderComponentTrait;

class GndLwComponent extends Component
{
    use ProviderComponentTrait;
    public $search;

    public $queryOptions;

    public $model;

    public $resourceable_id;

    public $provider = 'gnd';

    public $showAll = false; // Flag for displaying all results

    public $saveMethod = 'updateOrCreateResource'; // Method name for saving resources

    public $removeMethod = 'removeResource'; // Method name for resource removal

    public $filter = []; // Filter for providers to exclude from sync

    protected $listeners = ['resourcesChanged' => 'render'];

    public function mount($model, string $search = '', array $params = [], $filter = [])
    {
        $this->model = $model;
        $this->filter = $filter;

        $this->search = trim($search) ?: '';

        $this->queryOptions = $params['queryOptions'] ?? ['limit' => 5];
    }

    /**
     * Handler für Änderungen an der Sucheingabe
     * Diese Methode wird von Livewire automatisch aufgerufen, wenn sich der Wert von $search ändert
     *
     * @param string $value Der neue Suchwert
     * @return void
     */
    public function updatedSearch($value)
    {
        $this->search = $value;
        // Der render() wird automatisch aufgerufen
    }

    /**
     * Search provider method for testing
     */
    public function searchProvider()
    {
        // This method is mainly for testing purposes
        // The actual search logic is in the render() method
        if (empty($this->search)) {
            $this->queryOptions = [];
        } else {
            $this->queryOptions = $this->queryOptions ?? ['limit' => 5];
        }
    }

    /**
     * Toggle show all results
     */

    /**
     * Create or update resource from array data
     */
    public function updateOrCreateResource(array $data)
    {
        $resourceData = [
            'provider' => $this->provider,
            'provider_id' => $data['provider_id'],
            'url' => config("resources.providers.gnd.target_url")
                ? str_replace('{provider_id}', $data['provider_id'], config("resources.providers.gnd.target_url"))
                : '',
            'full_json' => json_encode($data['additional_data'] ?? [])
        ];

        $resource = $this->model->{$this->saveMethod}($resourceData);
        $this->dispatch('resourcesChanged');

        return $resource;
    }

    public function saveResource($provider_id, $url, $full_json = null)
    {
        // Check if a target_url is defined in the configuration
        $targetUrlTemplate = config("resources.providers.gnd.target_url");

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
        $this->model->syncFromProvider('gnd', $this->filter);

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
        $client = new Gnd();

        if ($this->search) {
            $resources = $client->search($this->search, $this->queryOptions);
        }

        $view = view()->exists('vendor.kraenzle-ritter.livewire.gnd-lw-component')
              ? 'vendor.kraenzle-ritter.livewire.gnd-lw-component'
              : 'resources::livewire.gnd-lw-component';

        if (!isset($resources) or !isset($resources->member) or !count($resources->member)) {
            // Get base_url from config
            $base_url = config('resources.providers.gnd.base_url', 'https://lobid.org/gnd/');

            // Debug logging

            return view($view, [
                'results' => [],
                'base_url' => $base_url,
                'showAll' => $this->showAll
            ]);
        }

        // Verarbeite die Ergebnisse mit dem ProviderComponentTrait
        foreach ($resources->member as $key => $resource) {
            // Verarbeite biographische Informationen mit TextHelper
            if (isset($resource->biographicalOrHistoricalInformation)) {
                $bioInfo = $resource->biographicalOrHistoricalInformation;
                if (is_array($bioInfo) && count($bioInfo) > 0) {
                    $resource->processedDescription = $this->extractFirstSentence($bioInfo[0]);
                }
            }
        }

        // Get base_url from config
        $base_url = config('resources.providers.gnd.base_url', 'https://lobid.org/gnd/');

        // Debug logging

        return view($view, [
            'results' => $resources->member,
            'base_url' => $base_url,
            'showAll' => $this->showAll
        ]);
    }
}
