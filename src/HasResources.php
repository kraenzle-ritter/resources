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
        return (new Resource())->removeResourceFor($this, $id);
    }

    /**
     * Sync resources from a specific provider
     *
     * @param string $provider The provider to sync from (e.g., 'wikidata', 'gnd', 'wikipedia')
     * @param $filter Array of provider names to exclude from sync
     * @return array Array of synced resources
     */
    public function syncFromProvider(string $provider, $filter = []): array
    {
        // Resolved from the container rather than constructed directly, so an
        // application - or the test suite - can substitute the service.
        $syncService = app()->makeWith(ResourceSyncService::class, ['filter' => $filter]);

        return $syncService->syncFromProvider($this, $provider);
    }
}
