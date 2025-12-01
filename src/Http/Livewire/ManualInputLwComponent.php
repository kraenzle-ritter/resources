<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;
use KraenzleRitter\Resources\Resource;
use KraenzleRitter\Resources\Events\ResourceSaved;

class ManualInputLwComponent extends Component
{
    public $provider;

    public $provider_id;

    public $url;

    public $model;

    public $resourceable_id;

    public $showAll = false; // Flag for displaying all results

    public $saveMethod = 'updateOrCreateResource';

    public $removeMethod = 'removeResource'; // url

    protected $listeners = ['resourcesChanged' => 'render'];


    protected function rules()
    {
        return [
            '$provider' => 'required|string',
            '$provider_id' => 'nullable|string',
            '$url' => 'required|url',
        ];
    }

    public function mount($model, string $search = '', array $params = [])
    {
        $this->model = $model;
    }

    public function updated($propertyName)
    {
        $this->only($propertyName);
    }

    public function saveResource()
    {
        $resource = $this->model->{$this->saveMethod}(
            $this->only(['provider', 'provider_id', 'url'])
        );

        $this->dispatch('resourcesChanged');
        event(new ResourceSaved($resource, $this->model->id));
    }

    public function render()
    {
        logger(__METHOD__);
        $view = view()->exists('vendor.kraenzle-ritter.livewire.manual-input-lw-component')
              ? 'vendor.kraenzle-ritter.livewire.manual-input-lw-component'
              : 'resources::livewire.manual-input-lw-component';

        return view($view, [
            'showAll' => $this->showAll
        ]);
    }
}
