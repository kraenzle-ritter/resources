<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;
use KraenzleRitter\Resources\Metagrid;
use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Traits\ProviderComponentTrait;

/**
 * https://source.dodis.ch/metagrid-go/metagrid-go/-/wikis/Breaking-changes
 */

class MetagridLwComponent extends Component
{
    use ProviderComponentTrait;
    public $search;

    public $queryOptions;

    public $model;

    public $resourceable_id;

    public $provider = 'metagrid';

    public $saveMethod = 'updateOrCreateResource'; // Method name for saving resources (id, url, full_json)

    public $removeMethod = 'removeResource'; // Method name for resource removal

    protected $listeners = ['resourcesChanged' => 'render'];

    public function mount ($model, string $search = '', array $params = [])
    {
        $this->model = $model;

        $locale = $params['locale'] ?? 'de';

        $this->search = trim($search) ?: '';

        $this->queryOptions = $params['queryOptions'] ?? ['locale' => $locale, 'limit' => 5];
    }

    public function saveResource($provider_id, $url, $full_json = null)
    {
        // Check if a target_url is defined in the configuration
        $targetUrlTemplate = config("components.providers.metagrid.target_url");

        if ($targetUrlTemplate) {
            // Platzhalter im Template ersetzen
            $url = str_replace('{provider_id}', $provider_id, $targetUrlTemplate);
        }

        $data = [
            'provider' => $this->provider,
            'provider_id' => $provider_id,
            'url' => $url
        ];
        $resource = $this->model->{$this->saveMethod}($data);

        $full_json = json_decode($full_json);
        $data = null;
        if (isset($full_json->resources)) {
            foreach ($full_json->resources as $srcData) {
                $data = [
                    'provider' => $srcData->provider->slug,
                    'url' => $srcData->link->uri,
                ];
                if (isset($srcData->identifier)) {
                    $data['provider_id'] = $srcData->identifier ?? '';
                }

                $this->model->{$this->saveMethod}($data);
            }
        }
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
        $client = new Metagrid();

        $resources = $client->search($this->search, $this->queryOptions);

        // Verarbeite die Ergebnisse mit dem ProviderComponentTrait
        if (!empty($resources)) {
            foreach ($resources as $key => $result) {
                // Formatiere die Provider-Information als Beschreibung
                if (!empty($result->provider)) {
                    $result->processedDescription = "Quelle: " . $result->provider;
                } else {
                    $result->processedDescription = '';
                }
            }
        }

        $view = view()->exists('vendor.kraenzle-ritter.livewire.metagrid-lw-component')
              ? 'vendor.kraenzle-ritter.livewire.metagrid-lw-component'
              : 'resources::livewire.metagrid-lw-component';

        return view($view, [
            'results' => $resources
        ]);
    }

}
