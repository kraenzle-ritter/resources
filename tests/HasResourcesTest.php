<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Resource;

class HasResourcesTest extends TestCase
{
    /** @test */
    public function test_it_adds_resources()
    {
        $model = TestModel::create(['name' => 'this is a test']);

        $model->updateOrCreateResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123']);
        $model->updateOrCreateResource(['provider' => 'geonames', 'provider_id' => 123, 'url' => 'https://www.geonames.de/123']);

        $this->assertEquals(2, count($model->resources));
    }

    /** @test */
    public function test_it_does_not_add_the_same_resource_twice()
    {
        $model = TestModel::create(['name' => 'this is a test']);

        $model->updateOrCreateResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123', 'full_json' => ['Karl Valentin']]);
        $model->updateOrCreateResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123', 'full_json' => ['Gerhard Polt']]);

        $this->assertEquals(1, count($model->resources));
    }

    /** @test */
    public function test_it_updates_resources()
    {
        $model = TestModel::create(['name' => 'this is a test']);
        $model->updateOrCreateResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123']);

        $resource = Resource::where('provider', 'gnd')->where('provider_id',123)->first();
        $model->updateResource($resource->id, ['provider' => 'gnd', 'provider_id' => 567, 'url' => 'https://www.gnd.de/567']);

        $this->assertEquals(567, Resource::find($resource->id)->provider_id);
    }

    /** @test */
    public function test_it_removes_resources()
    {
        $model = TestModel::create(['name' => 'this is a test']);
        $model->updateOrCreateResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123']);
        $model->updateOrCreateResource(['provider' => 'geonames', 'provider_id' => 123, 'url' => 'https://www.geonames.de/123']);

        $resource = Resource::where('provider', 'gnd')->where('provider_id',123)->first();
        $model->removeResource($resource->id);

        $this->assertEquals(1, count($model->resources));
    }
}
