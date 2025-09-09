<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;

class ProviderSelect extends Component
{
    public $model;
    public $providers_all;
    public $providers;
    public $endpoint;
    public ?string $providerKey = null;
    public ?string $componentToRender = null;
    public array $componentParams = [];
    protected $listeners = ['resourcesChanged' => 'hydrate'];

    public function mount($model, array $providers, string $endpoint = null)
    {
        $this->model = $model;
        $this->endpoint = $endpoint;

        $this->providers_all = array_map('strtolower', $providers);
        $this->filterAvailableProviders();

        $this->updateActiveProvider($this->providers[0] ?? null);
    }

    public function setProvider(string $providerKey)
    {
        $this->updateActiveProvider($providerKey);
    }

    private function updateActiveProvider(?string $providerKey)
    {
        if (!$providerKey) {
            $this->providerKey = null;
            $this->componentToRender = null;
            return;
        }

        // Debug: Was ist der ursprüngliche providerKey?

        // Legacy compatibility: 'wikipedia' => 'wikipedia-de'
        if ($providerKey === 'wikipedia') {
            $providerKey = 'wikipedia-de';
        }

        $this->providerKey = $providerKey;

        // Debug: Was ist der endgültige providerKey?

        $apiType = config('resources.providers.' . $providerKey . '.api-type');

        if (!$apiType) {
            $this->componentToRender = null;
            return;
        }

        // Derives the component name from the api-type (e.g. 'Gnd' -> 'gnd-lw-component')
        $this->componentToRender = strtolower($apiType) . '-lw-component';
        if ($apiType === 'ManualInput') {
            $this->componentToRender = 'manual-input-lw-component';
        }

        // Grundlegende Suchparameter
        $search = $this->model->resource_search ?? $this->model->name;

        // Spezielle Behandlung je nach Komponententyp
        if ($apiType === 'Wikipedia') {
            // Für Wikipedia-Komponenten: mount($model, string $search = '', string $providerKey = 'wikipedia-de')
            $this->componentParams = [
                'model' => $this->model,
                'search' => $search,
                'providerKey' => $providerKey,
            ];
        } else if ($apiType === 'Anton') {
            // Anton-Komponenten benötigen den zusätzlichen 'endpoint'-Parameter
            // mount($model, string $search = '', string $providerKey, string $endpoint, array $params = [])
            $this->componentParams = [
                'model' => $this->model,
                'search' => $search,
                'providerKey' => $providerKey,
                'endpoint' => $this->endpoint
            ];
        } else {
            // Standardparameter für andere Komponenten
            // Die meisten anderen Komponenten verwenden noch: mount($model, string $search = '', array $params = [])
            $this->componentParams = [
                'model' => $this->model,
                'search' => $search,
                'params' => ['providerKey' => $providerKey]
            ];
        }

        // Debug-Ausgabe für die vorbereiteten Parameter
    }

    public function hydrate()
    {
        $this->filterAvailableProviders();
        // Ensures the provider does not disappear if it was the last one
        if (!in_array($this->providerKey, $this->providers) && !empty($this->providers)) {
            $this->updateActiveProvider($this->providers[0]);
        } elseif (empty($this->providers) && count($this->providers_all) > count($this->model->resources)) {
            // Fallback if the last provider was linked
            $this->updateActiveProvider(null);
        }
    }

    private function filterAvailableProviders()
    {
        $this->model->load('resources');
        $linked_providers = $this->model->resources->pluck('provider')->toArray();
        $this->providers = array_values(array_diff($this->providers_all, $linked_providers));
    }

    public function render()
    {
        $view = view()->exists('vendor.kraenzle-ritter.livewire.provider-select')
            ? 'vendor.kraenzle-ritter.livewire.provider-select'
            : 'resources::livewire.provider-select';

        return view($view);
    }
}
