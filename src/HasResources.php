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
     * Create a resource.
     *
     * @param array      $data
     * @param Model      $creator
     * @param Model|null $parent
     *
     * @return static
     */
    public function comment(array $data)
    {
        $comment = (new Resource())->createRsource($this, $data);

        return $comment;
    }

    /**
     * Create a resource and persists it.
     *
     * @param Model $resourceable
     * @param array $data
     *
     * @return static
     */
    public function addResource(array $data)
    {
        return (new Resource())->addResource($this, $data);
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
        $resourceableModel = $this->resourceableModel();

        return (bool) (new $resourceableModel())->deleteResource($id);
    }

}
