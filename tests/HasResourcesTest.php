<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Support\Str;
use KraenzleRitter\Resources\Resource;

class HasResourcesTest extends TestCase
{
    /** @test */
    public function test_it_will_save_a_resource_when_saving_a_model()
    {
        $model = TestModel::create(['name' => 'this is a test']);

        $this->assertEquals('this is a test', $model->name);
    }

    /** @test */
    public function test_it_will_add_a_resources()
    {
        $model = TestModel::create(['name' => 'this is a test']);

        $model->addResource(['provider' => 'gnd', 'provider_id' => 123, 'url' => 'https://www.gnd.de/123']);
        $model->addResource(['provider' => 'geoames', 'provider_id' => 123, 'url' => 'https://www.geonames.de/123']);

        $this->assertEquals(2, count($model->resources));
    }
}
