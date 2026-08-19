<?php

namespace KraenzleRitter\Resources\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KraenzleRitter\Resources\Resource;

class ResourceSaved
{
    use Dispatchable, SerializesModels;

    public Resource $resource;

    /**
     * Id of the model the resource was attached to.
     *
     * Declared rather than assigned dynamically: listeners in the consuming
     * applications read `$event->model_id`, which worked only because PHP still
     * tolerates dynamic properties (deprecated since 8.2).
     */
    public int $model_id;

    public function __construct(Resource $resource, int $model_id)
    {
        $this->resource = $resource;
        $this->model_id = $model_id;
    }
}
