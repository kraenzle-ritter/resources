<?php

namespace KraenzleRitter\Resources\Tests;

class ResourcesFetchTest extends TestCase
{
    public function test_fetch_for_gnd()
    {
        $model = TestModel::create(['name' => 'this is a test']);
        $model->updateOrCreateResource(['provider' => 'gnd', 'provider_id' => 118519522, 'url' => 'https://www.gnd.de/123']);
        $this->assertEquals(1, count($model->resources));
        $this->artisan('resources:fetch --provider gnd');

        $model = TestModel::find(1);
        $this->assertTrue($model->resources->contains('provider', 'gnd'));
        $this->assertTrue($model->resources->contains('provider', 'viaf'));
    }

    public function test_fetch_for_wikidata()
    {
        $model = TestModel::create(['name' => 'this is a test']);
        $model->updateOrCreateResource(['provider' => 'wikidata', 'provider_id' => 'Q57188', 'url' => 'https://www.wikidata.org/wiki/Q57188']);

        $this->assertEquals(1, count($model->resources));

        $this->artisan('resources:fetch --provider wikidata');

        $model = TestModel::find(1);
        $this->assertTrue($model->resources->contains('provider', 'gnd'));
        $this->assertTrue($model->resources->contains('provider', 'viaf'));
    }

    public function test_fetch_for_wikipedia()
    {
        $model = TestModel::create(['name' => 'this is a test']);
        $model->updateOrCreateResource(['provider' => 'wikipedia', 'provider_id' => 556846, 'url' => 'https://de.wikipedia.org/Ernst Cassirer']);

        $this->assertEquals(1, count($model->resources));

        $this->artisan('resources:fetch --provider wikipedia');

        $model = TestModel::find(1);
        $this->assertTrue($model->resources->contains('provider', 'gnd'));
        $this->assertTrue($model->resources->contains('provider', 'viaf'));
    }
}
