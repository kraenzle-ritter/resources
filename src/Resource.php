<?php

namespace KraenzleRitter\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;
use KraenzleRitter\Resources\Helpers\UrlHelper;

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

    /**
     * The url column is NOT NULL, but identifier-only rows are a legitimate
     * pattern — KB's Place::setGeonamesIdAttribute() writes a provider_id and
     * nothing else. Without this default such a write only survives on a MySQL
     * that is not in strict mode; on SQLite or strict MySQL it fails with a
     * NOT NULL violation. Defaulting to '' makes the pattern portable instead
     * of accidental.
     */
    protected $attributes = [
        'url' => '',
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
        // rename according to config('resources.rename') - fallback to empty array if not configured
        $mapping = config('resources.rename', []);
        $value = $mapping[$value] ?? $value;
        $this->attributes['provider'] = strtolower($value);
    }

    public function setProviderIdAttribute($value)
    {
        $this->attributes['provider_id'] = urldecode($value);
    }

    public function updateOrCreateResource(Model $resourceable, array $data)
    {
        self::assertUrlIsSafe($data['url'] ?? null);

        $value = $data['provider'];
        unset($data['provider']);
        $mapping = config('resources.rename', []);
        $value = $mapping[$value] ?? $value;
        return $resourceable->resources()->updateOrCreate(
            ['provider' => $value],
             $data
        );
    }

    /**
     * Reject a URL whose scheme could execute script once rendered into an href.
     *
     * Deliberately enforced here rather than in a saving/creating model event:
     * consumers write resources straight through the relation
     * (KB's Place::setGeonamesIdAttribute(), Anton's SikIseaImportActors and
     * BeaconReader), and a model-level rule would break them. A missing url is
     * allowed — identifier-only rows are a legitimate, in-use pattern.
     *
     * @throws ValidationException
     */
    protected static function assertUrlIsSafe(?string $url): void
    {
        if (UrlHelper::isAbsent($url) || UrlHelper::isSafe($url)) {
            return;
        }

        throw ValidationException::withMessages([
            'url' => sprintf(
                'The resource url must use one of these schemes: %s.',
                implode(', ', UrlHelper::allowedSchemes())
            ),
        ]);
    }

    public function updateResource(int $id, array $data)
    {
        self::assertUrlIsSafe($data['url'] ?? null);

        $resource = static::find($id);

        return $resource ? (bool) $resource->update($data) : false;
    }

    public function removeResource(int $id)
    {
        $resource = static::find($id);

        return $resource ? (bool) $resource->delete() : false;
    }

    /**
     * Remove a resource, but only if it belongs to the given model.
     *
     * removeResource() above looks up by primary key alone and cannot tell
     * whose row it is, so anything reachable from a request must go through
     * this instead.
     */
    public function removeResourceFor(Model $resourceable, $id): bool
    {
        return (bool) $resourceable->resources()->whereKey($id)->delete();
    }

}
