<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;
use KraenzleRitter\Resources\Wikidata;
use KraenzleRitter\Resources\Events\ResourceSaved;

class WikidataLwComponent extends Component
{
    public $search;

    public $queryOptions;

    public $model;

    public $resourceable_id;

    public $provider = 'wikidata';

    public $showAll = false; // Flag for displaying all results

    public $saveMethod = 'updateOrCreateResource'; // Method name for saving resources

    public $removeMethod = 'removeResource'; // Method name for resource removal

    public $filter = []; // Filter for providers to exclude from sync

    protected $listeners = ['resourcesChanged' => 'render'];

    public function mount ($model, string $search = '', array $params = [], $filter = [])
    {
        $this->model = $model;
        $this->filter = $filter;

        $this->search = trim($search) ?: '';

        $this->queryOptions = $params['queryOptions'] ?? ['locale' => 'de', 'limit' => 5];
    }

    /**
     * Toggle show all results
     */
    public function toggleShowAll()
    {
        $this->showAll = !$this->showAll;
    }

    public function saveResource($provider_id, $url, $full_json = null)
    {
        // Check if a target_url is defined in the configuration
        $targetUrlTemplate = config("components.providers.wikidata.target_url");

        if ($targetUrlTemplate) {
            // Platzhalter im Template ersetzen
            $url = str_replace('{provider_id}', $provider_id, $targetUrlTemplate);
        }

        $data = [
            'provider' => $this->provider,
            'provider_id' => $provider_id,
            'url' => $url,
            'full_json' => json_encode($full_json, JSON_UNESCAPED_UNICODE)
        ];
        $resource = $this->model->{$this->saveMethod}($data);
        $this->model->syncFromProvider('wikidata', $this->filter);

        $this->dispatch('resourcesChanged');
        event(new ResourceSaved($resource, $this->model->id));
    }

    public function removeResource($url)
    {
        \KraenzleRitter\Resources\Resource::where([
            'url' => $url
        ])->delete();
        $this->dispatch('resourcesChanged');
    }

    public function render()
    {
        $client = new Wikidata();

        $resources = $client->search($this->search, $this->queryOptions);

        // Get base_url from config
        $base_url = config('resources.providers.wikidata.base_url', 'https://www.wikidata.org/w/api.php');

        // For Wikidata, the web URL is different from the API URL
        if (strpos($base_url, '/w/api.php') !== false) {
            $base_url = str_replace('/w/api.php', '/wiki/', $base_url);
        }

        // Debug logging

        $view = view()->exists('vendor.kraenzle-ritter.livewire.wikidata-lw-component')
              ? 'vendor.kraenzle-ritter.livewire.wikidata-lw-component'
              : 'resources::livewire.wikidata-lw-component';

        return view($view, [
            'results' => $resources ?: null,
            'base_url' => $base_url,
            'showAll' => $this->showAll
        ]);
    }

}
