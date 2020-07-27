<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Support\Str;
use KraenzleRitter\Resources\Resource;

class HasResourcesTest extends TestCase
{

    /** @test */
    public function test_it_adds_resources()
    {
        $model = TestModel::create(['name' => 'this is a test']);

        $model->addResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123']);
        $model->addResource(['provider' => 'genoames', 'provider_id' => 123, 'url' => 'https://www.geonames.de/123']);

        $this->assertEquals(2, count($model->resources));
    }

    /** @test */
    public function test_it_updates_resources()
    {
        $model = TestModel::create(['name' => 'this is a test']);
        $model->addResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123']);

        $resource = Resource::where('provider', 'gnd')->where('provider_id',123)->first();
        $model->updateResource($resource->id, ['provider' => 'gnd', 'provider_id' => 567, 'url' => 'https://www.gnd.de/567']);

        $this->assertEquals(567, Resource::find($resource->id)->provider_id);
    }

    /** @test */
    public function test_it_deletes_resources()
    {
        $model = TestModel::create(['name' => 'this is a test']);
        $model->addResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123']);
        $model->addResource(['provider' => 'genoames', 'provider_id' => 123, 'url' => 'https://www.geonames.de/123']);

        $resource = Resource::where('provider', 'gnd')->where('provider_id',123)->first();
        $model->deleteResource($resource->id);

        $this->assertEquals(1, count($model->resources));
    }
}
