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

    protected function asJson($value, $flags = 0)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | $flags);
    }

    public function __construct(array $attributes = [])
    {
        $this->table = config('resources.table', 'resources');
        parent::__construct($attributes);
    }

    public function setProviderAttribute($value)
    {
        $this->attributes['provider'] = strtolower($value);
    }

    public function setProviderIdAttribute($value)
    {
        $this->attributes['provider_id'] = urldecode($value);
    }

    public function updateOrCreateResource(Model $resourceable, array $data)
    {
        $provider = $data['provider'];
        unset($data['provider']);

        return $resourceable->resources()->updateOrCreate(
            ['provider' => $provider],
             $data
        );
    }

    public function updateResource(int $id, array $data)
    {
        return (bool) static::find($id)->update($data);
    }

    public function removeResource(int $id)
    {
        return (bool) static::find($id)->delete();
    }

}
