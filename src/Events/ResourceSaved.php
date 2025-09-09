<?php

namespace KraenzleRitter\Resources\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use KraenzleRitter\Resources\Resource;

class ResourceSaved
{
    use Dispatchable, SerializesModels;

    public $resource;

    public $model;

    public function __construct(Resource $resource, int $model_id)
    {
        $this->resource = $resource;
        $this->model_id = $model_id;
    }
}
