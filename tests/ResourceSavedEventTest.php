<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Support\Facades\Event;
use KraenzleRitter\Resources\Events\ResourceSaved;
use KraenzleRitter\Resources\Resource;

/**
 * Both consuming applications read `$event->model_id` in
 * UpdateLocationWithGeonamesCoordinates. It was an undeclared dynamic property,
 * which PHP has deprecated since 8.2 — hence the `@phpstan-ignore-line` on their
 * call sites.
 */
class ResourceSavedEventTest extends TestCase
{
    private function makeResource(): Resource
    {
        $model = TestModel::create(['name' => 'Event Test']);

        return $model->updateOrCreateResource([
            'provider' => 'geonames',
            'provider_id' => '2657896',
            'url' => 'https://www.geonames.org/2657896',
        ]);
    }

    public function test_model_id_is_a_declared_property()
    {
        $this->assertTrue(
            (new \ReflectionClass(ResourceSaved::class))->hasProperty('model_id'),
            'ResourceSaved must declare $model_id rather than creating it dynamically'
        );
    }

    public function test_constructing_the_event_creates_no_dynamic_property()
    {
        $resource = $this->makeResource();

        $seen = [];
        set_error_handler(
            function (int $errno, string $message) use (&$seen): bool {
                $seen[] = $message;

                return true;
            },
            E_DEPRECATED | E_USER_DEPRECATED
        );

        try {
            new ResourceSaved($resource, 42);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $seen, "Constructing ResourceSaved raised:\n" . implode("\n", $seen));
    }

    public function test_consumers_can_read_resource_and_model_id()
    {
        $resource = $this->makeResource();

        $event = new ResourceSaved($resource, 42);

        $this->assertSame(42, $event->model_id);
        $this->assertSame($resource->id, $event->resource->id);
        $this->assertSame('geonames', $event->resource->provider);
    }

    public function test_the_event_is_dispatched_with_the_model_id()
    {
        Event::fake([ResourceSaved::class]);

        $model = TestModel::create(['name' => 'Dispatch Test']);
        $resource = $model->updateOrCreateResource([
            'provider' => 'geonames',
            'provider_id' => '2657896',
            'url' => 'https://www.geonames.org/2657896',
        ]);

        event(new ResourceSaved($resource, $model->id));

        Event::assertDispatched(
            ResourceSaved::class,
            fn (ResourceSaved $event) => $event->model_id === $model->id
                && $event->resource->provider === 'geonames'
        );
    }
}
