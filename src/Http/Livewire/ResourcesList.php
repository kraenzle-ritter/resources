<?php

namespace KraenzleRitter\Resources\Http\Livewire;

use Livewire\Component;

class ResourcesList extends Component
{
    public $model;

    public $deleteButton;

    public $resources;

    protected $listeners = ['resourcesChanged' => 'render'];

    public function mount($model, $deleteButton = false)
    {
        $this->model = $model;
        $this->deleteButton = $deleteButton;
    }

    public function removeResource($id)
    {
        // $id arrives from the browser, so the delete has to be scoped to the
        // mounted model rather than looking the row up by primary key alone.
        $removed = (bool) $this->model->resources()->whereKey($id)->delete();

        if (! $removed) {
            logger()->warning('Refused to remove a resource that does not belong to the model', [
                'resource_id' => $id,
                'model_type' => get_class($this->model),
                'model_id' => $this->model->id ?? null,
            ]);
        }

        $this->dispatch('resourcesChanged');

        return $removed;
    }

    public function render()
    {
        $this->model->load('resources');

        $this->resources = $this->model->resources; //->sortBy('provider');

        $view = view()->exists('vendor.kraenzle-ritter.livewire.resources-list')
              ? 'vendor.kraenzle-ritter.livewire.resources-list'
              : 'resources::livewire.resources-list';

        return view($view);
    }
}
