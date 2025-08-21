<?php

namespace KraenzleRitter\Resources;

use KraenzleRitter\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasResources
{
    public function resources(): MorphMany
    {
        return $this->morphMany(Resource::class, 'resourceable');
    }

    public function hasResources() : bool
    {
        return (bool) $this->resources->count();
    }

    /**
     * Create a resource and persists it.
     *
     * @param array $data
     *
     * @return static
     */
    public function updateOrCreateResource(array $data)
    {
        return (new Resource())->updateOrCreateResource($this, $data);
    }

    /**
     * Update a resource.
     *
     * @param $id
     * @param $data
     *
     * @return mixed
     */
    public function updateResource(int $id, array $data)
    {
        return (new Resource())->updateResource($id, $data);
    }

    /**
     * Delete a resource.
     *
     * @param int $id
     *
     * @return mixed
     */
    public function removeResource(string $id): bool
    {
        return (bool) (new Resource())->removeResource($id);
    }

    /**
     * Fetch more resources which are fetched from a provider like wikidata
     *
     * @param string $provider (wikidata, wikipedia or gnd)
     * @return void
     */
    public function saveMoreResources($provider)
    {
        $this->load('resources');
        $service = new FetchResourcesService($provider);
        $resource = $this->resources->where('provider', $provider)->first();

        if (!$resource) {
            return null;
        }

        $new_resources = $service->run($resource->provider_id);

        if ($new_resources) {
            foreach ($new_resources as $new_resource) {
                try {
                    (new Resource())->updateOrCreateResource($this, $new_resource);
                } catch (\Exception $e) {
                    \Log::error('Error updating/creating resource: ' . $e->getMessage(), [
                        'provider' => $provider,
                        'resource' => $new_resource,
                        'exception' => $e
                    ]);
                }
            }
        } else {
            \Log::warning('Could not find a resource at ' . $provider . ' for id '. $resource->provider_id .': '. $resource->provider_id);
        }

        return $resource;
    }
}
