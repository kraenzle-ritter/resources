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

    /**
     * Create a resource and persists it.
     *
     * @param array $data
     *
     * @return static
     */
    public function addResource(array $data)
    {
        return (new Resource())->addResource($this, $data);
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
    public function deleteResource(int $id): bool
    {
        return (bool) (new Resource())->deleteResource($id);
    }

}
