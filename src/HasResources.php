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
     * Sync resources from a specific provider
     *
     * @param string $provider The provider to sync from (e.g., 'wikidata', 'gnd', 'wikipedia')
     * @return array Array of synced resources
     */
    public function syncFromProvider(string $provider, $filter = []): array
    {
        $syncService = new ResourceSyncService($filter);
        return $syncService->syncFromProvider($this, $provider);
    }

    /**
     * Fetch more resources which are fetched from a provider like wikidata
     *
     * @deprecated Use syncFromProvider() instead
     * @param string $provider (wikidata, wikipedia or gnd)
     * @return mixed
     */
    public function saveMoreResources($provider, $filter = [])
    {
        return $this->syncFromProvider($provider, $filter);
    }
}
