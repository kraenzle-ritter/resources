<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Resource;

class ResourceTest extends TestCase
{
    public function test_resource_can_be_created()
    {
        $resource = Resource::create([
            'provider' => 'wikidata',
            'provider_id' => 'Q12345',
            'url' => 'https://www.wikidata.org/wiki/Q12345',
            'resourceable_type' => TestModel::class,
            'resourceable_id' => 1
        ]);

        $this->assertInstanceOf(Resource::class, $resource);
        $this->assertEquals('wikidata', $resource->provider);
        $this->assertEquals('Q12345', $resource->provider_id);
        $this->assertEquals('https://www.wikidata.org/wiki/Q12345', $resource->url);
    }

    public function test_resource_belongs_to_model()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        $resource = Resource::create([
            'provider' => 'gnd',
            'provider_id' => '123456',
            'url' => 'https://d-nb.info/gnd/123456',
            'resourceable_type' => TestModel::class,
            'resourceable_id' => $model->id
        ]);

        $this->assertEquals($model->id, $resource->resourceable_id);
        $this->assertEquals(TestModel::class, $resource->resourceable_type);

        // Test the polymorphic relationship
        $this->assertInstanceOf(TestModel::class, $resource->resourceable);
        $this->assertEquals('Test Model', $resource->resourceable->name);
    }

    public function test_resource_has_json_attribute()
    {
        $jsonData = ['title' => 'Test Title', 'description' => 'Test Description'];

        $resource = Resource::create([
            'provider' => 'wikipedia',
            'provider_id' => '789',
            'url' => 'https://de.wikipedia.org/wiki/Test',
            'full_json' => $jsonData,
            'resourceable_type' => TestModel::class,
            'resourceable_id' => 1
        ]);

        $this->assertEquals($jsonData, $resource->full_json);
        $this->assertEquals('Test Title', $resource->full_json['title']);
    }
}
