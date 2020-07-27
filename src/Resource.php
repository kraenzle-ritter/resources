<?php

namespace KraenzleRitter\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Resource extends Model
{
    public function resourceable(): MorphTo
    {
        return $this->morphTo();
    }

    protected $guarded = [];

    protected $table;

    protected $casts = [
        'full_json' => 'array'
    ];

    protected function asJson($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public function __construct(array $attributes = [])
    {
        $this->table = config('resources.table', 'resources');
        parent::__construct($attributes);
    }

    public function addResource(Model $resourceable, array $data)
    {
        return $resourceable->resources()->firstOrCreate(
            $data
        );
    }

    public function updateResource(int $id, array $data)
    {
        return (bool) static::find($id)->update($data);
    }

    public function deleteResource(int $id)
    {
        return (bool) static::find($id)->delete();
    }


}
