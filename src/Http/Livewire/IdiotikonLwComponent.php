<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\Idiotikon;
use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Traits\ProviderComponentTrait;

class IdiotikonLwComponent extends Component
{
    use ProviderComponentTrait;
    public $search;

    public $queryOptions;

    public $model;

    public $endpoint;

    public $resourceable_id;

    public $provider = 'idiotikon';

    public $saveMethod = 'updateOrCreateResource'; // Method name for saving resources

    public $removeMethod = 'removeResource'; // Method name for resource removal

    protected $listeners = ['resourcesChanged' => 'render'];

    public function mount($model, string $search = '', array $params = [])
    {
        $this->model = $model;

        $this->search = trim($search) ?: '';

        $this->queryOptions = $params['queryOptions'] ?? ['limit' => 5];
    }

    public function saveResource($provider_id, $url, $full_json = null)
    {
        $full_json = preg_replace('/[\x00-\x1F]/','', $full_json);

        // Check if a target_url is defined in the configuration
        $targetUrlTemplate = config("components.providers.idiotikon.target_url");

        if ($targetUrlTemplate) {
            // Platzhalter im Template ersetzen
            $url = str_replace('{provider_id}', $provider_id, $targetUrlTemplate);
        }

        $data = [
            'provider' => 'idiotikon',
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
        $client = new Idiotikon();

        $resources = $client->search($this->search, $this->queryOptions);

        // Verarbeite die Ergebnisse mit dem ProviderComponentTrait
        if (!empty($resources)) {
            foreach ($resources as $key => $result) {
                // Verarbeite Beschreibungen, falls vorhanden
                if (!empty($result->description) && !empty($result->description[0])) {
                    $result->processedDescription = $this->extractFirstSentence($result->description[0]);
                } else {
                    $result->processedDescription = "ID: idiotikon-{$result->lemmaID}";
                }
            }
        }

        $view = view()->exists('vendor.kraenzle-ritter.livewire.idiotikon-lw-component')
              ? 'vendor.kraenzle-ritter.livewire.idiotikon-lw-component'
              : 'resources::livewire.idiotikon-lw-component';

        if (!isset($resources) or !count($resources)) {
            return view($view, [
                'results' => []
            ]);
        }

        return view($view, [
            'results' => $resources
        ]);
    }
}
