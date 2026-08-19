<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\IdRef;
use KraenzleRitter\Resources\Traits\ProviderComponentTrait;
use Livewire\Component;

class IdRefLwComponent extends Component
{
    use ProviderComponentTrait;

    public $search;

    public $queryOptions;

    public $model;

    public $provider = 'idref';

    public $endpoint;

    public $showAll = false;

    public $saveMethod = 'updateOrCreateResource';

    public $removeMethod = 'removeResource';

    public $filter = [];

    protected $listeners = ['resourcesChanged' => 'render'];

    public function mount($model, string $search = '', array $params = [], $filter = [], ?string $endpoint = null)
    {
        $this->model = $model;
        $this->filter = $filter;
        $this->endpoint = $endpoint ?? ($params['endpoint'] ?? null);
        $this->search = trim($search) ?: '';

        $this->queryOptions = $params['queryOptions'] ?? ['limit' => config('resources.limit', 5)];
    }

    public function updatedSearch($value)
    {
        $this->search = $value;
    }

    public function saveResource($provider_id, $url, $full_json = null)
    {
        $targetUrlTemplate = config('resources.providers.idref.target_url');

        if ($targetUrlTemplate) {
            $url = str_replace('{provider_id}', $provider_id, $targetUrlTemplate);
        }

        $resource = $this->model->{$this->saveMethod}([
            'provider' => $this->provider,
            'provider_id' => $provider_id,
            'url' => $url,
            'full_json' => json_decode((string) $full_json, true),
        ]);

        $this->dispatch('resourcesChanged');
        event(new ResourceSaved($resource, $this->model->id));
    }

    public function removeResource($url)
    {
        // Scoped to the mounted model - see ProviderComponentTrait.
        return $this->removeResourceByUrl($url);
    }

    public function render()
    {
        $results = [];

        if ($this->search) {
            $options = $this->queryOptions ?: [];

            // The endpoint decides which IdRef record types are searched, so a
            // place picker does not return people.
            if ($this->endpoint) {
                $options['endpoint'] = $this->endpoint;
            }

            $results = app(IdRef::class)->search($this->search, $options);
        }

        $view = view()->exists('vendor.kraenzle-ritter.livewire.idref-lw-component')
            ? 'vendor.kraenzle-ritter.livewire.idref-lw-component'
            : 'resources::livewire.idref-lw-component';

        return view($view, [
            'results' => $results,
            'showAll' => $this->showAll,
        ]);
    }
}
